<?php
namespace MochiPay\Bagisto;
use MochiPayShared\Payment;
use MochiPayShared\Store;
class Support
{
    public static function config()
    {
        $defaults=Payment::defaults();$result=[];
        foreach(['enabled'=>'active','url'=>'url','key'=>'key','secret'=>'secret','mode'=>'mode','direction'=>'direction'] as $k=>$f) {
            $v=core()->getConfigData('sales.payment_methods.mochipay.'.$f);$result[$k]=$v===null?$defaults[$k]:$v;
        }
        $result['enabled']=(bool)$result['enabled'];$result['methods']=[];
        foreach(Payment::labels() as $code=>$label) { $v=core()->getConfigData('sales.payment_methods.mochipay.asset_'.$code);if($v===null||$v)$result['methods'][]=$code; }
        return $result;
    }
    public static function service()
    {
        return new Payment(Store::pdo(\Illuminate\Support\Facades\DB::connection()->getPdo(),\Illuminate\Support\Facades\DB::getTablePrefix().'mochipay_attempt'),self::config());
    }
    public static function settle(array $row,array $remote)
    {
        \Illuminate\Support\Facades\DB::transaction(function()use($row,$remote){
            $order=\Webkul\Sales\Models\Order::query()->where('id',$row['extra']['order_id'])->lockForUpdate()->firstOrFail();
            if(!$order->payment||$order->payment->method!=='mochipay'||Payment::decimal((string)$order->grand_total)!==$row['amount']||$order->order_currency_code!==$row['currency'])throw new \RuntimeException('Store order mismatch.');
            $extra=(array)($order->payment->additional??[]);
            if(isset($extra['mochipay_remote'])) {
                if($extra['mochipay_remote']!==$remote['order_id'])throw new \RuntimeException('Another payment is already recorded.');
                return;
            }
            if($order->status!=='pending')throw new \RuntimeException('Order requires review.');
            $extra['mochipay_remote']=$remote['order_id'];$order->payment->additional=$extra;$order->payment->save();
            app(\Webkul\Sales\Repositories\OrderRepository::class)->update(['status'=>'processing'],$order->id);
            if($order->canInvoice()){
                $items=[];foreach($order->items as $item)if($item->qty_to_invoice>0)$items[$item->id]=$item->qty_to_invoice;
                app(\Webkul\Sales\Repositories\InvoiceRepository::class)->create(['order_id'=>$order->id,'invoice'=>['items'=>$items]]);
            }
        });
    }
}
