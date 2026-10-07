<?php declare(strict_types=1);
namespace MochiPay\Shopware;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use MochiPayShared\Payment;
use MochiPayShared\Store;
class Support
{
    private $db,$orders,$settings,$states,$router;
    public function __construct(Connection $db,EntityRepository $orders,SystemConfigService $settings,OrderTransactionStateHandler $states,UrlGeneratorInterface $router){require_once __DIR__.'/lib/bootstrap.php';$this->db=$db;$this->orders=$orders;$this->settings=$settings;$this->states=$states;$this->router=$router;}
    public function transaction(string $id,Context $context)
    {
        $c=new Criteria([$id]);$c->addAssociation('order.currency');$c->addAssociation('stateMachineState');
        $t=$this->orders->search($c,$context)->first();
        if(!$t||$t->getPaymentMethodId()!==MochiPay::PAYMENT_ID)throw new \RuntimeException('Invalid store transaction.');
        return $t;
    }
    public function service(string $channel):Payment
    {
        $c=Payment::defaults();
        foreach(['enabled','url','key','secret','mode','direction'] as $key){$v=$this->settings->get('MochiPay.config.'.$key,$channel);if($v!==null)$c[$key]=$v;}
        $c['enabled']=(bool)$c['enabled'];$c['methods']=[];
        foreach(Payment::labels() as $code=>$label){$v=$this->settings->get('MochiPay.config.asset_'.$code,$channel);if($v===null||$v)$c['methods'][]=$code;}
        return new Payment(Store::doctrine($this->db,'mochipay_attempt'),$c);
    }
    public function prepare(string $id,string $returnUrl,Context $context):string
    {
        $t=$this->transaction($id,$context);$o=$t->getOrder();$channel=$o->getSalesChannelId();
        if($t->getStateMachineState()->getTechnicalName()!=='open')throw new \RuntimeException('Transaction requires review.');
        $endpoint=$this->router->generate('frontend.mochipay.checkout',[],UrlGeneratorInterface::ABSOLUTE_URL);
        $service=$this->service($channel);
        $row=$service->prepare($id,(string)$t->getAmount()->getTotalPrice(),$o->getCurrency()->getIsoCode(),$returnUrl,$endpoint,'Shopware',['channel'=>$channel]);
        return Payment::url($row,'view');
    }
    public function row(string $id)
    {
        return Store::doctrine($this->db,'mochipay_attempt')->get($id);
    }
    public function settle(array $row,array $remote):void
    {
        $context=Context::createDefaultContext();$t=$this->transaction($row['local_id'],$context);$o=$t->getOrder();
        if(Payment::decimal((string)$t->getAmount()->getTotalPrice())!==$row['amount']||$o->getCurrency()->getIsoCode()!==$row['currency']||$o->getSalesChannelId()!==$row['extra']['channel'])throw new \RuntimeException('Store total changed.');
        $state=$t->getStateMachineState()->getTechnicalName();
        if($state==='paid')return;
        if(!in_array($state,['open','in_progress'],true))throw new \RuntimeException('Transaction requires review.');
        $this->states->paid($t->getId(),$context);
    }
    public function finalize(string $id,Context $context):void
    {
        $t=$this->transaction($id,$context);$row=$this->row($id);
        if(!$row)throw new \RuntimeException('Payment is not saved.');
        $view=$this->service($t->getOrder()->getSalesChannelId())->check($id,$row['token'],[$this,'settle']);
        if($view['status']!=='PAID')throw new \RuntimeException('Payment is not confirmed.');
    }
}
