<?php
namespace Plugin\MochiPay\Service;
use Eccube\Entity\Order;
use Symfony\Component\Form\FormInterface;
use Eccube\Service\Payment\PaymentResult;
use Eccube\Service\Payment\PaymentDispatcher;
class Method implements \Eccube\Service\Payment\PaymentMethodInterface
{
    private $order,$form,$support;
    public function __construct(\Plugin\MochiPay\Support $support){$this->support=$support;}
    public function setOrder(Order $Order){$this->order=$Order;return $this;}
    public function setFormType(FormInterface $form){$this->form=$form;return $this;}
    public function verify(){ $r=new PaymentResult();$r->setSuccess((bool)$this->support->config()['enabled']);if(!$r->isSuccess())$r->setErrors(['MochiPay is not enabled.']);return $r; }
    public function apply()
    {
        $service=$this->support->service();$id=(string)$this->order->getId();
        $row=$service->store->locked('native:'.$id,function()use($service,$id){
            return $this->support->em->wrapInTransaction(function()use($service,$id){
                $existing=$service->store->get($id);
                if($existing){$service->authorized($id,$existing['token']);if(\MochiPayShared\Payment::decimal((string)$this->order->getPaymentTotal())!==$existing['amount'])throw new \RuntimeException('Order total changed.');return $existing;}
                $this->order->setOrderStatus($this->support->em->find(\Eccube\Entity\Master\OrderStatus::class,\Eccube\Entity\Master\OrderStatus::PENDING));
                $this->support->flow->prepare($this->order,new \Eccube\Service\PurchaseFlow\PurchaseContext());
                $this->support->em->flush();return $this->support->prepare($this->order);
            });
        });
        $d=new PaymentDispatcher();$d->setResponse(new \Symfony\Component\HttpFoundation\RedirectResponse(\MochiPayShared\Payment::url($row,'view'),303));return $d;
    }
    public function checkout(){ $r=new PaymentResult();$r->setSuccess(false);$r->setErrors(['Continue the saved MochiPay payment.']);return $r; }
}
