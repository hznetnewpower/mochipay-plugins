<?php
require 'includes/application_top.php';
require_once DIR_FS_CATALOG . DIR_WS_CLASSES . 'mochipay_bridge.php';
header('Content-Type: text/plain; charset=utf-8');
try {
 $body=json_decode(file_get_contents('php://input'),true);$system=is_array($body)&&isset($body['order_id'])?(string)$body['order_id']:'';
 if(!$system)throw new RuntimeException('ORDER_ID_REQUIRED');
 $service=mochipay_service();$row=$service->find($system);

 if(!$row){
  $old=$db->Execute("SELECT * FROM ".DB_PREFIX."mochipay_order WHERE mochipay_order_id='".zen_db_input($system)."' LIMIT 1");
  if($old->EOF)throw new RuntimeException('ORDER_NOT_FOUND');$m=$old->fields;
  $o=$db->Execute('SELECT order_total,currency FROM '.TABLE_ORDERS.' WHERE orders_id='.(int)$m['orders_id']);if($o->EOF)throw new RuntimeException('ORDER_NOT_FOUND');
  $client=new MochiPayClient(MODULE_PAYMENT_MOCHIPAY_API_URL,MODULE_PAYMENT_MOCHIPAY_API_KEY,MODULE_PAYMENT_MOCHIPAY_API_SECRET);
  $data=$client->queryOrder($system);$payload=array('merchant_order_id'=>$m['merchant_order_id'],'amount'=>(string)$o->fields['order_total'],'currency'=>$o->fields['currency'],'payment_method'=>$m['payment_method']);
  $row=$service->adopt((int)$m['orders_id'],$payload,$data);
 }

 $id=(int)$row['local_id'];$data=$service->check($id,$row['token'],function($d,$p)use($id){mochipay_settle($id,$d,$p);});
 if($data['status']!=='PAID')throw new RuntimeException('ORDER_NOT_PAID');echo 'OK';
} catch(Exception $e){http_response_code(409);echo $e->getMessage();}
require DIR_WS_INCLUDES . 'application_bottom.php';
