<?php declare(strict_types=1);
namespace MochiPay\Shopware;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
class Handler extends AbstractPaymentHandler
{
    private $support;
    public function __construct(Support $support){$this->support=$support;}
    public function supports(PaymentHandlerType $type,string $paymentMethodId,Context $context):bool{return false;}
    public function pay(Request $request,PaymentTransactionStruct $transaction,Context $context,?Struct $validateStruct):?RedirectResponse
    {return new RedirectResponse($this->support->prepare($transaction->getOrderTransactionId(),(string)$transaction->getReturnUrl(),$context),303);}
    public function finalize(Request $request,PaymentTransactionStruct $transaction,Context $context):void
    {$this->support->finalize($transaction->getOrderTransactionId(),$context);}
}
