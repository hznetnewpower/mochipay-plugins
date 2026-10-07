<?php declare(strict_types=1);
namespace MochiPay\Shopware;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
class MochiPay extends Plugin
{
    public const PAYMENT_ID='e5139dc555a344b184dc010a2b5d306c';
    public function install(InstallContext $context):void
    {
        require_once __DIR__.'/lib/bootstrap.php';
        \MochiPayShared\Store::doctrine($this->container->get('Doctrine\\DBAL\\Connection'),'mochipay_attempt')->install();
        $this->container->get('payment_method.repository')->upsert([['id'=>self::PAYMENT_ID,'name'=>'MochiPay','description'=>'Cryptocurrency payments: ON_SITE and HPP','handlerIdentifier'=>Handler::class,'pluginId'=>$context->getPlugin()->getId(),'active'=>false,'afterOrderEnabled'=>true]],$context->getContext());
    }
    public function update(\Shopware\Core\Framework\Plugin\Context\UpdateContext $context):void
    {
        $this->container->get('payment_method.repository')->update([['id'=>self::PAYMENT_ID,'handlerIdentifier'=>Handler::class,'afterOrderEnabled'=>true]],$context->getContext());
    }
    public function uninstall(UninstallContext $context):void
    {
        $this->container->get('payment_method.repository')->update([['id'=>self::PAYMENT_ID,'active'=>false]],$context->getContext());
        // Retain attempts and native payment history even when user data is removed.
        parent::uninstall($context);
    }
}
