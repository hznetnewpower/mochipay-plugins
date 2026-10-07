<?php
namespace Opencart\Catalog\Model\Extension\Mochipay\Payment;
class Mochipay extends \Opencart\System\Engine\Model
{
 public function getMethods(array $address=array()): array
 {
  if(!$this->config->get('payment_mochipay_status') || $this->cart->hasSubscription())return array();
  $title=$this->config->get('payment_mochipay_title')?:'Cryptocurrency (MochiPay)';
  return array('code'=>'mochipay','name'=>$title,'option'=>array('mochipay'=>array('code'=>'mochipay.mochipay','name'=>$title)),'sort_order'=>(int)$this->config->get('payment_mochipay_sort_order'));
 }
}
