<?php
namespace MochiPay\Sylius;
use MochiPayShared\Payment;
use MochiPayShared\Store;
class Support
{
 private $em,$payments,$router,$states;
 public function __construct(\Doctrine\ORM\EntityManagerInterface $em,$payments,\Symfony\Component\Routing\Generator\UrlGeneratorInterface $router,\Sylius\Abstraction\StateMachine\StateMachineInterface $states){require_once __DIR__.'/lib/bootstrap.php';$this->em=$em;$this->payments=$payments;$this->router=$router;$this->states=$states;}
 public function store(){return Store::doctrine($this->em->getConnection(),'mochipay_attempt');}
 public function payment($id){$p=$this->payments->find($id);if(!$p||!$p->getMethod()||$p->getMethod()->getGatewayConfig()->getFactoryName()!=='mochipay')throw new \RuntimeException('Invalid store payment.');return $p;}
 public function service($p){return new Payment($this->store(),$p->getMethod()->getGatewayConfig()->getConfig()+Payment::defaults());}
 public function amount($p){
  $digits=\Symfony\Component\Intl\Currencies::getFractionDigits($p->getCurrencyCode());
  $n=(string)$p->getAmount();if(!ctype_digit($n))throw new \RuntimeException('Invalid payment amount.');
  if($digits===0)return $n;$n=str_pad($n,$digits+1,'0',STR_PAD_LEFT);return substr($n,0,-$digits).'.'.substr($n,-$digits);
 }
 public function prepare($p,$after){
  if(!in_array($p->getState(),['new','processing'],true)||$p->getAmount()!==$p->getOrder()->getTotal()||$p->getCurrencyCode()!==$p->getOrder()->getCurrencyCode())throw new \RuntimeException('Order balance requires review.');
  $endpoint=$this->router->generate('mochipay_checkout',[],\Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);
  return $this->service($p)->prepare((string)$p->getId(),$this->amount($p),$p->getCurrencyCode(),$after,$endpoint,'Sylius',['order_id'=>$p->getOrder()->getId(),'gateway_id'=>$p->getMethod()->getId()]);
 }
 public function settle(array $row,array $remote){
  $this->em->wrapInTransaction(function()use($row,$remote){
   $p=$this->payment($row['local_id']);$this->em->refresh($p);$this->em->refresh($p->getOrder());
   if(Payment::decimal($this->amount($p))!==$row['amount']||$p->getCurrencyCode()!==$row['currency']||$p->getOrder()->getId()!==$row['extra']['order_id']||$p->getMethod()->getId()!==$row['extra']['gateway_id']||$p->getAmount()!==$p->getOrder()->getTotal())throw new \RuntimeException('Store order changed.');
   $d=$p->getDetails();if(isset($d['mochipay_remote'])){if($d['mochipay_remote']===$remote['order_id']&&$p->getState()==='completed')return;throw new \RuntimeException('Existing payment needs review.');}
   if(in_array($p->getOrder()->getState(),['cancelled','fulfilled'],true)||!$this->states->can($p,\Sylius\Component\Payment\PaymentTransitions::GRAPH,\Sylius\Component\Payment\PaymentTransitions::TRANSITION_COMPLETE))throw new \RuntimeException('Payment requires review.');
   $d['mochipay_remote']=$remote['order_id'];$p->setDetails($d);$this->states->apply($p,\Sylius\Component\Payment\PaymentTransitions::GRAPH,\Sylius\Component\Payment\PaymentTransitions::TRANSITION_COMPLETE);$this->em->flush();
  });
 }
}
