<?php
namespace Drupal\mochipay\PluginForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
class PaymentForm extends \Drupal\commerce_payment\PluginForm\PaymentOffsiteForm
{
    public function buildConfigurationForm(array $form,FormStateInterface $form_state)
    {
        $form=parent::buildConfigurationForm($form,$form_state);$payment=$this->entity;$order=$payment->getOrder();$gateway=$payment->getPaymentGateway();
        $service=\Drupal\mochipay\Support::service($gateway);
        if(\MochiPayShared\Payment::decimal($payment->getAmount()->getNumber())!==\MochiPayShared\Payment::decimal($order->getTotalPrice()->getNumber()))throw new \RuntimeException('Partial payments are not supported.');
        $endpoint=Url::fromRoute('mochipay.checkout',['commerce_payment_gateway'=>$gateway->id()],['absolute'=>true])->toString();
        $row=$service->prepare($gateway->id().':'.$order->id(),$payment->getAmount()->getNumber(),$payment->getAmount()->getCurrencyCode(),$form['#return_url'],$endpoint,'Drupal Commerce',['order_id'=>$order->id(),'gateway'=>$gateway->id()]);
        return $this->buildRedirectForm($form,$form_state,\MochiPayShared\Payment::url($row,'view'),[],'get');
    }
}
