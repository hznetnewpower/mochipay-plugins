<?php
namespace MochiPay\Sylius\Action;
class Capture implements \Payum\Core\Action\ActionInterface
{
 private $support;
 public function __construct(\MochiPay\Sylius\Support $support){$this->support=$support;}
 public function execute($r):void{
  \Payum\Core\Exception\RequestNotSupportedException::assertSupports($this,$r);
  $p=$this->support->payment($r->getModel()['mp_payment_id']);$token=$r->getToken();if(!$token)throw new \RuntimeException('Use the native Sylius checkout.');
  $existing=$this->support->store()->get((string)$p->getId());
  if($existing&&$existing['system_id']){$v=$this->support->service($p)->check((string)$p->getId(),$existing['token'],[$this->support,'settle']);if($v['status']==='PAID'){$r->getModel()['mochipay_remote']=$existing['system_id'];return;}}
  $row=$this->support->prepare($p,$token->getAfterUrl());throw new \Payum\Core\Reply\HttpRedirect(\MochiPayShared\Payment::url($row,'view'));
 }
 public function supports($r):bool{return $r instanceof \Payum\Core\Request\Capture&&$r->getModel() instanceof \ArrayAccess&&isset($r->getModel()['mp_payment_id']);}
}
