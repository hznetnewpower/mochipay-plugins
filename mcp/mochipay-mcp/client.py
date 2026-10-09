"""MochiPay's exact-byte API contract. No wallet signing or transfers."""
import base64
import hashlib
import hmac
import json
import re
import sqlite3
import ssl
import time
import uuid
from contextlib import contextmanager
from decimal import Decimal
from pathlib import Path
from urllib.parse import urlencode, urlsplit
from urllib.request import Request, build_opener, HTTPSHandler, HTTPRedirectHandler
from urllib.error import HTTPError

METHODS = ('USDT_TRC20', 'USDC_ERC20', 'BTC_BITCOIN', 'ETH_ERC20', 'SOL_SOLANA')
STATES = ('WAITING_PAYMENT', 'CONFIRMING', 'PAID', 'UNDERPAID', 'OVERPAID', 'EXPIRED', 'CANCELLED', 'CANCELED', 'PENDING', 'WAITING')

class PaymentError(Exception):
    def __init__(self, code, http=0, recovery_contract=''):
        self.code, self.http, self.recovery_contract = code, http, recovery_contract
        super().__init__(code)
    def can_recover_not_found(self):
        return self.http == 404 and self.code == 'ORDER_NOT_FOUND' and self.recovery_contract == 'request-id-v1'
    def is_rejected(self):
        return 400 <= self.http < 500 and self.http not in (408,409,429)


def money(value):
    text = str(value)
    if len(text) > 100 or not re.fullmatch(r'[0-9]+(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?', text):
        raise PaymentError('INVALID_AMOUNT')
    value = Decimal(text)
    if not value.is_finite() or value > Decimal('1e28') or value.as_tuple().exponent < -28:
        raise PaymentError('INVALID_AMOUNT')
    return format(value, 'f')

class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None

class Client:
    def __init__(self, base_url, key, secret, database, allow_create=False, max_amount='1000'):
        u = urlsplit(base_url)
        if u.scheme != 'https' or not u.hostname or u.username or u.password or u.query or u.fragment or u.path not in ('', '/') or u.port not in (None, 443):
            raise PaymentError('INVALID_MOCHIPAY_URL')
        if not key or not secret or any(c in key+secret for c in '\r\n'):
            raise PaymentError('API_NOT_CONFIGURED')
        self.base = base_url.rstrip('/')
        self.key, self.secret = key, secret
        self.scope = hashlib.sha256((self.base+'\0'+key).encode()).hexdigest()
        self.database = Path(database).expanduser().resolve()
        self.database.parent.mkdir(parents=True, exist_ok=True, mode=0o700)
        self.allow_create, self.maximum = allow_create, Decimal(money(max_amount))
        self.opener = build_opener(NoRedirect(), HTTPSHandler(context=ssl.create_default_context()))
        with self.connect() as db:
            db.execute('CREATE TABLE IF NOT EXISTS attempts (scope TEXT, request_id TEXT, payload TEXT NOT NULL, snapshot TEXT, stage TEXT NOT NULL, started REAL NOT NULL, PRIMARY KEY(scope,request_id))')
        self.database.chmod(0o600)

    @contextmanager
    def connect(self):
        db = sqlite3.connect(self.database, timeout=10)
        try:
            with db:
                yield db
        finally:
            db.close()

    def request(self, method, path, text):
        raw = text.encode('utf-8')
        signature = base64.b64encode(hmac.new(self.secret.encode(), raw, hashlib.sha256).digest()).decode()
        req = Request(self.base+path, data=raw if method=='POST' else None, method=method,
                      headers={'Content-Type':'application/json', 'Accept':'application/json', 'X-Mochi-Key':self.key, 'X-Mochi-Signature':signature})
        try:
            with self.opener.open(req, timeout=30) as response:
                raw = response.read(262145)
                if len(raw)>262144:
                    raise PaymentError('INVALID_API_RESPONSE')
                data = json.loads(raw, parse_float=Decimal, parse_int=Decimal)
                if not isinstance(data, dict):
                    raise PaymentError('INVALID_API_RESPONSE')
                if data.get('success') is not True:
                    raise PaymentError(data.get('message') if re.fullmatch(r'[A-Z][A-Z0-9_]{0,100}', str(data.get('message',''))) else 'API_REQUEST_FAILED', getattr(response,'status',200), data.get('recovery_contract',''))
                return data
        except PaymentError:
            raise
        except HTTPError as e:
            try:
                data=json.loads(e.read(262145), parse_float=Decimal, parse_int=Decimal)
                code=data.get('message','') if isinstance(data,dict) else ''
                contract=data.get('recovery_contract','') if isinstance(data,dict) else ''
            except Exception:
                code,contract='', ''
            if not re.fullmatch(r'[A-Z][A-Z0-9_]{0,100}', str(code)): code='API_HTTP_'+str(e.code)
            raise PaymentError(code,e.code,contract) from None
        except json.JSONDecodeError:
            raise PaymentError('INVALID_API_RESPONSE') from None
        except Exception:
            raise PaymentError('API_UNAVAILABLE') from None

    def query(self, field, value):
        text = urlencode({field:value})
        for attempt in range(3):
            try:
                return self.request('GET', '/api/v1/orders/query?'+text, text)
            except PaymentError as error:
                transient = error.code == 'API_UNAVAILABLE' or error.http in (408, 429) or error.http >= 500
                if not transient or attempt == 2:
                    raise
                time.sleep(attempt + 1)

    def validate(self, data, payload, snapshot=None):
        method = data.get('payment_method') or str(data.get('wallet_type',''))+'_'+str(data.get('network',''))
        oid = str(data.get('order_id',''))
        if not re.fullmatch('[a-f0-9]{32}', oid) or method != payload['payment_method'] or data.get('merchant_order_id') != payload['merchant_order_id'] or data.get('currency') != payload['currency'] or Decimal(money(data.get('amount',''))) != Decimal(payload['amount']):
            raise PaymentError('ORDER_BINDING_MISMATCH')
        if data.get('status') not in STATES or Decimal(money(data.get('pay_amount',''))) <= 0 or not re.fullmatch('[A-Za-z0-9]{20,120}', str(data.get('payment_address',''))):
            raise PaymentError('INVALID_PAYMENT_INSTRUCTIONS')
        if data.get('payment_url') != self.base+'/pay/'+oid:
            raise PaymentError('INVALID_PAYMENT_URL')
        if snapshot and (oid != snapshot['order_id'] or data['payment_address'] != snapshot['payment_address'] or Decimal(money(data['pay_amount'])) != Decimal(snapshot['pay_amount'])):
            raise PaymentError('ORDER_BINDING_MISMATCH')
        return {'order_id':oid, 'payment_address':data['payment_address'], 'pay_amount':money(data['pay_amount'])}

    def view(self, data, payload, checkout_mode='ON_SITE'):
        result = {k:str(data.get(k,'')) for k in ('order_id','merchant_order_id','status','currency','payment_address','payment_url','expires_at','tx_hash')}
        for k in ('amount','pay_amount','received_amount'):
            result[k] = money(data.get(k,'0'))
        result['payment_method'] = payload['payment_method']
        result['confirmations'] = int(data.get('confirmations',0))
        result['checkout_mode'] = checkout_mode
        result['payment_verified'] = result['status']=='PAID' and Decimal(result['received_amount'])==Decimal(result['pay_amount'])
        result['checkout_note'] = ('Use the payment fields in your own application checkout; this tool does not embed a payment UI in chat.' if checkout_mode=='ON_SITE' else 'Open payment_url to use hosted checkout.')
        return result

    def create(self, request_id, amount, currency='USD', payment_method='USDT_TRC20', description='Payment request', checkout_mode='ON_SITE', unique_amount_direction='UP', merchant_order_id=''):
        if not self.allow_create:
            raise PaymentError('CREATE_DISABLED_SET_MOCHIPAY_ALLOW_CREATE')
        if not re.fullmatch('[A-Za-z0-9_.-]{6,100}', request_id):
            raise PaymentError('INVALID_REQUEST_ID')
        if not isinstance(amount,str) or not re.fullmatch(r'[0-9]{1,18}(?:\.[0-9]{1,18})?',amount):
            raise PaymentError('USE_EXACT_DECIMAL_STRING')
        amount = money(amount)
        if Decimal(amount)<=0 or Decimal(amount)>self.maximum:
            raise PaymentError('AMOUNT_OUTSIDE_CONFIGURED_LIMIT')
        if not re.fullmatch('[A-Z]{3,10}',currency) or payment_method not in METHODS or checkout_mode not in ('ON_SITE','HPP') or unique_amount_direction not in ('UP','DOWN') or len(description)>500:
            raise PaymentError('INVALID_PAYMENT_OPTIONS')
        if merchant_order_id and (not isinstance(merchant_order_id,str) or len(merchant_order_id)>100 or any(ord(c)<32 for c in merchant_order_id)):
            raise PaymentError('INVALID_MERCHANT_ORDER_ID')
        with self.connect() as db:
            db.execute('BEGIN IMMEDIATE')
            row = db.execute('SELECT payload,snapshot,stage FROM attempts WHERE scope=? AND request_id=?',(self.scope,request_id)).fetchone()
            if row:
                payload=json.loads(row[0]);snapshot=json.loads(row[1]) if row[1] else None
                if any(payload[k]!=v for k,v in {'amount':amount,'currency':currency,'payment_method':payment_method,'description':description,'unique_amount_direction':unique_amount_direction}.items()):
                    raise PaymentError('REQUEST_ID_ALREADY_USED_WITH_DIFFERENT_DETAILS')
                if merchant_order_id and payload['merchant_order_id']!=merchant_order_id:
                    raise PaymentError('REQUEST_ID_ALREADY_USED_WITH_DIFFERENT_DETAILS')
                fresh=row[2]=='REJECTED'
            else:
                payload={'request_id':'mcp:'+hashlib.sha256((self.scope+':'+request_id).encode()).hexdigest()[:60], 'merchant_order_id':merchant_order_id or 'mcp-'+uuid.uuid4().hex,'amount':amount,'currency':currency,'payment_method':payment_method,'description':description,'product_type':'DIGITAL','unique_amount_direction':unique_amount_direction}
                snapshot=None;fresh=True
                db.execute('INSERT INTO attempts VALUES (?,?,?,?,?,?)',(self.scope,request_id,json.dumps(payload),None,'CREATING',time.time()))
        # Persist before POST. Recovery queries first, then uses the advertised request-id contract.
        try:
            creating=fresh
            if fresh:
                data=self.request('POST','/api/v1/orders/create',json.dumps(payload,ensure_ascii=False,separators=(',',':')))
            else:
                try:
                    data=self.query('order_id' if snapshot else 'request_id',snapshot['order_id'] if snapshot else payload['request_id'])
                except PaymentError as e:
                    if snapshot or not e.can_recover_not_found(): raise
                    creating=True
                    data=self.request('POST','/api/v1/orders/create',json.dumps(payload,ensure_ascii=False,separators=(',',':')))

            binding=self.validate(data,payload,snapshot)
            with self.connect() as db:
                db.execute('UPDATE attempts SET snapshot=?,stage=? WHERE scope=? AND request_id=?',(json.dumps(binding),'READY',self.scope,request_id))
            result=self.view(data,payload,checkout_mode);result['request_id']=request_id;result['reused_order']=not fresh
            return result
        except PaymentError as e:
            if creating and e.is_rejected():
                with self.connect() as db:
                    db.execute('UPDATE attempts SET stage=? WHERE scope=? AND request_id=?',('REJECTED',self.scope,request_id))
                raise PaymentError('CREATE_REJECTED_'+e.code) from None
            raise PaymentError('PAYMENT_TEMPORARILY_UNAVAILABLE_RETRY_SAME_REQUEST_ID_'+e.code) from None

    def status(self, request_id):
        if not re.fullmatch('[A-Za-z0-9_.-]{6,100}',request_id):
            raise PaymentError('INVALID_REQUEST_ID')
        with self.connect() as db:
            row=db.execute('SELECT payload,snapshot FROM attempts WHERE scope=? AND request_id=?',(self.scope,request_id)).fetchone()
        if not row:
            raise PaymentError('LOCAL_REQUEST_NOT_FOUND')
        payload=json.loads(row[0]);snapshot=json.loads(row[1]) if row[1] else None
        data=self.query('order_id' if snapshot else 'request_id',snapshot['order_id'] if snapshot else payload['request_id'])
        binding=self.validate(data,payload,snapshot)
        if not snapshot:
            with self.connect() as db:
                db.execute('UPDATE attempts SET snapshot=?,stage=? WHERE scope=? AND request_id=?',(json.dumps(binding),'READY',self.scope,request_id))
        result=self.view(data,payload);result['request_id']=request_id
        return result
