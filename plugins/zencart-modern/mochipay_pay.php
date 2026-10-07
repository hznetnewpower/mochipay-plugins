<?php
require 'includes/application_top.php';
require_once DIR_FS_CATALOG . DIR_WS_CLASSES . 'mochipay_bridge.php';
header('Cache-Control: no-store'); header('Referrer-Policy: same-origin');header('X-Robots-Tag: noindex, nofollow');
try {
 $service=mochipay_service();$id=isset($_GET['id'])?(int)$_GET['id']:0;$token=isset($_GET['token'])?(string)$_GET['token']:'';
 $row=$service->get($id);if(!$row||!$token||!hash_equals($row['token'],$token))throw new RuntimeException('Invalid payment link.');
 $poll=zen_href_link('mochipay_pay.php','id='.$id.'&token='.$token.'&poll=1','SSL',true,false);
 if(isset($_GET['poll'])){ $data=$service->check($id,$token,function($d,$p)use($id){mochipay_settle($id,$d,$p);}); header('Content-Type: application/json');echo MochiPayPortable::json(array('success'=>true,'data'=>$data)); }
 else echo MochiPayPortable::page($poll,zen_href_link(FILENAME_ACCOUNT_HISTORY_INFO,'order_id='.$id,'SSL'));
} catch(Exception $e){http_response_code(400);if(isset($_GET['poll'])){header('Content-Type: application/json');echo json_encode(array('success'=>false,'message'=>$e->getMessage()));}else echo htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8');}
require DIR_WS_INCLUDES . 'application_bottom.php';
