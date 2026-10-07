import http from 'node:http';
import {createHmac,randomBytes,timingSafeEqual} from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import {fileURLToPath} from 'node:url';
const root=path.dirname(fileURLToPath(import.meta.url));
const env=(k,d='')=>process.env[k]??d;
const base=new URL(env('MOCHIPAY_BASE_URL','https://mochi.bz'));
const pub=new URL(env('APP_PUBLIC_URL','http://127.0.0.1:8080'));
function originOnly(u){if(u.username||u.password||u.pathname!=='/'||u.search||u.hash||!(u.protocol==='https:'||(u.protocol==='http:'&&['127.0.0.1','localhost'].includes(u.hostname))))throw Error('Configure an HTTPS origin (loopback HTTP only for local development)');}
originOnly(base);originOnly(pub);
const key=env('MOCHIPAY_API_KEY'),secret=env('MOCHIPAY_API_SECRET'),access=env('DEMO_ACCESS_TOKEN');
if(!key||!secret||access.length<32||access.startsWith('replace-'))throw Error('Set private API credentials and a random DEMO_ACCESS_TOKEN of 32+ characters');
const methods=['USDT_TRC20','USDC_ERC20','BTC_BITCOIN','ETH_ERC20','SOL_SOLANA'];
const langs=['en','zh','es','pt-br','fr','de','nl','fa','ru','ar'];
const store=path.resolve(env('DEMO_DATA_DIR',path.join(root,'private-data')));
fs.mkdirSync(store,{recursive:true,mode:0o700});
const lock=path.join(store,'.instance.lock');fs.writeFileSync(lock,String(process.pid),{flag:'wx',mode:0o600});
let cleaned=false;function cleanup(){if(!cleaned){cleaned=true;fs.unlinkSync(lock)}}
process.on('exit',cleanup);for(const s of ['SIGINT','SIGTERM'])process.on(s,()=>process.exit(0));
const equal=(a,b)=>{const x=Buffer.from(String(a??'')),y=Buffer.from(String(b??''));return x.length===y.length&&timingSafeEqual(x,y)};
export function decimal(v){const s=String(v??'');if(!/^\d{1,28}(?:\.\d{1,18})?$/.test(s))throw Error('Invalid decimal');const [a,b='']=s.split('.');return (a.replace(/^0+(?=\d)/,'')||'0')+(b.replace(/0+$/,'')?'.'+b.replace(/0+$/,''):'')}
if(decimal(env('DEMO_AMOUNT','10.00'))==='0')throw Error('Positive server amount required');
const currency=env('DEMO_CURRENCY','USD').toUpperCase(),direction=env('DEMO_DIRECTION','UP');
if(!/^[A-Z]{3,10}$/.test(currency)||!['UP','DOWN'].includes(direction))throw Error('Invalid currency/direction');
// Tokenize complete JSON strings before numbers, preserving upstream decimal bytes.
function lossless(s){return JSON.parse(s.replace(/"(?:\\.|[^"\\])*"|-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?/g,m=>m[0]==='"'?m:JSON.stringify(m)))}
export async function api(route,payload){const text=typeof payload==='string'?payload:JSON.stringify(payload),post=typeof payload!=='string';const u=new URL(route+(post?'':'?'+text),base);const signature=createHmac('sha256',secret).update(text,'utf8').digest('base64');const r=await fetch(u,{method:post?'POST':'GET',redirect:'error',headers:{'Content-Type':'application/json','X-Mochi-Key':key,'X-Mochi-Signature':signature},body:post?text:undefined,signal:AbortSignal.timeout(30000)});const raw=await r.text();if(raw.length>1048576)throw Error('Upstream response too large');const d=lossless(raw);if(!r.ok||d.success!==true)throw Error('Upstream payment request was not accepted; recover with the same request_id');return d}
const validId=r=>/^[A-Za-z0-9._:-]{1,64}$/.test(r??'');
function recordPath(r){if(!validId(r))throw Error('Invalid request_id');return path.join(store,Buffer.from(r).toString('hex')+'.json')}
function load(r){return JSON.parse(fs.readFileSync(recordPath(r),'utf8'))}
function save(r,a){const p=recordPath(r),t=p+'.'+randomBytes(8).toString('hex')+'.tmp';fs.writeFileSync(t,JSON.stringify(a),{mode:0o600});fs.renameSync(t,p)}
function hpp(d){const u=new URL(d.payment_url);if(u.origin!==base.origin||u.username||u.password||u.pathname!=='/pay/'+d.order_id||u.search||u.hash)throw Error('Invalid hosted payment URL');return u}
function method(d){return d.payment_method??(d.wallet_type+'_'+d.network)}
function bind(a,d,checkPaid=true){const p=a.payload;if(!/^[a-f0-9]{32}$/.test(d.order_id??'')||d.merchant_order_id!==p.merchant_order_id||d.currency!==p.currency||decimal(d.amount)!==decimal(p.amount)||method(d)!==p.payment_method||!d.payment_address||decimal(d.pay_amount)==='0')throw Error('Payment binding mismatch');hpp(d);if(a.snapshot){const s=a.snapshot;if(d.order_id!==s.order_id||d.payment_address!==s.payment_address||decimal(d.pay_amount)!==decimal(s.pay_amount))throw Error('Immutable payment instructions changed')}if(checkPaid&&d.status==='PAID'&&decimal(d.received_amount)!==decimal(d.pay_amount))throw Error('PAID amount requires review')}
async function verify(r,t){const a=load(r);if(!equal(a.token,t)||!a.snapshot)throw Error('Invalid payment capability');const d=await api('/api/v1/orders/query','order_id='+encodeURIComponent(a.snapshot.order_id));bind(a,d);if(d.status==='PAID'&&!a.paid_verified){a.paid_verified=true;save(r,a)}return d}
function safe(d){const parts=method(d).split('_');return {status:d.status,payAmount:String(d.pay_amount),asset:parts[0],network:parts.slice(1).join('_'),address:d.payment_address,amount:String(d.amount),currency:d.currency,expiresAt:d.expires_at,reference:d.merchant_order_id}}
async function create(p){if(!validId(p.request_id)||!methods.includes(p.payment_method))throw Error('Invalid request_id or method');const r=p.request_id,file=recordPath(r);let a;
 if(fs.existsSync(file)){a=load(r);if(a.payload.payment_method!==p.payment_method)throw Error('Use the original method for this saved request_id')}
 else{const token=randomBytes(24).toString('hex'),q='?r='+encodeURIComponent(r)+'&t='+token;a={token,payload:{request_id:r,merchant_order_id:'demo-'+r,amount:env('DEMO_AMOUNT','10.00'),currency,payment_method:p.payment_method,unique_amount_direction:direction,notify_url:new URL('/callback'+q,pub).href,redirect_url:new URL('/complete'+q,pub).href},paid_verified:false};save(r,a)}
 const d=a.snapshot?await api('/api/v1/orders/query','order_id='+encodeURIComponent(a.snapshot.order_id)):await api('/api/v1/orders/create',a.payload);bind(a,d,Boolean(a.snapshot));if(!a.snapshot){a.snapshot=d;save(r,a)}return {success:true,request_id:r,checkout_url:'/checkout?r='+encodeURIComponent(r)+'&t='+a.token};}
const staticNames=new Set(['index.html','checkout.html','complete.html','demo.js','demo.css','complete.js','onsite.js','onsite.css','qrcode.min.js']);
function output(res,status,type,body){res.writeHead(status,{'Content-Type':type,'Cache-Control':'no-store','Referrer-Policy':'no-referrer','X-Content-Type-Options':'nosniff','Content-Security-Policy':"default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'self'; base-uri 'none'"});res.end(body)}
const json=(res,status,d)=>output(res,status,'application/json; charset=utf-8',JSON.stringify(d));
async function body(req){let s='';for await(const b of req){s+=b;if(s.length>8192)throw Error('Request too large')}return JSON.parse(s)}
let queue=Promise.resolve();
async function handle(req,res){try{const u=new URL(req.url,'http://127.0.0.1'),r=u.searchParams.get('r'),t=u.searchParams.get('t');
 if(req.method==='GET'&&(u.pathname==='/'||u.pathname.startsWith('/assets/'))){const name=u.pathname==='/'?'index.html':u.pathname.slice(8);if(!staticNames.has(name)||['checkout.html','complete.html'].includes(name)){json(res,404,{success:false,message:'Not found'});return}output(res,200,name.endsWith('.js')?'application/javascript':name.endsWith('.css')?'text/css':'text/html; charset=utf-8',fs.readFileSync(path.join(root,'assets',name)));return}
 if(req.method==='POST'&&u.pathname==='/payments'){if(!equal(req.headers.authorization,'Bearer '+access)){json(res,401,{success:false,message:'Staging access token required'});return}json(res,200,await create(await body(req)));return}
 if(req.method==='GET'&&u.pathname==='/status'){json(res,200,{success:true,data:safe(await verify(r,t))});return}
 if(req.method==='POST'&&u.pathname==='/callback'){const d=await verify(r,t);output(res,d.status==='PAID'?200:409,'text/plain',d.status==='PAID'?'OK':'Payment not confirmed');return}
 if(req.method==='GET'&&u.pathname==='/checkout'){const d=await verify(r,t),mode=u.searchParams.get('mode')??'ON_SITE',lang=langs.includes(u.searchParams.get('lang'))?u.searchParams.get('lang'):'en';if(!['ON_SITE','HPP'].includes(mode))throw Error('Invalid checkout mode');if(mode==='HPP'){const dest=hpp(d);dest.searchParams.set('lang',lang);res.writeHead(303,{Location:dest.href,'Cache-Control':'no-store','Referrer-Policy':'no-referrer'});res.end();return}const q='?r='+encodeURIComponent(r)+'&t='+encodeURIComponent(t),config=JSON.stringify({poll:'/status'+q,complete:'/complete'+q}).replace(/</g,'\\u003c');output(res,200,'text/html; charset=utf-8',fs.readFileSync(path.join(root,'assets/checkout.html'),'utf8').replace('{{CONFIG}}',config));return}
 if(req.method==='GET'&&u.pathname==='/complete'){await verify(r,t);output(res,200,'text/html; charset=utf-8',fs.readFileSync(path.join(root,'assets/complete.html')));return}
 json(res,404,{success:false,message:'Not found'});
 }catch(e){json(res,409,{success:false,message:'Unable to verify or recover payment. Keep the original request_id and payment method; check server configuration or review the saved order.'})}}
http.createServer((req,res)=>{queue=queue.then(()=>handle(req,res)).catch(()=>{});}).listen(Number(env('DEMO_PORT','8080')),'127.0.0.1',()=>console.log('MochiPay staging demo listening on loopback port '+env('DEMO_PORT','8080')));
