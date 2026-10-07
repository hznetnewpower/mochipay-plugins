<?php
namespace MochiPay\Payment\Controller\Payment;
class Complete extends \Magento\Framework\App\Action\Action implements \Magento\Framework\App\Action\HttpGetActionInterface
{
 private $portable;private $session;
 public function __construct(\Magento\Framework\App\Action\Context $context,\MochiPay\Payment\Model\Portable $portable,\Magento\Checkout\Model\Session $session){parent::__construct($context);$this->portable=$portable;$this->session=$session;}
 public function execute()
 {
  try{$id=(int)($this->getRequest()->getParam('id') ?: $this->getRequest()->getParam('order_id'));$token=(string)$this->getRequest()->getParam('token');$row=$this->portable->engine()->get($id);
   $o=$this->portable->order($id);
   if(!$token){$saved=$row ? json_decode($row['payload'],true) : array();if(isset($saved['redirect_url']))throw new \RuntimeException('Invalid payment link.');$key=(string)$this->getRequest()->getParam('key');if(!$key||!$o->getProtectCode()||!hash_equals((string)$o->getProtectCode(),$key))throw new \RuntimeException('Invalid return link.');if($row)$token=$row['token'];}
   if(!$row||!$token||!hash_equals($row['token'],$token))throw new \RuntimeException('Invalid payment link.');
   if(!$row['settled']){$engine=$this->portable->engine($o->getStoreId());$portable=$this->portable;$data=$engine->check($id,$token,function($d,$p)use($portable,$id){$portable->settle($id,$d,$p);});if($data['status']!=='PAID')throw new \RuntimeException('Payment is not confirmed.');}
$this->session->setLastOrderId($id)->setLastRealOrderId($o->getIncrementId())->setLastSuccessQuoteId($o->getQuoteId())->setLastQuoteId($o->getQuoteId());
   return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success');
  }catch(\Exception $e){return $this->resultRedirectFactory->create()->setPath('checkout/cart');}
 }
}
