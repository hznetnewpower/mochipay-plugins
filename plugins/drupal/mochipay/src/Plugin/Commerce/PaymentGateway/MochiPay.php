<?php
namespace Drupal\mochipay\Plugin\Commerce\PaymentGateway;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Symfony\Component\HttpFoundation\Request;
use MochiPayShared\Payment;
use Drupal\commerce_payment\Annotation\CommercePaymentGateway;
/**
 * @CommercePaymentGateway(
 *   id = "mochipay",
 *   label = @Translation("MochiPay"),
 *   display_label = @Translation("MochiPay"),
 *   forms = {"offsite-payment" = "Drupal\mochipay\PluginForm\PaymentForm"},
 *   requires_billing_information = FALSE
 * )
 */
class MochiPay extends OffsitePaymentGatewayBase
{
    public function defaultConfiguration()
    {
        require_once dirname(__DIR__,4).'/lib/bootstrap.php';
        $c=Payment::defaults();$c['interface_mode']=$c['mode'];unset($c['mode']);
        return $c+['mode'=>'live']+parent::defaultConfiguration();
    }
    public function buildConfigurationForm(array $form,FormStateInterface $form_state)
    {
        $form=parent::buildConfigurationForm($form,$form_state);
        $form['enabled']=['#type'=>'checkbox','#title'=>'Enabled','#default_value'=>$this->configuration['enabled']];
        foreach(['url'=>'MochiPay URL','key'=>'API Key','secret'=>'API Secret'] as $name=>$title)$form[$name]=['#type'=>$name==='secret'?'password':'textfield','#title'=>$title,'#default_value'=>$name==='secret'?'':$this->configuration[$name],'#description'=>$name==='secret'?'Leave blank to keep the saved secret.':''];
        $form['interface_mode']=['#type'=>'select','#title'=>'Payment Interface','#options'=>['ON_SITE'=>'ON_SITE','HPP'=>'HPP'],'#default_value'=>$this->configuration['interface_mode']];
        $form['direction']=['#type'=>'select','#title'=>'Unique Amount Direction','#options'=>['UP'=>'UP','DOWN'=>'DOWN'],'#default_value'=>$this->configuration['direction']];
        $form['methods']=['#type'=>'checkboxes','#title'=>'Supported currencies and networks','#options'=>Payment::labels(),'#default_value'=>$this->configuration['methods']];
        return $form;
    }
    public function validateConfigurationForm(array &$form,FormStateInterface $form_state)
    {
        parent::validateConfigurationForm($form,$form_state);$v=$form_state->getValue($form['#parents']);
        $methods=array_values(array_filter($v['methods']??[]));if(!$methods)$form_state->setError($form['methods'],'Enable at least one currency and network.');
        if(!empty($v['enabled'])){
            try{new \MochiPayShared\Client(['url'=>$v['url'],'key'=>$v['key'],'secret'=>$v['secret']?:$this->configuration['secret']]);}
            catch(\Throwable $e){$form_state->setError($form['url'],'Enter a valid HTTPS MochiPay origin, API key and secret.');}
        }
    }
    public function submitConfigurationForm(array &$form,FormStateInterface $form_state)
    {
        parent::submitConfigurationForm($form,$form_state);$v=$form_state->getValue($form['#parents']);
        foreach(['enabled','url','key','interface_mode','direction'] as $name)$this->configuration[$name]=$v[$name];
        if($v['secret']!=='')$this->configuration['secret']=$v['secret'];
        $this->configuration['methods']=array_values(array_filter($v['methods']));
    }
    public function onReturn(OrderInterface $order,Request $request)
    {
        $service=\Drupal\mochipay\Support::service($this->parentEntity);$id=$this->parentEntity->id().':'.$order->id();
        $row=$service->store->get($id,(string)$request->query->get('mochipay_token',''));if(!$request->query->get('mochipay_token')||!$row)throw new PaymentGatewayException('Payment is not saved.');
        $view=$service->check($id,$row['token'],function($r,$d){\Drupal\mochipay\Support::settle($r,$d);});
        if($view['status']!=='PAID')throw new PaymentGatewayException('Payment is not confirmed.');
    }
    public function onNotify(Request $request)
    {
        return \Drupal\mochipay\Controller\Checkout::respond($this->parentEntity,$request);
    }
}
