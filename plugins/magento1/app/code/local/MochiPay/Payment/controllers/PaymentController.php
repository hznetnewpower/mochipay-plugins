<?php
class MochiPay_Payment_PaymentController extends Mage_Core_Controller_Front_Action
{
 public function startAction()
 {
  $session=Mage::getSingleton('checkout/session');$o=Mage::getModel('sales/order')->load((int)$session->getLastOrderId());
  if(!$o->getId() || $o->getPayment()->getMethod()!=='mochipay')return $this->_redirect('checkout/cart');
  try{
   $store=(int)$o->getStoreId();if(!Mage::getStoreConfig('payment/mochipay/active',$store))throw new RuntimeException('MochiPay is disabled.');$method=$o->getPayment()->getAdditionalInformation('mochipay_payment_method');$gateway=Mage::getModel('mochipay/payment')->setStore($store);$allowed=$gateway->methods();if(!isset($allowed[$method]))throw new RuntimeException('Invalid payment method.');
   $a=$o->getShippingAddress()?:$o->getBillingAddress();$items=array();$physical=false;foreach($o->getAllVisibleItems()as $item){$items[]=array('name'=>$item->getName(),'quantity'=>$item->getQtyOrdered(),'sku'=>$item->getSku(),'total'=>(string)$item->getRowTotalInclTax());if(!$item->getIsVirtual())$physical=true;}
   $ref='M1-'.strtoupper(substr(md5(Mage::app()->getStore($store)->getBaseUrl()),0,8)).'-'.$o->getIncrementId();
   $payload=array('merchant_order_id'=>$ref,'amount'=>(string)$o->getGrandTotal(),'currency'=>$o->getOrderCurrencyCode(),'payment_method'=>$method,'unique_amount_direction'=>Mage::getStoreConfig('payment/mochipay/unique_direction',$store)==='DOWN'?'DOWN':'UP','source'=>'MAGENTO1','product_type'=>$physical?'PHYSICAL':'DIGITAL_SERVICE','description'=>'Magento order #'.$o->getIncrementId(),'product_info'=>json_encode(array('platform'=>'Magento 1','items'=>$items)),'customer_email'=>$o->getCustomerEmail(),'customer_phone'=>$a?$a->getTelephone():'','customer_ip'=>filter_var($o->getRemoteIp(),FILTER_VALIDATE_IP)?$o->getRemoteIp():'','first_name'=>$a?$a->getFirstname():'','last_name'=>$a?$a->getLastname():'','country'=>$a?$a->getCountryId():'','state'=>$a?$a->getRegion():'','city'=>$a?$a->getCity():'','address1'=>$a?implode(' ',(array)$a->getStreet()):'','postal_code'=>$a?$a->getPostcode():'','notify_url'=>Mage::getUrl('mochipay/payment/callback',array('_secure'=>true)),'redirect_url'=>Mage::getUrl('mochipay/payment/complete',array('_secure'=>true,'id'=>$o->getId(),'token'=>'MOCHIPAY_ATTEMPT_TOKEN')));
   $engine=Mage::getModel('mochipay/bridge')->engine($store);$row=$engine->begin((int)$o->getId(),$payload);$data=json_decode($row['snapshot'],true);
   $o->getPayment()->setAdditionalInformation('mochipay_order_id',$row['system_id']);$o->save();
   $url=Mage::getStoreConfig('payment/mochipay/checkout_mode',$store)==='HPP'?$data['payment_url']:Mage::getUrl('mochipay/payment/pay',array('_secure'=>true,'id'=>$o->getId(),'token'=>$row['token']));
   return $this->getResponse()->setRedirect($url);
  }catch(Exception $e){Mage::getSingleton('checkout/session')->addError('Unable to start payment. Contact the store before retrying.');Mage::log($e->getMessage(),null,'mochipay.log');return $this->_redirect('checkout/cart');}
 }
 public function payAction()
 {
  $poll=$this->getRequest()->getParam('poll');$r=$this->getResponse()->setHeader('Cache-Control','no-store',true)->setHeader('Referrer-Policy','same-origin',true)->setHeader('X-Robots-Tag','noindex, nofollow',true);
  try{
   $id=(int)$this->getRequest()->getParam('id');$token=(string)$this->getRequest()->getParam('token');$bridge=Mage::getModel('mochipay/bridge');$o=Mage::getModel('sales/order')->load($id);$engine=$bridge->engine($o->getStoreId());$row=$engine->get($id);
   if(!$row||!$token||!hash_equals($row['token'],$token))throw new RuntimeException('Invalid payment link.');
   if($poll){$data=$engine->check($id,$token,function($d,$p)use($bridge,$id){$bridge->settle($id,$d,$p);});return $r->setHeader('Content-Type','application/json',true)->setBody(json_encode(array('success'=>true,'data'=>$data)));}
   $url=Mage::getUrl('mochipay/payment/pay',array('_secure'=>true,'id'=>$id,'token'=>$token,'poll'=>1));$complete=Mage::getUrl('mochipay/payment/complete',array('_secure'=>true,'id'=>$id,'token'=>$token));return $r->setHeader('Content-Type','text/html; charset=utf-8',true)->setBody(MochiPayPortable::page($url,$complete));
  }catch(Exception $e){return $r->setHttpResponseCode(400)->setHeader('Content-Type',$poll?'application/json':'text/plain',true)->setBody($poll?json_encode(array('success'=>false,'message'=>$e->getMessage())):$e->getMessage());}
 }
 public function callbackAction()
 {
  try{
   $body=json_decode($this->getRequest()->getRawBody(),true);$system=is_array($body)&&isset($body['order_id'])?(string)$body['order_id']:'';if(!$system)throw new RuntimeException('ORDER_ID_REQUIRED');
   // Look up local order without choosing an API credential scope from callback data.
   $res=Mage::getSingleton('core/resource');$db=$res->getConnection('core_write');$row=$db->fetchRow('SELECT * FROM '.$res->getTableName('mochipay_attempt').' WHERE system_id=?',array($system));if(!$row)throw new RuntimeException('ORDER_NOT_FOUND');
   $id=(int)$row['local_id'];$o=Mage::getModel('sales/order')->load($id);$bridge=Mage::getModel('mochipay/bridge');$engine=$bridge->engine($o->getStoreId());$data=$engine->check($id,$row['token'],function($d,$p)use($bridge,$id){$bridge->settle($id,$d,$p);});if($data['status']!=='PAID')throw new RuntimeException('ORDER_NOT_PAID');return $this->getResponse()->setBody('OK');
  }catch(Exception $e){return $this->getResponse()->setHttpResponseCode(409)->setBody($e->getMessage());}
 }
 public function completeAction()
 {
  try{$id=(int)$this->getRequest()->getParam('id');$token=(string)$this->getRequest()->getParam('token');$o=Mage::getModel('sales/order')->load($id);$row=Mage::getModel('mochipay/bridge')->engine($o->getStoreId())->get($id);if(!$token){$saved=$row ? json_decode($row['payload'],true) : array();if(isset($saved['redirect_url']))throw new \RuntimeException('Invalid payment link.');$key=(string)$this->getRequest()->getParam('key');if(!$key||!$o->getProtectCode()||!hash_equals((string)$o->getProtectCode(),$key))throw new RuntimeException('Invalid return link.');if($row)$token=$row['token'];}
   if(!$row||!$token||!hash_equals($row['token'],$token))throw new RuntimeException('Invalid payment link.');
   if(!$row['settled']){$bridge=Mage::getModel('mochipay/bridge');$engine=$bridge->engine($o->getStoreId());$data=$engine->check($id,$token,function($d,$p)use($bridge,$id){$bridge->settle($id,$d,$p);});if($data['status']!=='PAID')throw new RuntimeException('Payment not confirmed.');}$s=Mage::getSingleton('checkout/session');$s->setLastOrderId($id)->setLastRealOrderId($o->getIncrementId())->setLastSuccessQuoteId($o->getQuoteId())->setLastQuoteId($o->getQuoteId());return $this->_redirect('checkout/onepage/success');}
  catch(Exception $e){return $this->_redirect('checkout/cart');}
 }
}
