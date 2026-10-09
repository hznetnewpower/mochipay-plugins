package com.mochipay.demo;

import com.mochipay.demo.Json.*;
import com.sun.net.httpserver.*;
import java.net.*;
import java.net.http.*;
import java.nio.charset.StandardCharsets;
import java.nio.file.*;
import java.nio.channels.FileChannel;
import java.nio.ByteBuffer;
import java.time.Duration;
import java.util.*;
import java.security.*;
import javax.crypto.Mac;
import javax.crypto.spec.SecretKeySpec;

public final class Main {
    static final class QueryUnavailable extends Exception {}
    static String env(String n,String d){return System.getenv().getOrDefault(n,d);}
    static final Path ROOT=Path.of("").toAbsolutePath();
    static final String BASE=env("MOCHIPAY_BASE_URL","https://mochi.bz").replaceAll("/$",""),PUBLIC=env("APP_PUBLIC_URL","http://127.0.0.1:8080").replaceAll("/$","");
    static final String KEY=env("MOCHIPAY_API_KEY",""),SECRET=env("MOCHIPAY_API_SECRET",""),ACCESS=env("DEMO_ACCESS_TOKEN","");
    static final String AMOUNT=env("DEMO_AMOUNT","10.00"),CURRENCY=env("DEMO_CURRENCY","USD").toUpperCase(Locale.ROOT),DIRECTION=env("DEMO_DIRECTION","UP");
    static final Path STORE=Path.of(env("DEMO_DATA_DIR",ROOT.resolve("private-data").toString())).toAbsolutePath();
    static final Set<String> METHODS=Set.of("USDT_TRC20","USDC_ERC20","BTC_BITCOIN","ETH_ERC20","SOL_SOLANA"),LANGS=Set.of("en","zh","es","pt-br","fr","de","nl","fa","ru","ar","ja","ko","it","tr","id");
    static final HttpClient CLIENT=HttpClient.newBuilder().followRedirects(HttpClient.Redirect.NEVER).connectTimeout(Duration.ofSeconds(30)).build();
    static String s(JsonObject d,String k){JsonElement v=d.get(k);return v==null||v.isJsonNull()?"":v.getAsString();}
    static JsonObject object(Object... kv){JsonObject d=new JsonObject();for(int i=0;i<kv.length;i+=2){String k=(String)kv[i];Object v=kv[i+1];if(v instanceof JsonElement j)d.add(k,j);else if(v instanceof Boolean b)d.addProperty(k,b);else d.addProperty(k,(String)v);}return d;}
    public static String decimal(String v){if(v==null||!v.matches("[0-9]{1,28}(?:\\.[0-9]{1,18})?"))throw new IllegalArgumentException("Invalid decimal");String[] p=v.split("\\.");String a=p[0].replaceFirst("^0+(?!$)",""),b=p.length>1?p[1].replaceFirst("0+$",""):"";return a+(b.isEmpty()?"":"."+b);}
    static boolean equal(String a,String b){return a!=null&&b!=null&&MessageDigest.isEqual(a.getBytes(StandardCharsets.UTF_8),b.getBytes(StandardCharsets.UTF_8));}
    static String random(){byte[] b=new byte[24];new SecureRandom().nextBytes(b);return HexFormat.of().formatHex(b);}
    static String enc(String s){return URLEncoder.encode(s,StandardCharsets.UTF_8).replace("+","%20");}
    static String origin(String value){URI u=URI.create(value);if(u.getUserInfo()!=null||!(u.getPath().isEmpty()||u.getPath().equals("/"))||u.getQuery()!=null||u.getFragment()!=null||!(u.getScheme().equals("https")||u.getScheme().equals("http")&&Set.of("127.0.0.1","localhost").contains(u.getHost())))throw new IllegalArgumentException("Configure an HTTPS origin; loopback HTTP only for local development");return u.getScheme()+"://"+u.getRawAuthority();}
    static JsonObject api(String route,JsonObject payload,String query)throws Exception{
        String text=payload==null?query:payload.toString();Mac mac=Mac.getInstance("HmacSHA256");mac.init(new SecretKeySpec(SECRET.getBytes(StandardCharsets.UTF_8),"HmacSHA256"));String sig=Base64.getEncoder().encodeToString(mac.doFinal(text.getBytes(StandardCharsets.UTF_8)));
        HttpRequest.Builder b=HttpRequest.newBuilder(URI.create(BASE+route+(payload==null?"?"+text:""))).timeout(Duration.ofSeconds(30)).header("Content-Type","application/json").header("X-Mochi-Key",KEY).header("X-Mochi-Signature",sig);
        if(payload==null)b.GET();else b.POST(HttpRequest.BodyPublishers.ofString(text,StandardCharsets.UTF_8));HttpResponse<String> res;try{res=CLIENT.send(b.build(),HttpResponse.BodyHandlers.ofString(StandardCharsets.UTF_8));}catch(java.io.IOException e){throw new QueryUnavailable();}if(res.statusCode()==408||res.statusCode()==429||res.statusCode()>=500)throw new QueryUnavailable();if(res.body().length()>1048576)throw new Exception("Upstream response too large");
        // The strict local JSON codec preserves numeric source text; never parse money as double.
        JsonObject d=JsonParser.parseString(res.body()).getAsJsonObject();JsonElement ok=d.get("success");if(res.statusCode()!=200||ok==null||!ok.isJsonPrimitive()||!ok.getAsJsonPrimitive().isBoolean()||!ok.getAsBoolean())throw new Exception("Upstream rejected payment");return d;
    }
    static Path filename(String r){if(r==null||!r.matches("[A-Za-z0-9._:-]{1,64}"))throw new IllegalArgumentException("Invalid request_id");return STORE.resolve(HexFormat.of().formatHex(r.getBytes(StandardCharsets.UTF_8))+".json");}
    static JsonObject load(String r)throws Exception{return JsonParser.parseString(Files.readString(filename(r))).getAsJsonObject();}
    static void save(String r,JsonObject a)throws Exception{Path p=filename(r),t=Path.of(p+"."+random()+".tmp");try(FileChannel f=FileChannel.open(t,StandardOpenOption.CREATE_NEW,StandardOpenOption.WRITE)){ByteBuffer b=ByteBuffer.wrap(a.toString().getBytes(StandardCharsets.UTF_8));while(b.hasRemaining())f.write(b);f.force(true);}Files.move(t,p,StandardCopyOption.ATOMIC_MOVE,StandardCopyOption.REPLACE_EXISTING);}
    static String method(JsonObject d){return s(d,"payment_method").isEmpty()?s(d,"wallet_type")+"_"+s(d,"network"):s(d,"payment_method");}
    static String hpp(JsonObject d){URI u=URI.create(s(d,"payment_url"));if(!(u.getScheme()+"://"+u.getRawAuthority()).equals(origin(BASE))||u.getUserInfo()!=null||!u.getRawPath().equals("/pay/"+s(d,"order_id"))||u.getQuery()!=null||u.getFragment()!=null)throw new IllegalArgumentException("Invalid hosted URL");return u.toString();}
    static void bind(JsonObject a,JsonObject d){bind(a,d,true);}
    static void bind(JsonObject a,JsonObject d,boolean checkPaid){JsonObject p=a.getAsJsonObject("payload");if(!s(d,"order_id").matches("[a-f0-9]{32}")||!s(d,"merchant_order_id").equals(s(p,"merchant_order_id"))||!s(d,"currency").equals(s(p,"currency"))||!decimal(s(d,"amount")).equals(decimal(s(p,"amount")))||!method(d).equals(s(p,"payment_method"))||s(d,"payment_address").isEmpty()||decimal(s(d,"pay_amount")).equals("0"))throw new IllegalArgumentException("Binding mismatch");hpp(d);
        if(a.has("snapshot")){JsonObject t=a.getAsJsonObject("snapshot");if(!s(d,"order_id").equals(s(t,"order_id"))||!s(d,"payment_address").equals(s(t,"payment_address"))||!decimal(s(d,"pay_amount")).equals(decimal(s(t,"pay_amount"))))throw new IllegalArgumentException("Instructions changed");}
        if(checkPaid&&s(d,"status").equals("PAID")&&!decimal(s(d,"received_amount")).equals(decimal(s(d,"pay_amount"))))throw new IllegalArgumentException("PAID amount requires review");
    }
    static JsonObject verify(String r,String t)throws Exception{JsonObject a=load(r);if(!equal(s(a,"token"),t)||!a.has("snapshot"))throw new IllegalArgumentException("Invalid capability");JsonObject d=api("/api/v1/orders/query",null,"order_id="+enc(s(a.getAsJsonObject("snapshot"),"order_id")));bind(a,d);if(s(d,"status").equals("PAID")&&!a.get("paid_verified").getAsBoolean()){a.addProperty("paid_verified",true);save(r,a);}return d;}
    static JsonObject safe(JsonObject d){String[] m=method(d).split("_",2);return object("status",s(d,"status"),"payAmount",s(d,"pay_amount"),"asset",m[0],"network",m[1],"address",s(d,"payment_address"),"amount",s(d,"amount"),"currency",s(d,"currency"),"expiresAt",s(d,"expires_at"),"reference",s(d,"merchant_order_id"));}
    static String q(String r,String t){return "?r="+enc(r)+"&t="+enc(t);}
    static JsonObject create(JsonObject p)throws Exception{String r=s(p,"request_id");Path f=filename(r);if(!METHODS.contains(s(p,"payment_method")))throw new IllegalArgumentException("Invalid method");JsonObject a;
        if(Files.exists(f)){a=load(r);if(!s(a.getAsJsonObject("payload"),"payment_method").equals(s(p,"payment_method")))throw new IllegalArgumentException("Use original method");}
        else{String token=random();a=object("token",token,"paid_verified",false,"payload",object("request_id",r,"merchant_order_id","demo-"+r,"amount",AMOUNT,"currency",CURRENCY,"payment_method",s(p,"payment_method"),"unique_amount_direction",DIRECTION,"notify_url",PUBLIC+"/callback"+q(r,token),"redirect_url",PUBLIC+"/complete"+q(r,token)));save(r,a);}
        JsonObject d=a.has("snapshot")?api("/api/v1/orders/query",null,"order_id="+enc(s(a.getAsJsonObject("snapshot"),"order_id"))):api("/api/v1/orders/create",a.getAsJsonObject("payload"),null);bind(a,d,a.has("snapshot"));if(!a.has("snapshot")){a.add("snapshot",d);save(r,a);}return object("success",true,"request_id",r,"checkout_url","/checkout"+q(r,s(a,"token")));
    }
    static void out(HttpExchange x,int code,String typ,String text)throws Exception{var h=x.getResponseHeaders();h.set("Content-Type",typ);h.set("Cache-Control","no-store");h.set("Referrer-Policy","no-referrer");h.set("X-Content-Type-Options","nosniff");h.set("Content-Security-Policy","default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'self'; base-uri 'none'");byte[] b=text.getBytes(StandardCharsets.UTF_8);x.sendResponseHeaders(code,b.length==0?-1:b.length);if(b.length>0)x.getResponseBody().write(b);x.close();}
    static void jout(HttpExchange x,int c,JsonObject d)throws Exception{out(x,c,"application/json; charset=utf-8",d.toString());}
    static Map<String,String> params(String query){Map<String,String> q=new HashMap<>();if(query!=null)for(String part:query.split("&")){String[] v=part.split("=",2);q.put(URLDecoder.decode(v[0],StandardCharsets.UTF_8),v.length==2?URLDecoder.decode(v[1],StandardCharsets.UTF_8):"");}return q;}
    static synchronized void handle(HttpExchange x)throws java.io.IOException{
        try{String route=x.getRequestURI().getPath(),verb=x.getRequestMethod();var qs=params(x.getRequestURI().getRawQuery());String r=qs.getOrDefault("r",""),t=qs.getOrDefault("t","");
            if(verb.equals("GET")&&(route.equals("/")||route.startsWith("/assets/"))){String name=route.equals("/")?"index.html":route.substring(8);if(!Set.of("index.html","demo.js","demo.css","complete.js","onsite.js","onsite.css","qrcode.min.js").contains(name)){jout(x,404,object("success",false,"message","Not found"));return;}out(x,200,name.endsWith(".js")?"application/javascript":name.endsWith(".css")?"text/css":"text/html; charset=utf-8",Files.readString(ROOT.resolve("assets").resolve(name)));return;}
            if(verb.equals("POST")&&route.equals("/payments")){if(!equal(x.getRequestHeaders().getFirst("Authorization"),"Bearer "+ACCESS)){jout(x,401,object("success",false,"message","Staging access token required"));return;}byte[] b=x.getRequestBody().readNBytes(8193);if(b.length>8192)throw new Exception("Request too large");jout(x,200,create(JsonParser.parseString(new String(b,StandardCharsets.UTF_8)).getAsJsonObject()));return;}
            if(verb.equals("GET")&&route.equals("/status")){jout(x,200,object("success",true,"data",safe(verify(r,t))));return;}
            if(verb.equals("POST")&&route.equals("/callback")){boolean paid=s(verify(r,t),"status").equals("PAID");out(x,paid?200:409,"text/plain",paid?"OK":"Payment not confirmed");return;}
            if(verb.equals("GET")&&route.equals("/checkout")){JsonObject d=verify(r,t);String mode=qs.getOrDefault("mode","ON_SITE"),lang=qs.getOrDefault("lang","en");if(!LANGS.contains(lang))lang="en";if(!Set.of("ON_SITE","HPP").contains(mode))throw new Exception("Invalid mode");if(mode.equals("HPP")){x.getResponseHeaders().set("Location",hpp(d)+"?lang="+enc(lang));out(x,303,"text/plain","");return;}String cfg=object("poll","/status"+q(r,t),"complete","/complete"+q(r,t)).toString().replace("<","\\u003c");out(x,200,"text/html; charset=utf-8",Files.readString(ROOT.resolve("assets/checkout.html")).replace("{{CONFIG}}",cfg));return;}
            if(verb.equals("GET")&&route.equals("/complete")){verify(r,t);out(x,200,"text/html; charset=utf-8",Files.readString(ROOT.resolve("assets/complete.html")));return;}
            jout(x,404,object("success",false,"message","Not found"));
        }catch(Exception e){try{boolean retryable=e instanceof QueryUnavailable;jout(x,retryable?503:409,object("success",false,"retryable",retryable,"message","Unable to verify or recover payment. Keep the original request_id and payment method; check server configuration or review the saved order."));}catch(Exception ignored){x.close();}}
    }
    public static void main(String[] args)throws Exception{origin(BASE);origin(PUBLIC);if(KEY.isEmpty()||SECRET.isEmpty()||ACCESS.length()<32||ACCESS.startsWith("replace-")||decimal(AMOUNT).equals("0")||!CURRENCY.matches("[A-Z]{3,10}")||!Set.of("UP","DOWN").contains(DIRECTION))throw new Exception("Set valid private credentials, staging token and price");Files.createDirectories(STORE);Path lock=STORE.resolve(".instance.lock");Files.writeString(lock,Long.toString(ProcessHandle.current().pid()),StandardOpenOption.CREATE_NEW);Runtime.getRuntime().addShutdownHook(new Thread(()->{try{Files.deleteIfExists(lock);}catch(Exception ignored){}}));HttpServer server=HttpServer.create(new InetSocketAddress("127.0.0.1",Integer.parseInt(env("DEMO_PORT","8080"))),0);server.createContext("/",Main::handle);server.start();System.out.println("MochiPay staging demo listening on loopback");}
}
