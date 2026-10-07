<?php

namespace MochiPay\Payment\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class ConfigProvider implements ConfigProviderInterface
{
    private $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function getConfig()
    {
        $enabled = explode(',', (string) $this->scopeConfig->getValue('payment/mochipay/payment_methods', ScopeInterface::SCOPE_STORE));
        $methods = array();
        $labels = self::methodLabels();
        foreach ($enabled as $method) {
            $method = strtoupper(trim($method));
            if (isset($labels[$method])) $methods[] = array('value' => $method, 'label' => $labels[$method]);
        }
        if (!$methods) $methods[] = array('value' => 'USDT_TRC20', 'label' => $labels['USDT_TRC20']);
        return array('payment' => array('mochipay' => array(
            'title' => (string) $this->scopeConfig->getValue('payment/mochipay/title', ScopeInterface::SCOPE_STORE),
            'methods' => $methods,
        )));
    }

    public static function methodLabels()
    {
        return array(
            'USDT_TRC20' => 'USDT — TRON (TRC20)',
            'USDC_ERC20' => 'USDC — Ethereum (ERC20)',
            'BTC_BITCOIN' => 'BTC — Bitcoin',
            'ETH_ERC20' => 'ETH — Ethereum',
            'SOL_SOLANA' => 'SOL — Solana',
        );
    }
}
