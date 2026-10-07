<?php
class MochipayValidationModuleFrontController extends ModuleFrontController
{
 public $ssl=true;
 public function postProcess(){
  $cart=$this->context->cart;
  if(($_SERVER['REQUEST_METHOD']??'')!=='POST'||!hash_equals(Tools::getToken(false),(string)Tools::getValue('mochipay_nonce'))||!$this->module->gatewayEnabled()||!$cart->id||!$this->context->customer->id||(int)$cart->id_customer!==(int)$this->context->customer->id){http_response_code(403);exit('Invalid checkout.');}
  try{
   $service=$this->module->service();$id='cart:'.$cart->id;
   $row=$service->store->locked('native:'.$id,function()use($cart,$service,$id){
    $customer=new Customer((int)$cart->id_customer);$currency=new Currency((int)$cart->id_currency);$total=$cart->getOrderTotal(true,Cart::BOTH);
    $orderId=(int)Db::getInstance()->getValue('SELECT id_order FROM '._DB_PREFIX_.'orders WHERE id_cart='.(int)$cart->id.' ORDER BY id_order');
    if(!$orderId){$this->module->validateOrder((int)$cart->id,(int)Configuration::get('PS_OS_MOCHIPAY_WAITING'),$total,$this->module->displayName,null,[],(int)$currency->id,false,$customer->secure_key);$orderId=(int)Db::getInstance()->getValue('SELECT id_order FROM '._DB_PREFIX_.'orders WHERE id_cart='.(int)$cart->id.' ORDER BY id_order');}
    $o=new Order($orderId);$orders=Db::getInstance()->executeS('SELECT id_order FROM '._DB_PREFIX_.'orders WHERE id_cart='.(int)$cart->id);
    if(!Validate::isLoadedObject($o)||$o->module!=='mochipay'||(int)$o->id_customer!==(int)$customer->id||count($orders)!==1||\MochiPayShared\Payment::decimal($this->module->orderAmount($o,$currency))!==\MochiPayShared\Payment::decimal(number_format(Tools::ps_round($total,$currency->getDisplayPrecision()),$currency->getDisplayPrecision(),'.','')))throw new RuntimeException('Split carts and changed totals require separate orders.');
    $endpoint=$this->context->link->getModuleLink('mochipay','pay',[],true);
    $return=$this->context->link->getPageLink('order-confirmation',true,null,['id_cart'=>$cart->id,'id_module'=>$this->module->id,'id_order'=>$orderId,'key'=>$customer->secure_key]);
    return $service->prepare($id,$this->module->orderAmount($o,$currency),strtoupper($currency->iso_code),$return,$endpoint,'thirty bees',['native_total'=>\MochiPayShared\Payment::decimal((string)$o->total_paid),'order_id'=>$orderId,'cart_id'=>(int)$cart->id,'product_type'=>$cart->isVirtualCart()?'DIGITAL_SERVICE':'PHYSICAL']);
   });Tools::redirect(\MochiPayShared\Payment::url($row,'view'));
  }catch(Throwable $e){http_response_code(409);exit('Unable to start payment. Return to checkout and continue the saved order. Do not pay twice.');}
 }
}
