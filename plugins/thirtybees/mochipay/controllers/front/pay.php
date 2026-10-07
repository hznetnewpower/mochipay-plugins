<?php
class MochipayPayModuleFrontController extends ModuleFrontController
{
 public $ssl=true;
 public function postProcess(){
  $input=array_merge($_GET,$_POST);list($code,$headers,$body)=\MochiPayShared\Page::handle($this->module->service(),$input,$_SERVER['REQUEST_METHOD']??'GET',[$this->module,'settle']);http_response_code($code);foreach($headers as $k=>$v)header($k.': '.$v);echo $body;exit;
 }
}
