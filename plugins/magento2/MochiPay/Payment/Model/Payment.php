<?php

namespace MochiPay\Payment\Model;

use Magento\Framework\DataObject;
use Magento\Payment\Model\Method\AbstractMethod;

class Payment extends AbstractMethod
{
    protected $_code = 'mochipay';
    protected $_isOffline = true;
    protected $_canAuthorize = false;
    protected $_canCapture = false;

    public function assignData(DataObject $data)
    {
        parent::assignData($data);
        $additional = $data->getData('additional_data');
        if (!is_array($additional)) $additional = array();
        $method = isset($additional['mochipay_payment_method']) ? strtoupper(trim($additional['mochipay_payment_method'])) : '';
        if ($method !== '') $this->getInfoInstance()->setAdditionalInformation('mochipay_payment_method', $method);
        return $this;
    }

    public function validate()
    {
        parent::validate();
        $method = (string) $this->getInfoInstance()->getAdditionalInformation('mochipay_payment_method');
        $allowed = array_intersect(array_keys(ConfigProvider::methodLabels()), array_map('trim',explode(',',(string)$this->getConfigData('payment_methods'))));
        if (!in_array($method, $allowed, true)) throw new \Magento\Framework\Exception\LocalizedException(__('Please select a MochiPay payment currency and network.'));
        return $this;
    }

    public function getOrderPlaceRedirectUrl()
    {
        return $this->_urlBuilder->getUrl('mochipay/payment/start', array('_secure' => true));
    }
}
