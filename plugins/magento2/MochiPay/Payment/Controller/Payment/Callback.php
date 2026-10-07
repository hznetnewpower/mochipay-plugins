<?php
namespace MochiPay\Payment\Controller\Payment;
class Callback extends \Magento\Framework\App\Action\Action implements \Magento\Framework\App\CsrfAwareActionInterface,\Magento\Framework\App\Action\HttpPostActionInterface
{
 private $portable;private $raw;
 public function __construct(\Magento\Framework\App\Action\Context $context,\MochiPay\Payment\Model\Portable $portable,\Magento\Framework\Controller\Result\RawFactory $raw){parent::__construct($context);$this->portable=$portable;$this->raw=$raw;}
 public function createCsrfValidationException(\Magento\Framework\App\RequestInterface $request){return null;}
 public function validateForCsrf(\Magento\Framework\App\RequestInterface $request){return true;}
 public function execute()
 {
  try{$body=json_decode($this->getRequest()->getContent(),true);$system=is_array($body)&&isset($body['order_id'])?(string)$body['order_id']:'';
   if(!$system)throw new \RuntimeException('ORDER_ID_REQUIRED');$engine=$this->portable->engine();$row=$engine->find($system);
   if(!$row){$row=$this->portable->importLegacy($system);if(!$row)throw new \RuntimeException('ORDER_NOT_FOUND');}

   $id=(int)$row['local_id'];$o=$this->portable->order($id);$engine=$this->portable->engine($o->getStoreId());$portable=$this->portable;
   $data=$engine->check($id,$row['token'],function($d,$p)use($portable,$id){$portable->settle($id,$d,$p);});if($data['status']!=='PAID')throw new \RuntimeException('ORDER_NOT_PAID');
   return $this->raw->create()->setContents('OK');
  }catch(\Exception $e){return $this->raw->create()->setHttpResponseCode(409)->setContents($e->getMessage());}
 }
}
