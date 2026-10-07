<?php
class MochipayCallbackModuleFrontController extends ModuleFrontController
{
 public $ssl=true;
 public function postProcess()
 {
  header('Content-Type: text/plain');
  try{$body=json_decode(file_get_contents('php://input'),true);$system=is_array($body)&&isset($body['order_id'])?(string)$body['order_id']:'';
   if(!$system)throw new RuntimeException('ORDER_ID_REQUIRED');$service=$this->module->portable();$row=$service->find($system);

   if(!$row){$m=Db::getInstance()->getRow('SELECT * FROM `'._DB_PREFIX_.'mochipay_order` WHERE mochipay_order_id="'.pSQL($system).'"');
    if(!$m)throw new RuntimeException('ORDER_NOT_FOUND');$o=new Order((int)$m['id_order']);$c=new Currency((int)$o->id_currency);
    $v=$this->module->client()->queryOrder($system);$row=$service->adopt((int)$o->id,array('merchant_order_id'=>$m['merchant_order_id'],'amount'=>(string)$o->total_paid,'currency'=>$c->iso_code,'payment_method'=>$m['payment_method']),$v);}
$id=(int)$row['local_id'];$module=$this->module;
   $data=$service->check($id,$row['token'],function($d,$p)use($module,$id){$module->settle($id,$d,$p);});
   if($data['status']!=='PAID')throw new RuntimeException('ORDER_NOT_PAID');exit('OK');
  }catch(Exception $e){http_response_code(409);exit($e->getMessage());}
 }
}
