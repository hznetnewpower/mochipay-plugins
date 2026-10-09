<?php
require 'includes/application_top.php';
require_once DIR_FS_CATALOG . DIR_WS_CLASSES . 'mochipay_bridge.php';
header('Content-Type: text/plain; charset=utf-8');
try {
 $body=json_decode(file_get_contents('php://input'),true);$system=is_array($body)&&isset($body['order_id'])?(string)$body['order_id']:'';
 if(!$system)throw new RuntimeException('ORDER_ID_REQUIRED');
 $service=mochipay_service();$row=$service->find($system);

 if(!$row)throw new RuntimeException('ORDER_NOT_FOUND');

 $id=(int)$row['local_id'];$data=$service->check($id,$row['token'],function($d,$p)use($id){mochipay_settle($id,$d,$p);});
 if($data['status']!=='PAID')throw new RuntimeException('ORDER_NOT_PAID');echo 'OK';
} catch(Exception $e){http_response_code(409);echo $e->getMessage();}
require DIR_WS_INCLUDES . 'application_bottom.php';
