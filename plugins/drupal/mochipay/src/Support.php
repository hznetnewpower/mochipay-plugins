<?php
namespace Drupal\mochipay;
use MochiPayShared\Payment;
use MochiPayShared\Store;
class Support
{
    public static function service($gateway)
    {
        require_once dirname(__DIR__).'/lib/bootstrap.php';
        $db=\Drupal::database();$driver=$db->driver();
        $store=new Store(function($sql,$args)use($db){$params=[];$i=0;$sql=preg_replace_callback('/\?/',function()use(&$i,$args,&$params){$name=':mp'.$i;$params[$name]=$args[$i++];return $name;},$sql);$q=$db->query($sql,$params);return preg_match('/^SELECT/i',$sql)?array_map(function($row){return (array)$row;},$q->fetchAll()):[];},(method_exists($db,'getPrefix')?$db->getPrefix():$db->tablePrefix('mochipay_attempt')).'mochipay_attempt',$driver);
        $c=$gateway->getPlugin()->getConfiguration();$c['mode']=$c['interface_mode'];
        return new Payment($store,$c);
    }
    public static function settle(array $row,array $remote)
    {
        $id=$row['extra']['order_id'];$gatewayId=$row['extra']['gateway'];
        $storage=\Drupal::entityTypeManager()->getStorage('commerce_order');$storage->resetCache([$id]);$o=$storage->load($id);
        if(!$o||$o->get('payment_gateway')->target_id!==$gatewayId||Payment::decimal($o->getTotalPrice()->getNumber())!==$row['amount']||$o->getTotalPrice()->getCurrencyCode()!==$row['currency'])throw new \RuntimeException('Order changed.');
        $payments=\Drupal::entityTypeManager()->getStorage('commerce_payment');
        $existing=$payments->loadByProperties(['order_id'=>$id,'payment_gateway'=>$gatewayId,'remote_id'=>$remote['order_id']]);
        if($existing){foreach($existing as $p)if($p->getState()->getId()==='completed')return;throw new \RuntimeException('Existing payment needs review.');}
        if(in_array($o->getState()->getId(),['canceled','completed'],true)||Payment::decimal($o->getBalance()->getNumber())!==$row['amount'])throw new \RuntimeException('Order balance requires review.');
        $payments->create(['state'=>'completed','amount'=>$o->getTotalPrice(),'payment_gateway'=>$gatewayId,'order_id'=>$id,'remote_id'=>$remote['order_id'],'remote_state'=>'PAID'])->save();
    }
}
