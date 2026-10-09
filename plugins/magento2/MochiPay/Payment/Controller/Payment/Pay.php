<?php
namespace MochiPay\Payment\Controller\Payment;
class Pay extends \Magento\Framework\App\Action\Action implements \Magento\Framework\App\Action\HttpGetActionInterface
{
 private $portable;private $raw;
 public function __construct(\Magento\Framework\App\Action\Context $context,\MochiPay\Payment\Model\Portable $portable,\Magento\Framework\Controller\Result\RawFactory $raw){parent::__construct($context);$this->portable=$portable;$this->raw=$raw;}
 public function execute()
 {
  $result=$this->raw->create()->setHeader('Cache-Control','no-store',true)->setHeader('Referrer-Policy','same-origin',true)->setHeader('X-Robots-Tag','noindex, nofollow',true);$poll=$this->getRequest()->getParam('poll');
  try{
   $id=(int)$this->getRequest()->getParam('id');$token=(string)$this->getRequest()->getParam('token');
   $engine=$this->portable->engine();$row=$engine->get($id,$token);if(!$row||!$token||!hash_equals($row['token'],$token))throw new \RuntimeException('Invalid payment link.');
   $order=$this->portable->order($id);$engine=$this->portable->engine($order->getStoreId());$portable=$this->portable;
   if($poll){$data=$engine->check($id,$token,function($d,$p)use($portable,$id){$portable->settle($id,$d,$p);});return $result->setHeader('Content-Type','application/json',true)->setContents(json_encode(array('success'=>true,'data'=>$data)));}
   $pollUrl=$this->_url->getUrl('mochipay/payment/pay',array('_secure'=>true,'id'=>$id,'token'=>$token,'poll'=>1));
   $complete=$this->_url->getUrl('mochipay/payment/complete',array('_secure'=>true,'id'=>$id,'token'=>$token));
   return $result->setHeader('Content-Type','text/html; charset=utf-8',true)->setContents(\MochiPayPortable::page($pollUrl,$complete));
  }catch(\Exception $e){return $result->setHttpResponseCode(400)->setHeader('Content-Type',$poll?'application/json':'text/plain',true)->setContents($poll?json_encode(array('success'=>false,'retryable'=>mochipay_query_retryable($e),'message'=>mochipay_query_retryable($e)?'Connection failed. Your order is saved. Please check again.':$e->getMessage())):$e->getMessage());}
 }
}
