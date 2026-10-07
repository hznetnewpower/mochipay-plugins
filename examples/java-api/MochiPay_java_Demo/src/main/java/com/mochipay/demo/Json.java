package com.mochipay.demo;

import java.util.*;

/** Small strict JSON codec for this dependency-free demo; numbers keep their text.
 * In an existing application use its established JSON library with decimal handling.
 */
public final class Json {
    private record NumberText(String text) { }
    public static class JsonElement {
        final Object value;
        JsonElement(Object v){value=v;}
        public boolean isJsonNull(){return value==null;}
        public boolean isJsonPrimitive(){return value instanceof String||value instanceof Boolean||value instanceof NumberText;}
        public JsonElement getAsJsonPrimitive(){if(!isJsonPrimitive())throw new IllegalArgumentException("Not primitive");return this;}
        public boolean isBoolean(){return value instanceof Boolean;}
        public boolean getAsBoolean(){if(!(value instanceof Boolean))throw new IllegalArgumentException("Not boolean");return (Boolean)value;}
        public String getAsString(){if(value instanceof String s)return s;if(value instanceof NumberText n)return n.text;if(value instanceof Boolean b)return b.toString();throw new IllegalArgumentException("Not primitive");}
        public JsonObject getAsJsonObject(){if(this instanceof JsonObject j)return j;throw new IllegalArgumentException("Not object");}
        public String toString(){return encode(this);}
    }
    public static final class JsonObject extends JsonElement {
        final Map<String,JsonElement> map=new LinkedHashMap<>();
        public JsonObject(){super(null);}
        public boolean isJsonNull(){return false;}
        public JsonElement get(String key){return map.get(key);}
        public boolean has(String key){return map.containsKey(key);}
        public void add(String key,JsonElement value){map.put(key,value);}
        public void addProperty(String key,String value){add(key,new JsonElement(value));}
        public void addProperty(String key,boolean value){add(key,new JsonElement(value));}
        public JsonObject getAsJsonObject(String key){return get(key).getAsJsonObject();}
    }
    public static final class JsonParser {
        public static JsonElement parseString(String text){Parser p=new Parser(text);JsonElement result=p.read(0);p.space();if(p.pos!=text.length())throw new IllegalArgumentException("Trailing JSON");return result;}
    }
    private static String quote(String s){StringBuilder out=new StringBuilder("\"");for(char c:s.toCharArray()){switch(c){case '"'->out.append("\\\"");case '\\'->out.append("\\\\");case '\b'->out.append("\\b");case '\f'->out.append("\\f");case '\n'->out.append("\\n");case '\r'->out.append("\\r");case '\t'->out.append("\\t");default->{if(c<32)out.append(String.format("\\u%04x",(int)c));else out.append(c);}}}return out.append('"').toString();}
    private static String encode(JsonElement e){
        if(e instanceof JsonObject o){StringJoiner j=new StringJoiner(",","{","}");o.map.forEach((k,v)->j.add(quote(k)+":"+encode(v)));return j.toString();}
        Object v=e.value;if(v==null)return "null";if(v instanceof String s)return quote(s);if(v instanceof Boolean b)return b.toString();if(v instanceof NumberText n)return n.text;
        if(v instanceof List<?> list){StringJoiner j=new StringJoiner(",","[","]");for(Object item:list)j.add(encode((JsonElement)item));return j.toString();}throw new IllegalArgumentException("Unsupported JSON");
    }
    private static final class Parser {
        final String text;int pos=0;
        Parser(String s){text=Objects.requireNonNull(s);}
        void space(){while(pos<text.length()&&" \t\r\n".indexOf(text.charAt(pos))>=0)pos++;}
        char current(){if(pos>=text.length())throw new IllegalArgumentException("Incomplete JSON");return text.charAt(pos);}
        void expect(char c){space();if(current()!=c)throw new IllegalArgumentException("Unexpected JSON token");pos++;}
        String string(){expect('"');StringBuilder out=new StringBuilder();while(pos<text.length()){char c=text.charAt(pos++);if(c=='"')return out.toString();if(c<32)throw new IllegalArgumentException("Control in string");if(c!='\\'){out.append(c);continue;}if(pos>=text.length())throw new IllegalArgumentException("Incomplete escape");char esc=text.charAt(pos++);switch(esc){case '"','\\','/'->out.append(esc);case 'b'->out.append('\b');case 'f'->out.append('\f');case 'n'->out.append('\n');case 'r'->out.append('\r');case 't'->out.append('\t');case 'u'->{if(pos+4>text.length())throw new IllegalArgumentException("Incomplete unicode");String hex=text.substring(pos,pos+4);if(!hex.matches("[0-9a-fA-F]{4}"))throw new IllegalArgumentException("Invalid unicode");out.append((char)Integer.parseInt(hex,16));pos+=4;}default->throw new IllegalArgumentException("Invalid escape");}}throw new IllegalArgumentException("Unclosed string");}
        JsonElement read(int depth){if(depth>64)throw new IllegalArgumentException("JSON nesting limit");space();char c=current();
            if(c=='{'){pos++;JsonObject o=new JsonObject();space();if(current()=='}'){pos++;return o;}while(true){String k=string();expect(':');if(o.has(k))throw new IllegalArgumentException("Duplicate JSON key");o.add(k,read(depth+1));space();char sep=current();pos++;if(sep=='}')return o;if(sep!=',')throw new IllegalArgumentException("Invalid object separator");}}
            if(c=='['){pos++;List<JsonElement> a=new ArrayList<>();space();if(current()==']'){pos++;return new JsonElement(a);}while(true){a.add(read(depth+1));space();char sep=current();pos++;if(sep==']')return new JsonElement(a);if(sep!=',')throw new IllegalArgumentException("Invalid array separator");}}
            if(c=='"')return new JsonElement(string());for(String literal:new String[]{"true","false","null"})if(text.startsWith(literal,pos)){pos+=literal.length();return new JsonElement(literal.equals("null")?null:literal.equals("true"));}
            int start=pos;while(pos<text.length()&&"-+0123456789.eE".indexOf(text.charAt(pos))>=0)pos++;String number=text.substring(start,pos);if(!number.matches("-?(?:0|[1-9][0-9]*)(?:\\.[0-9]+)?(?:[eE][+-]?[0-9]+)?"))throw new IllegalArgumentException("Invalid JSON number");return new JsonElement(new NumberText(number));
        }
    }
}
