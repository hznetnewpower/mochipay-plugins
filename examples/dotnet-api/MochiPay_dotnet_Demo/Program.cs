using System;
using System.IO;
using System.Net;
using System.Text;
using System.Text.RegularExpressions;
using System.Globalization;
using System.Security.Cryptography;
using Newtonsoft.Json;
using Newtonsoft.Json.Linq;

namespace MochiPayDemo
{
    public static class Program
    {
        static string Env(string name,string fallback="") { return Environment.GetEnvironmentVariable(name) ?? fallback; }
        static readonly string Root=Environment.CurrentDirectory;
        static readonly string Base=Env("MOCHIPAY_BASE_URL","https://mochi.bz").TrimEnd('/');
        static readonly string Public=Env("APP_PUBLIC_URL","http://127.0.0.1:8080").TrimEnd('/');
        static readonly string Key=Env("MOCHIPAY_API_KEY"),Secret=Env("MOCHIPAY_API_SECRET"),Access=Env("DEMO_ACCESS_TOKEN");
        static readonly string Store=Path.GetFullPath(Env("DEMO_DATA_DIR",Path.Combine(Root,"private-data")));
        static readonly string Amount=Env("DEMO_AMOUNT","10.00"),Currency=Env("DEMO_CURRENCY","USD").ToUpperInvariant(),Direction=Env("DEMO_DIRECTION","UP");
        static readonly string[] Methods={"USDT_TRC20","USDC_ERC20","BTC_BITCOIN","ETH_ERC20","SOL_SOLANA"};
        static readonly string[] Languages={"en","zh","es","pt-br","fr","de","nl","fa","ru","ar"};
        static string S(JToken d,string k) { var v=d[k];return v==null?"":v.Type==JTokenType.Float?((decimal)v).ToString(CultureInfo.InvariantCulture):v.ToString(); }
        static string Json(JToken d) { return d.ToString(Formatting.None); }
        static JObject Parse(string text) { using(var r=new JsonTextReader(new StringReader(text)) { FloatParseHandling=FloatParseHandling.Decimal,DateParseHandling=DateParseHandling.None }) return JObject.Load(r); }
        public static string Decimal(string value) { if(!Regex.IsMatch(value ?? "",@"\A\d{1,28}(?:\.\d{1,18})?\z"))throw new Exception("Invalid decimal");var parts=value.Split('.');var a=parts[0].TrimStart('0');var b=parts.Length>1?parts[1].TrimEnd('0'):"";return (a==""?"0":a)+(b==""?"":"."+b); }
        static bool Equal(string a,string b) { if(a==null||b==null||a.Length!=b.Length)return false;int v=0;for(int i=0;i<a.Length;i++)v|=a[i]^b[i];return v==0; }
        static string Random() { var b=new byte[24];using(var r=RandomNumberGenerator.Create())r.GetBytes(b);return BitConverter.ToString(b).Replace("-","").ToLowerInvariant(); }
        static string Origin(string s) { var u=new Uri(s);if(u.UserInfo!=""||u.AbsolutePath!="/"||u.Query!=""||u.Fragment!=""||!(u.Scheme=="https"||(u.Scheme=="http"&&(u.Host=="127.0.0.1"||u.Host=="localhost"))))throw new Exception("Configure an HTTPS origin; loopback HTTP only for local development");return u.GetLeftPart(UriPartial.Authority); }
        static JObject Api(string route,JObject payload,string query=null)
        {
            string text=payload==null?query:Json(payload),sig;using(var h=new HMACSHA256(Encoding.UTF8.GetBytes(Secret)))sig=Convert.ToBase64String(h.ComputeHash(Encoding.UTF8.GetBytes(text)));
            var req=WebRequest.CreateHttp(Base+route+(payload==null?"?"+text:""));
            req.Method=payload==null?"GET":"POST";req.AllowAutoRedirect=false;req.Timeout=30000;req.ReadWriteTimeout=30000;
            req.Headers["X-Mochi-Key"]=Key;req.Headers["X-Mochi-Signature"]=sig;
            if(payload!=null){req.ContentType="application/json";byte[] body=Encoding.UTF8.GetBytes(text);req.ContentLength=body.Length;using(var stream=req.GetRequestStream())stream.Write(body,0,body.Length);}
            using(var res=(HttpWebResponse)req.GetResponse())using(var reader=new StreamReader(res.GetResponseStream(),Encoding.UTF8))
            {
                string raw=reader.ReadToEnd();if(raw.Length>1048576)throw new Exception("Response too large");var d=Parse(raw);
                if(res.StatusCode!=HttpStatusCode.OK||d["success"]==null||d["success"].Type!=JTokenType.Boolean||!(bool)d["success"])throw new Exception("Upstream rejected payment");return d;
            }
        }

        static string FileFor(string r) { if(!Regex.IsMatch(r ?? "",@"\A[A-Za-z0-9._:-]{1,64}\z"))throw new Exception("Invalid request_id");return Path.Combine(Store,BitConverter.ToString(Encoding.UTF8.GetBytes(r)).Replace("-","").ToLowerInvariant()+".json"); }
        static JObject Load(string r) { return Parse(File.ReadAllText(FileFor(r))); }
        static void Save(string r,JObject a) { var p=FileFor(r);var t=p+"."+Random()+".tmp";using(var f=new FileStream(t,FileMode.CreateNew,FileAccess.Write,FileShare.None)){byte[] b=Encoding.UTF8.GetBytes(Json(a));f.Write(b,0,b.Length);f.Flush(true);}if(File.Exists(p))File.Replace(t,p,null);else File.Move(t,p); }
        static string Method(JObject d) { return S(d,"payment_method")!=""?S(d,"payment_method"):S(d,"wallet_type")+"_"+S(d,"network"); }
        static string Hpp(JObject d) { var u=new Uri(S(d,"payment_url"));if(u.GetLeftPart(UriPartial.Authority)!=Origin(Base)||u.UserInfo!=""||u.AbsolutePath!="/pay/"+S(d,"order_id")||u.Query!=""||u.Fragment!="")throw new Exception("Invalid hosted URL");return u.AbsoluteUri; }
        static void Bind(JObject a,JObject d,bool checkPaid=true)
        {
            var p=(JObject)a["payload"];
            if(!Regex.IsMatch(S(d,"order_id"),@"\A[a-f0-9]{32}\z")||S(d,"merchant_order_id")!=S(p,"merchant_order_id")||S(d,"currency")!=S(p,"currency")||Decimal(S(d,"amount"))!=Decimal(S(p,"amount"))||Method(d)!=S(p,"payment_method")||S(d,"payment_address")==""||Decimal(S(d,"pay_amount"))=="0")throw new Exception("Binding mismatch");Hpp(d);
            var s=a["snapshot"] as JObject;if(s!=null&&(S(d,"order_id")!=S(s,"order_id")||S(d,"payment_address")!=S(s,"payment_address")||Decimal(S(d,"pay_amount"))!=Decimal(S(s,"pay_amount"))))throw new Exception("Instructions changed");
            if(checkPaid&&S(d,"status")=="PAID"&&Decimal(S(d,"received_amount"))!=Decimal(S(d,"pay_amount")))throw new Exception("PAID amount requires review");
        }
        static JObject Verify(string r,string t)
        {
            var a=Load(r);if(!Equal(S(a,"token"),t)||a["snapshot"]==null)throw new Exception("Invalid capability");var d=Api("/api/v1/orders/query",null,"order_id="+Uri.EscapeDataString(S(a["snapshot"],"order_id")));Bind(a,d);
            if(S(d,"status")=="PAID"&&!(bool)a["paid_verified"]){a["paid_verified"]=true;Save(r,a);}return d;
        }
        static JObject Safe(JObject d) { var m=Method(d);var i=m.IndexOf('_');return new JObject {{"status",S(d,"status")},{"payAmount",S(d,"pay_amount")},{"asset",m.Substring(0,i)},{"network",m.Substring(i+1)},{"address",S(d,"payment_address")},{"amount",S(d,"amount")},{"currency",S(d,"currency")},{"expiresAt",S(d,"expires_at")},{"reference",S(d,"merchant_order_id")}}; }
        static string Q(string r,string t) { return "?r="+Uri.EscapeDataString(r)+"&t="+Uri.EscapeDataString(t); }
        static JObject Create(JObject p)
        {
            string r=S(p,"request_id"),file=FileFor(r);if(Array.IndexOf(Methods,S(p,"payment_method"))<0)throw new Exception("Invalid method");JObject a;
            if(File.Exists(file)){a=Load(r);if(S(a["payload"],"payment_method")!=S(p,"payment_method"))throw new Exception("Use original method");}
            else{string token=Random();a=new JObject {{"token",token},{"paid_verified",false},{"payload",new JObject {{"request_id",r},{"merchant_order_id","demo-"+r},{"amount",Amount},{"currency",Currency},{"payment_method",S(p,"payment_method")},{"unique_amount_direction",Direction},{"notify_url",Public+"/callback"+Q(r,token)},{"redirect_url",Public+"/complete"+Q(r,token)}}}};Save(r,a);}
            var d=a["snapshot"]==null?Api("/api/v1/orders/create",(JObject)a["payload"]):Api("/api/v1/orders/query",null,"order_id="+Uri.EscapeDataString(S(a["snapshot"],"order_id")));Bind(a,d,a["snapshot"]!=null);if(a["snapshot"]==null){a["snapshot"]=d;Save(r,a);}return new JObject {{"success",true},{"request_id",r},{"checkout_url","/checkout"+Q(r,S(a,"token"))}};
        }
        static void Out(HttpListenerResponse res,int code,string type,string text)
        {
            res.StatusCode=code;res.ContentType=type;res.Headers["Cache-Control"]="no-store";res.Headers["Referrer-Policy"]="no-referrer";res.Headers["X-Content-Type-Options"]="nosniff";res.Headers["Content-Security-Policy"]="default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'self'; base-uri 'none'";byte[] b=Encoding.UTF8.GetBytes(text);res.OutputStream.Write(b,0,b.Length);res.Close();
        }
        static void JOut(HttpListenerResponse res,int code,JObject d) { Out(res,code,"application/json; charset=utf-8",Json(d)); }
        static void Handle(HttpListenerContext c)
        {
            var req=c.Request;var res=c.Response;
            try
            {
                string route=req.Url.AbsolutePath,r=req.QueryString["r"],t=req.QueryString["t"];
                if(req.HttpMethod=="GET"&&(route=="/"||route.StartsWith("/assets/",StringComparison.Ordinal)))
                {
                    string name=route=="/"?"index.html":route.Substring(8);if(Array.IndexOf(new[]{"index.html","demo.js","demo.css","complete.js","onsite.js","onsite.css","qrcode.min.js"},name)<0){JOut(res,404,new JObject {{"success",false},{"message","Not found"}});return;}Out(res,200,name.EndsWith(".js")?"application/javascript":name.EndsWith(".css")?"text/css":"text/html; charset=utf-8",File.ReadAllText(Path.Combine(Root,"assets",name)));return;
                }
                if(req.HttpMethod=="POST"&&route=="/payments")
                {
                    if(!Equal(req.Headers["Authorization"],"Bearer "+Access)){JOut(res,401,new JObject {{"success",false},{"message","Staging access token required"}});return;}
                    if(req.ContentLength64<=0||req.ContentLength64>8192)throw new Exception("Invalid body length");string body;using(var sr=new StreamReader(req.InputStream,Encoding.UTF8))body=sr.ReadToEnd();if(body.Length>8192)throw new Exception("Request too large");JOut(res,200,Create(Parse(body)));return;
                }
                if(req.HttpMethod=="GET"&&route=="/status"){JOut(res,200,new JObject {{"success",true},{"data",Safe(Verify(r,t))}});return;}
                if(req.HttpMethod=="POST"&&route=="/callback"){var d=Verify(r,t);Out(res,S(d,"status")=="PAID"?200:409,"text/plain",S(d,"status")=="PAID"?"OK":"Payment not confirmed");return;}
                if(req.HttpMethod=="GET"&&route=="/checkout")
                {
                    var d=Verify(r,t);string mode=req.QueryString["mode"]??"ON_SITE",lang=req.QueryString["lang"]??"en";if(Array.IndexOf(Languages,lang)<0)lang="en";if(mode!="ON_SITE"&&mode!="HPP")throw new Exception("Invalid mode");if(mode=="HPP"){res.RedirectLocation=Hpp(d)+"?lang="+Uri.EscapeDataString(lang);Out(res,303,"text/plain","");return;}string q=Q(r,t),cfg=Json(new JObject {{"poll","/status"+q},{"complete","/complete"+q}}).Replace("<","\\u003c");Out(res,200,"text/html; charset=utf-8",File.ReadAllText(Path.Combine(Root,"assets/checkout.html")).Replace("{{CONFIG}}",cfg));return;
                }
                if(req.HttpMethod=="GET"&&route=="/complete"){Verify(r,t);Out(res,200,"text/html; charset=utf-8",File.ReadAllText(Path.Combine(Root,"assets/complete.html")));return;}
                JOut(res,404,new JObject {{"success",false},{"message","Not found"}});
            }
            catch { JOut(res,409,new JObject {{"success",false},{"message","Unable to verify or recover payment. Keep the original request_id and payment method; check server configuration or review the saved order."}}); }
        }
        public static void Main()
        {
            Origin(Base);Origin(Public);if(Key==""||Secret==""||Access.Length<32||Access.StartsWith("replace-")||Decimal(Amount)=="0"||!Regex.IsMatch(Currency,@"\A[A-Z]{3,10}\z")||(Direction!="UP"&&Direction!="DOWN"))throw new Exception("Set valid private credentials, staging token and server price");ServicePointManager.SecurityProtocol=SecurityProtocolType.Tls12;
            Directory.CreateDirectory(Store);string lp=Path.Combine(Store,".instance.lock");using(var l=new FileStream(lp,FileMode.CreateNew,FileAccess.Write,FileShare.None))l.WriteByte(1);
            bool cleaned=false;Action cleanup=()=>{if(!cleaned){cleaned=true;File.Delete(lp);}};AppDomain.CurrentDomain.ProcessExit+=(s,e)=>cleanup();
            using(var server=new HttpListener()){server.Prefixes.Add("http://127.0.0.1:"+Env("DEMO_PORT","8080")+"/");server.Start();Console.WriteLine("MochiPay staging demo listening on loopback");try{while(true)Handle(server.GetContext());}finally{cleanup();}}
        }
    }
}
