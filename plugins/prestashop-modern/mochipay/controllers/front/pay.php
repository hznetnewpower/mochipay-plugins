<?php
class MochipayPayModuleFrontController extends ModuleFrontController
{
 public $ssl=true;
 public function postProcess()
 {
  header('Cache-Control: no-store');header('Referrer-Policy: same-origin');header('X-Robots-Tag: noindex, nofollow');
  try{
   $service=$this->module->portable();$id=(int)Tools::getValue('id');$token=(string)Tools::getValue('token');$row=$service->get($id,$token);
   if(!$row||!$token||!hash_equals($row['token'],$token))throw new RuntimeException('Invalid payment link.');
   $module=$this->module;
   if(Tools::getValue('poll')){ $data=$service->check($id,$token,function($d,$p)use($module,$id){$module->settle($id,$d,$p);}); header('Content-Type: application/json');exit(MochiPayPortable::json(array('success'=>true,'data'=>$data))); }
   $order=new Order($id);
   $complete=$this->context->link->getPageLink('order-confirmation',true,null,array('id_cart'=>$order->id_cart,'id_module'=>$module->id,'id_order'=>$id,'key'=>$order->secure_key));
   $poll=$this->context->link->getModuleLink('mochipay','pay',array('id'=>$id,'token'=>$token,'poll'=>1),true);
   exit(MochiPayPortable::page($poll,$complete));
  }catch(Exception $e){http_response_code(400);if(Tools::getValue('poll')){header('Content-Type: application/json');exit(json_encode(array('success'=>false,'retryable'=>mochipay_query_retryable($e),'message'=>mochipay_query_retryable($e)?'Connection failed. Your order is saved. Please check again.':$e->getMessage())));}exit(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));}
 }
}
