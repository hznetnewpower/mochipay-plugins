<?php
namespace MochiPay\Sylius\Form;
use Symfony\Component\Form\Extension\Core\Type as T;
class Configuration extends \Symfony\Component\Form\AbstractType
{
 public function buildForm(\Symfony\Component\Form\FormBuilderInterface $b,array $options):void{
  require_once dirname(__DIR__).'/lib/bootstrap.php';
  $b->add('enabled',T\CheckboxType::class,['label'=>'Enable MochiPay','required'=>false]);
  foreach(['url'=>'MochiPay URL','key'=>'API key','secret'=>'API secret'] as $key=>$label)$b->add($key,$key==='secret'?T\PasswordType::class:T\TextType::class,['label'=>$label,'required'=>true]);
  $b->add('mode',T\ChoiceType::class,['label'=>'Payment mode','choices'=>['ON_SITE'=>'ON_SITE','HPP'=>'HPP']])->add('direction',T\ChoiceType::class,['label'=>'Unique amount direction','choices'=>['UP'=>'UP','DOWN'=>'DOWN']])->add('methods',T\ChoiceType::class,['label'=>'Supported currencies','choices'=>array_flip(\MochiPayShared\Payment::labels()),'multiple'=>true,'expanded'=>true]);
  $b->addEventListener(\Symfony\Component\Form\FormEvents::PRE_SUBMIT,function($e){$d=$e->getData();$old=$e->getForm()->getData();if(($d['secret']??'')===''&&is_array($old))$d['secret']=$old['secret']??'';$e->setData($d);});
  $b->addEventListener(\Symfony\Component\Form\FormEvents::PRE_SET_DATA,function($e){$d=$e->getData();$e->setData((is_array($d)?$d:[])+\MochiPayShared\Payment::defaults());});
 }
}
