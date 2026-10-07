<?php
namespace Plugin\MochiPay;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use MochiPayShared\Payment;
use MochiPayShared\Store;
class Support
{
    public $em,$router,$flow;
    public function __construct(EntityManagerInterface $em,UrlGeneratorInterface $router,\Eccube\Service\PurchaseFlow\PurchaseFlow $shoppingPurchaseFlow){require_once __DIR__.'/lib/bootstrap.php';$this->em=$em;$this->router=$router;$this->flow=$shoppingPurchaseFlow;}
    public function config(){return json_decode($this->em->getConnection()->fetchOne('SELECT config_text FROM mochipay_config WHERE id=1'),true)+Payment::defaults();}
    public function saveConfig(array $config){$this->em->getConnection()->executeStatement('UPDATE mochipay_config SET config_text=? WHERE id=1',[Payment::json($config)]);}
    public function service(){return new Payment(Store::doctrine($this->em->getConnection(),'mochipay_attempt'),$this->config());}
    public function prepare($order)
    {
        if(!$order->getPayment()||$order->getPayment()->getMethodClass()!==Service\Method::class)throw new \RuntimeException('Invalid order payment method.');
        $endpoint=$this->router->generate('mochipay_checkout',[],UrlGeneratorInterface::ABSOLUTE_URL);
        $return=$endpoint.'?'.http_build_query(['id'=>$order->getId(),'token'=>'MOCHIPAY_ATTEMPT_TOKEN','action'=>'done']);
        return $this->service()->prepare((string)$order->getId(),(string)$order->getPaymentTotal(),'JPY',$return,$endpoint,'EC-CUBE');
    }
    public function settle(array $row,array $remote)
    {
        $o=$this->em->getRepository(\Eccube\Entity\Order::class)->find($row['local_id']);
        if(!$o||!$o->getPayment()||$o->getPayment()->getMethodClass()!==Service\Method::class||Payment::decimal((string)$o->getPaymentTotal())!==$row['amount']||$row['currency']!=='JPY')throw new \RuntimeException('Store order changed.');
        if($o->getOrderStatus()->getId()===\Eccube\Entity\Master\OrderStatus::PAID&&$o->getPaymentDate())return;
        if($o->getOrderStatus()->getId()!==\Eccube\Entity\Master\OrderStatus::PENDING)throw new \RuntimeException('Order requires review.');
        $this->em->wrapInTransaction(function()use($o){$this->flow->commit($o,new \Eccube\Service\PurchaseFlow\PurchaseContext());$o->setOrderStatus($this->em->find(\Eccube\Entity\Master\OrderStatus::class,\Eccube\Entity\Master\OrderStatus::PAID));$o->setPaymentDate(new \DateTime());$this->em->flush();});
    }
}
