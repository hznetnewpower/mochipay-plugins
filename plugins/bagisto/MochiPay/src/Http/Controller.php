<?php
namespace MochiPay\Bagisto\Http;
use MochiPay\Bagisto\Support;
use MochiPayShared\Payment;
use MochiPayShared\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Webkul\Checkout\Facades\Cart;
class Controller extends \Illuminate\Routing\Controller
{
    public function redirect()
    {
        $cart=Cart::getCart();
        if(!$cart){if(session('mochipay_resume'))return redirect()->to(session('mochipay_resume'));abort(409,'No saved checkout.');}
        if(!$cart->payment||$cart->payment->method!=='mochipay'||Cart::hasError())abort(409,'Invalid cart.');
        Cart::collectTotals();$cart=Cart::getCart();$service=Support::service();$id='cart:'.$cart->id;
        // Serialize duplicate redirect requests and commit order + attempt together.
        $row=$service->store->locked('native:'.$id,function()use($service,$id,$cart){return DB::transaction(function()use($service,$id,$cart){
            $row=$service->store->get($id);if($row){$service->authorized($id,$row['token']);if(Payment::decimal((string)$cart->grand_total)!==$row['amount']||$cart->cart_currency_code!==$row['currency'])throw new \RuntimeException('Cart total changed.');return $row;}
            $data=(new \Webkul\Sales\Transformers\OrderResource($cart))->jsonSerialize();
            $order=app(\Webkul\Sales\Repositories\OrderRepository::class)->create($data);
            $endpoint=route('mochipay.checkout');
            $return=$endpoint.'?'.http_build_query(['id'=>$id,'token'=>'MOCHIPAY_ATTEMPT_TOKEN','action'=>'done']);
            return $service->prepare($id,(string)$order->grand_total,$order->order_currency_code,$return,$endpoint,'Bagisto',['order_id'=>$order->id,'product_type'=>$cart->haveStockableItems()?'PHYSICAL':'DIGITAL_SERVICE']);
        });});
        $url=Payment::url($row,'view');session(['mochipay_resume'=>$url]);Cart::deActivateCart();
        return redirect()->to($url,303);
    }
    public function checkout(Request $request)
    {
        $service=Support::service();$input=array_merge($request->query(),$request->post());
        if(($input['action']??'')==='done'){
            $row=$service->authorized($input['id']??'',$input['token']??'');
            if(!$row['settled'])abort(409,'Payment is not verified.');
            session()->flash('order_id',$row['extra']['order_id']);return redirect()->route('shop.checkout.onepage.success');
        }
        list($code,$headers,$body)=Page::handle($service,$input,$request->method(),[Support::class,'settle']);
        return response($body,$code,$headers);
    }
}
