"""MochiPay standard-library staging server. API credentials never reach clients."""
import os, json, re, hmac, hashlib, base64, secrets, atexit, signal, threading
from pathlib import Path
from urllib.parse import urlsplit, urlencode, parse_qs
from urllib.request import Request, build_opener, HTTPRedirectHandler
from urllib.error import HTTPError, URLError
from http.server import ThreadingHTTPServer, BaseHTTPRequestHandler

ROOT = Path(__file__).resolve().parent
env = os.environ.get
BASE = env('MOCHIPAY_BASE_URL', 'https://mochi.bz').rstrip('/')
PUBLIC = env('APP_PUBLIC_URL', 'http://127.0.0.1:8080').rstrip('/')
def origin(value):
    u = urlsplit(value)
    if u.username or u.password or u.path not in ['', '/'] or u.query or u.fragment or not (u.scheme == 'https' or u.scheme == 'http' and u.hostname in ['127.0.0.1', 'localhost']):
        raise ValueError('Configure an HTTPS origin; loopback HTTP only for local development')
    return u.scheme + '://' + u.netloc
origin(BASE); origin(PUBLIC)
KEY, SECRET, ACCESS = env('MOCHIPAY_API_KEY', ''), env('MOCHIPAY_API_SECRET', ''), env('DEMO_ACCESS_TOKEN', '')
if not KEY or not SECRET or len(ACCESS) < 32 or ACCESS.startswith('replace-'):
    raise ValueError('Set private API credentials and a random DEMO_ACCESS_TOKEN of 32+ characters')
METHODS = ['USDT_TRC20', 'USDC_ERC20', 'BTC_BITCOIN', 'ETH_ERC20', 'SOL_SOLANA']
LANGS = ['en', 'zh', 'es', 'pt-br', 'fr', 'de', 'nl', 'fa', 'ru', 'ar','ja','ko','it','tr','id']
STORE = Path(env('DEMO_DATA_DIR', str(ROOT/'private-data'))).resolve()
STORE.mkdir(parents=True, exist_ok=True, mode=0o700)
LOCK = STORE/'.instance.lock'
with LOCK.open('x') as f: f.write(str(os.getpid()))
atexit.register(lambda: LOCK.unlink(missing_ok=True))
for sig in [signal.SIGINT, signal.SIGTERM]: signal.signal(sig, lambda *_: exit(0))
MUTEX = threading.RLock()
def decimal(value):
    text = str(value)
    if not re.fullmatch(r'\d{1,28}(?:\.\d{1,18})?', text): raise ValueError('Invalid decimal')
    a, _, b = text.partition('.')
    return (a.lstrip('0') or '0') + ('.'+b.rstrip('0') if b.rstrip('0') else '')
AMOUNT, CURRENCY, DIRECTION = env('DEMO_AMOUNT', '10.00'), env('DEMO_CURRENCY', 'USD').upper(), env('DEMO_DIRECTION', 'UP')
if decimal(AMOUNT) == '0' or not re.fullmatch('[A-Z]{3,10}', CURRENCY) or DIRECTION not in ['UP', 'DOWN']: raise ValueError('Invalid server price/direction')
class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, *args): return None
OPENER = build_opener(NoRedirect())
class QueryUnavailable(Exception): pass
def api(route, payload):
    post = isinstance(payload, dict)
    text = json.dumps(payload, separators=(',', ':'), ensure_ascii=False) if post else payload
    raw = text.encode('utf-8')
    sig = base64.b64encode(hmac.new(SECRET.encode(), raw, hashlib.sha256).digest()).decode()
    req = Request(BASE+route+('' if post else '?'+text), data=raw if post else None, headers={'Content-Type':'application/json','X-Mochi-Key':KEY,'X-Mochi-Signature':sig}, method='POST' if post else 'GET')
    try:
        with OPENER.open(req, timeout=30) as res:
            data = res.read(1048577)
            if len(data) > 1048576: raise ValueError('Upstream response too large')
            d = json.loads(data.decode(), parse_float=str, parse_int=str)
            if res.status != 200 or d.get('success') is not True: raise ValueError('Upstream rejected payment')
            return d
    except HTTPError as error:
        if error.code in (408,429) or error.code>=500: raise QueryUnavailable() from None
        raise
    except (URLError,TimeoutError,ConnectionError): raise QueryUnavailable() from None

def filename(r):
    if not isinstance(r, str) or not re.fullmatch(r'[A-Za-z0-9._:-]{1,64}', r): raise ValueError('Invalid request_id')
    return STORE/(r.encode().hex()+'.json')
def load(r): return json.loads(filename(r).read_text())
def save(r, a):
    p = filename(r); t = p.with_suffix('.'+secrets.token_hex(8)+'.tmp')
    with t.open('x') as f:
        json.dump(a, f, separators=(',', ':')); f.flush(); os.fsync(f.fileno())
    os.chmod(t, 0o600); os.replace(t, p)
def method(d): return d.get('payment_method') or d.get('wallet_type', '')+'_'+d.get('network', '')
def hpp(d):
    u = urlsplit(d['payment_url'])
    if origin(BASE) != u.scheme+'://'+u.netloc or u.username or u.password or u.path != '/pay/'+d['order_id'] or u.query or u.fragment: raise ValueError('Invalid hosted URL')
    return d['payment_url']
def bind(a, d, check_paid=True):
    p = a['payload']
    if not re.fullmatch('[a-f0-9]{32}', d.get('order_id','')) or d.get('merchant_order_id') != p['merchant_order_id'] or d.get('currency') != p['currency'] or decimal(d.get('amount')) != decimal(p['amount']) or method(d) != p['payment_method'] or not d.get('payment_address') or decimal(d.get('pay_amount')) == '0': raise ValueError('Binding mismatch')
    hpp(d)
    if a.get('snapshot'):
        s = a['snapshot']
        if d['order_id'] != s['order_id'] or d['payment_address'] != s['payment_address'] or decimal(d['pay_amount']) != decimal(s['pay_amount']): raise ValueError('Immutable instructions changed')
    if check_paid and d.get('status') == 'PAID' and decimal(d.get('received_amount')) != decimal(d['pay_amount']): raise ValueError('PAID amount requires review')
def verify(r, t):
    a = load(r)
    if not isinstance(t,str) or not hmac.compare_digest(a['token'],t) or not a.get('snapshot'): raise ValueError('Invalid capability')
    d = api('/api/v1/orders/query',urlencode({'order_id':a['snapshot']['order_id']})); bind(a,d)
    if d.get('status') == 'PAID' and not a['paid_verified']: a['paid_verified'] = True; save(r,a)
    return d
def safe(d):
    asset, network = method(d).split('_',1)
    return dict(status=d['status'],payAmount=str(d['pay_amount']),asset=asset,network=network,address=d['payment_address'],amount=str(d['amount']),currency=d['currency'],expiresAt=d['expires_at'],reference=d['merchant_order_id'])
def create(p):
    r = p.get('request_id'); f = filename(r)
    if p.get('payment_method') not in METHODS: raise ValueError('Invalid method')
    if f.exists():
        a = load(r)
        if a['payload']['payment_method'] != p['payment_method']: raise ValueError('Use original method')
    else:
        token = secrets.token_hex(24); q = '?'+urlencode({'r':r,'t':token})
        a = dict(token=token,paid_verified=False,payload=dict(request_id=r,merchant_order_id='demo-'+r,amount=AMOUNT,currency=CURRENCY,payment_method=p['payment_method'],unique_amount_direction=DIRECTION,notify_url=PUBLIC+'/callback'+q,redirect_url=PUBLIC+'/complete'+q)); save(r,a)
    d = api('/api/v1/orders/query',urlencode({'order_id':a['snapshot']['order_id']})) if a.get('snapshot') else api('/api/v1/orders/create',a['payload'])
    bind(a,d,bool(a.get('snapshot')))
    if not a.get('snapshot'): a['snapshot'] = d; save(r,a)
    return dict(success=True,request_id=r,checkout_url='/checkout?'+urlencode({'r':r,'t':a['token']}))
FILES = {'index.html','demo.js','demo.css','complete.js','onsite.js','onsite.css','qrcode.min.js'}
class Handler(BaseHTTPRequestHandler):
    def log_message(self, *_): pass  # Never log order capability URLs.
    def out(self, code, typ, data, extra=None):
        self.send_response(code)
        for k,v in {'Content-Type':typ,'Cache-Control':'no-store','Referrer-Policy':'no-referrer','X-Content-Type-Options':'nosniff','Content-Security-Policy':"default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'self'; base-uri 'none'",**(extra or {})}.items(): self.send_header(k,v)
        self.end_headers(); self.wfile.write(data.encode() if isinstance(data,str) else data)
    def jout(self, code, obj): self.out(code,'application/json; charset=utf-8',json.dumps(obj))
    def do_GET(self): self.handle_route()
    def do_POST(self): self.handle_route()
    def handle_route(self):
        with MUTEX:
            try:
                u = urlsplit(self.path); q = parse_qs(u.query); r,t = q.get('r',[''])[0],q.get('t',[''])[0]
                if self.command == 'GET' and (u.path == '/' or u.path.startswith('/assets/')):
                    name = 'index.html' if u.path == '/' else u.path[8:]
                    if name not in FILES: self.jout(404,dict(success=False,message='Not found')); return
                    typ = 'application/javascript' if name.endswith('.js') else 'text/css' if name.endswith('.css') else 'text/html; charset=utf-8'
                    self.out(200,typ,(ROOT/'assets'/name).read_bytes()); return
                if self.command == 'POST' and u.path == '/payments':
                    if not hmac.compare_digest(self.headers.get('Authorization',''),'Bearer '+ACCESS): self.jout(401,dict(success=False,message='Staging access token required')); return
                    n = int(self.headers.get('Content-Length','0'))
                    if not 0 < n <= 8192: raise ValueError('Invalid body length')
                    self.jout(200,create(json.loads(self.rfile.read(n)))); return
                if self.command == 'GET' and u.path == '/status': self.jout(200,dict(success=True,data=safe(verify(r,t)))); return
                if self.command == 'POST' and u.path == '/callback':
                    d = verify(r,t); self.out(200 if d['status']=='PAID' else 409,'text/plain','OK' if d['status']=='PAID' else 'Payment not confirmed'); return
                if self.command == 'GET' and u.path == '/checkout':
                    d = verify(r,t); mode = q.get('mode',['ON_SITE'])[0]; lang = q.get('lang',['en'])[0]
                    if lang not in LANGS: lang = 'en'
                    if mode not in ['ON_SITE','HPP']: raise ValueError('Invalid mode')
                    if mode == 'HPP': self.out(303,'text/plain','',{'Location':hpp(d)+'?'+urlencode({'lang':lang})}); return
                    qs = '?'+urlencode({'r':r,'t':t}); cfg = json.dumps(dict(poll='/status'+qs,complete='/complete'+qs)).replace('<','\\u003c')
                    self.out(200,'text/html; charset=utf-8',(ROOT/'assets/checkout.html').read_text().replace('{{CONFIG}}',cfg)); return
                if self.command == 'GET' and u.path == '/complete': verify(r,t); self.out(200,'text/html; charset=utf-8',(ROOT/'assets/complete.html').read_bytes()); return
                self.jout(404,dict(success=False,message='Not found'))
            except Exception as error:
                retryable=isinstance(error,QueryUnavailable)
                self.jout(503 if retryable else 409,dict(success=False,retryable=retryable,message='Unable to verify or recover payment. Keep the original request_id and payment method; check server configuration or review the saved order.'))
if __name__ == '__main__':
    print('MochiPay staging demo listening on loopback port '+env('DEMO_PORT','8080'))
    ThreadingHTTPServer(('127.0.0.1',int(env('DEMO_PORT','8080'))),Handler).serve_forever()
