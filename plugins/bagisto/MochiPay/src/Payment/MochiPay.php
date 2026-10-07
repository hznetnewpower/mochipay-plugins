<?php
namespace MochiPay\Bagisto\Payment;
class MochiPay extends \Webkul\Payment\Payment\Payment
{
    protected $code='mochipay';
    public function getRedirectUrl() { return route('mochipay.redirect'); }
    public function isAvailable()
    {
        $c=\MochiPay\Bagisto\Support::config();
        return $c['enabled'] && $c['key']!=='' && $c['secret']!=='';
    }
}
