<?php
namespace Plugin\MochiPay\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
class Config extends \Eccube\Controller\AbstractController
{
    private $support;
    public function __construct(\Plugin\MochiPay\Support $support){$this->support=$support;}
    /** @Route("/%eccube_admin_route%/mochipay/config", name="mochi_pay_admin_config", methods={"GET","POST"}) */
    public function index(Request $request)
    {
        $c=$this->support->config();$data=$c;$data['secret']='';$builder=$this->createFormBuilder($data);
        $builder->add('enabled',CheckboxType::class,['label'=>'Enabled','required'=>false]);
        foreach(['url'=>'MochiPay URL','key'=>'API Key'] as $k=>$label)$builder->add($k,TextType::class,['label'=>$label]);
        $builder->add('secret',PasswordType::class,['label'=>'API Secret (leave blank to keep saved)','required'=>false]);
        $builder->add('mode',ChoiceType::class,['label'=>'Payment Interface','choices'=>['ON_SITE'=>'ON_SITE','HPP'=>'HPP']]);
        $builder->add('direction',ChoiceType::class,['label'=>'Unique Amount Direction','choices'=>['UP'=>'UP','DOWN'=>'DOWN']]);
        $builder->add('methods',ChoiceType::class,['label'=>'Supported currencies and networks','multiple'=>true,'expanded'=>true,'choices'=>array_flip(\MochiPayShared\Payment::labels())]);
        $form=$builder->getForm();$form->handleRequest($request);
        if($form->isSubmitted()&&$form->isValid()){$v=$form->getData();if($v['secret']==='')$v['secret']=$c['secret'];if($v['enabled']){$check=new \MochiPayShared\Payment(\MochiPayShared\Store::doctrine($this->support->em->getConnection(),'mochipay_attempt'),$v);}$this->support->saveConfig($v);return $this->redirectToRoute('mochi_pay_admin_config');}
        return $this->render('@MochiPay/admin/config.twig',['form'=>$form->createView()]);
    }
}
