<?php
namespace MochiPay\Sylius\Action;
class Status implements \Payum\Core\Action\ActionInterface
{
 private $support;
 public function __construct(\MochiPay\Sylius\Support $support){$this->support=$support;}
 public function execute($r):void{
  \Payum\Core\Exception\RequestNotSupportedException::assertSupports($this,$r);$d=$r->getModel();
  if(!isset($d['mp_payment_id'])){$r->markNew();return;}
  $p=$this->support->payment($d['mp_payment_id']);$row=$this->support->store()->get((string)$p->getId());
  if(!$row||!$row['system_id']){$r->markNew();return;}
  $v=$this->support->service($p)->check((string)$p->getId(),$row['token'],[$this->support,'settle']);
  if($v['status']==='PAID'){$d['mochipay_remote']=$row['system_id'];$r->markCaptured();}else $r->markPending();
 }
 public function supports($r):bool{return $r instanceof \Payum\Core\Request\GetStatusInterface&&$r->getModel() instanceof \ArrayAccess;}
}
