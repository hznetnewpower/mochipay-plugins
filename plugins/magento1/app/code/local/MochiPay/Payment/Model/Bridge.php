<?php
class MochiPay_Payment_Model_Bridge
{
 public function engine($store=null)
 {
  require_once dirname(__DIR__).'/portable/mochipay_client.php';require_once dirname(__DIR__).'/portable/mochipay_core.php';
  $resource=Mage::getSingleton('core/resource');$db=$resource->getConnection('core_write');
  $key=Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/mochipay/api_key',$store));$secret=Mage::helper('core')->decrypt(Mage::getStoreConfig('payment/mochipay/api_secret',$store));
  $client=new MochiPayClient(Mage::getStoreConfig('payment/mochipay/api_url',$store),$key,$secret);
  $engine=new MochiPayPortable($client,function($sql)use($db){$r=$db->query($sql);return preg_match('/^SELECT/i',$sql)?$r->fetchAll():array();},function($s)use($db){return substr($db->quote($s),1,-1);},$resource->getTableName('mochipay_attempt'));
  $engine->install();return $engine;
 }
 public function settle($id,$data,$payload)
 {
  $o=Mage::getModel('sales/order')->load($id);
  if(!$o->getId() || $o->getPayment()->getMethod()!=='mochipay' || strtoupper($o->getOrderCurrencyCode())!==strtoupper($payload['currency']) || MochiPayPortable::decimal($o->getGrandTotal())!==MochiPayPortable::decimal($payload['amount']))throw new RuntimeException('Store order mismatch.');
  if($o->isCanceled() || $o->getState()===Mage_Sales_Model_Order::STATE_CLOSED)throw new RuntimeException('Store order requires manual review.');
  if($o->getPayment()->getAdditionalInformation('mochipay_confirmed'))return;
  if((float)$o->getTotalPaid()>=(float)$o->getGrandTotal())return;
  if((float)$o->getTotalPaid()>0)throw new \RuntimeException('Partially paid store order requires manual review.');
  $tx=!empty($data['tx_hash'])?$data['tx_hash']:$data['order_id'];
  $o->getPayment()->setTransactionId($tx)->setIsTransactionClosed(true)->setCurrencyCode($o->getBaseCurrencyCode())->registerCaptureNotification($o->getBaseGrandTotal(),true);
  $o->getPayment()->setAdditionalInformation('mochipay_confirmed',true);$o->addStatusHistoryComment('MochiPay payment confirmed. Transaction: '.$tx);$o->save();
 }
}
