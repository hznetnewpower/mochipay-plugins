<?php
class ModelExtensionPaymentMochipay extends Model
{
    public function getMethod($address, $total)
    {
        $this->load->language('extension/payment/mochipay');
        if (!$this->config->get('payment_mochipay_status')) return array();
        return array('code' => 'mochipay', 'title' => $this->config->get('payment_mochipay_title') ?: $this->language->get('text_title'), 'terms' => '', 'sort_order' => (int) $this->config->get('payment_mochipay_sort_order'));
    }
}
