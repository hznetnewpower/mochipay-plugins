<?php declare(strict_types=1);
namespace MochiPay\Shopware;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
class Handler implements AsynchronousPaymentHandlerInterface
{
    private $support;
    public function __construct(Support $support){$this->support=$support;}
    public function pay(AsyncPaymentTransactionStruct $transaction,RequestDataBag $dataBag,SalesChannelContext $salesChannelContext):RedirectResponse
    {return new RedirectResponse($this->support->prepare($transaction->getOrderTransaction()->getId(),$transaction->getReturnUrl(),$salesChannelContext->getContext()),303);}
    public function finalize(AsyncPaymentTransactionStruct $transaction,Request $request,SalesChannelContext $salesChannelContext):void
    {$this->support->finalize($transaction->getOrderTransaction()->getId(),$salesChannelContext->getContext());}
}
