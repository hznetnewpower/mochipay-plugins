<?php
namespace MochiPay\Payment\Model;
class Portable
{
 private $client;private $resource;private $orders;
 public function __construct(Client $client,\Magento\Framework\App\ResourceConnection $resource,\Magento\Sales\Api\OrderRepositoryInterface $orders){$this->client=$client;$this->resource=$resource;$this->orders=$orders;}
 public function engine($store=null)
 {
  require_once dirname(__DIR__).'/portable/mochipay_core.php';$db=$this->resource->getConnection();
  $engine=new \MochiPayPortable(new ScopedClient($this->client,$store),function($sql)use($db){$r=$db->query($sql);return preg_match('/^SELECT/i',$sql)?$r->fetchAll():array();},function($s)use($db){$q=$db->quote($s);return substr($q,1,-1);},$this->resource->getTableName('mochipay_attempt'));
  $engine->install();return $engine;
 }
 public function settle($id,$data,$payload)
 {
  $o=$this->orders->get($id);
  if(!$o->getId() || $o->getPayment()->getMethod()!=='mochipay' || strtoupper($o->getOrderCurrencyCode())!==strtoupper($payload['currency']) || \MochiPayPortable::decimal($o->getGrandTotal())!==\MochiPayPortable::decimal($payload['amount']))throw new \RuntimeException('Store order mismatch.');
  if($o->isCanceled() || $o->getState()===\Magento\Sales\Model\Order::STATE_CLOSED)throw new \RuntimeException('Store order requires manual review.');
  if($o->getPayment()->getAdditionalInformation('mochipay_confirmed'))return;
  if((float)$o->getTotalPaid()>=(float)$o->getGrandTotal())return;
  if((float)$o->getTotalPaid()>0)throw new \RuntimeException('Partially paid store order requires manual review.');
  $tx=!empty($data['tx_hash'])?$data['tx_hash']:$data['order_id'];
  $o->getPayment()->setTransactionId($tx)->setIsTransactionClosed(true)->setCurrencyCode($o->getBaseCurrencyCode())->registerCaptureNotification($o->getBaseGrandTotal(),true);
  $o->getPayment()->setAdditionalInformation('mochipay_confirmed',true);
  $o->addCommentToStatusHistory('MochiPay payment confirmed. Transaction: '.$tx);
  $this->orders->save($o);
 }
 public function importLegacy($system)
 {
  $db=$this->resource->getConnection();$table=$this->resource->getTableName('sales_order_payment');
  // Decode server-stored payment metadata; never select an order solely from callback merchant_order_id.
  $rows=$db->fetchAll('SELECT parent_id,additional_information FROM '.$table.' WHERE method=?',array('mochipay'));
  foreach($rows as $row){$info=json_decode($row['additional_information'],true);if(!is_array($info)){ $info=@unserialize($row['additional_information'],array('allowed_classes'=>false)); }
   if(is_array($info)&&isset($info['mochipay_order_id'])&&hash_equals((string)$info['mochipay_order_id'],(string)$system)){
    $o=$this->orders->get((int)$row['parent_id']);$payload=array('merchant_order_id'=>$info['mochipay_merchant_order_id'],'amount'=>(string)$o->getGrandTotal(),'currency'=>$o->getOrderCurrencyCode(),'payment_method'=>$info['mochipay_payment_method']);
    return $this->engine($o->getStoreId())->adopt((int)$o->getId(),$payload,$this->client->queryOrder($system,$o->getStoreId()));
   }
  }return null;
 }
 public function order($id){return $this->orders->get($id);}
}
