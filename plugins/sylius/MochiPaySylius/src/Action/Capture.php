<?php
namespace MochiPay\Sylius\Action;
class Capture implements \Payum\Core\Action\ActionInterface
{
 private $support;
 public function __construct(\MochiPay\Sylius\Support $support){$this->support=$support;}
 public function execute($r):void{
  \Payum\Core\Exception\RequestNotSupportedException::assertSupports($this,$r);
  $p=$this->support->payment($r->getModel()['mp_payment_id']);$token=$r->getToken();if(!$token)throw new \RuntimeException('Use the native Sylius checkout.');
  $after=$token->getAfterUrl();$after.=(strpos($after,'?')===false?'?':'&').'mochipay_token=MOCHIPAY_ATTEMPT_TOKEN';$row=$this->support->prepare($p,$after);$details=$r->getModel();$details['mochipay_token']=$row['token'];$saved=$p->getDetails();$saved['mochipay_token']=$row['token'];$p->setDetails($saved);throw new \Payum\Core\Reply\HttpRedirect(\MochiPayShared\Payment::url($row,'view'));
 }
 public function supports($r):bool{return $r instanceof \Payum\Core\Request\Capture&&$r->getModel() instanceof \ArrayAccess&&isset($r->getModel()['mp_payment_id']);}
}
