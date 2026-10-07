<?php
namespace MochiPay\Sylius\Action;
class Convert implements \Payum\Core\Action\ActionInterface
{
 public function execute($r):void{\Payum\Core\Exception\RequestNotSupportedException::assertSupports($this,$r);$r->setResult($r->getSource()->getDetails()+['mp_payment_id'=>(string)$r->getSource()->getId()]);}
 public function supports($r):bool{return $r instanceof \Payum\Core\Request\Convert&&$r->getSource() instanceof \Sylius\Component\Core\Model\PaymentInterface&&$r->getTo()==='array';}
}
