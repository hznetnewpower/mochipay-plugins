<?php

defined('ABSPATH') || exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class MochiPay_WC_Blocks extends AbstractPaymentMethodType
{
    protected $name = 'mochipay';

    public function initialize()
    {
        $this->settings = get_option('woocommerce_mochipay_settings', array());
    }

    public function is_active()
    {
        return 'yes' === $this->get_setting('enabled', 'no') &&
            '' !== trim((string) $this->get_setting('api_base_url', '')) &&
            '' !== trim((string) $this->get_setting('api_key', '')) &&
            '' !== trim((string) $this->get_setting('api_secret', '')) &&
            !empty(mochipay_wc_enabled_payment_methods($this->settings));
    }

    public function get_payment_method_script_handles()
    {
        $handle = 'mochipay-wc-blocks';
        wp_register_script(
            $handle,
            MOCHIPAY_WC_URL . 'assets/js/blocks.js',
            array('wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities'),
            MOCHIPAY_WC_VERSION,
            true
        );
        return array($handle);
    }

    public function get_payment_method_data()
    {
        $all_options = mochipay_wc_payment_method_options();
        $payment_methods = array();

        foreach (mochipay_wc_enabled_payment_methods($this->settings) as $value) {
            if (isset($all_options[$value])) {
                $payment_methods[] = array(
                    'value' => $value,
                    'label' => $all_options[$value],
                );
            }
        }

        return array(
            'title' => $this->get_setting('title', __('Cryptocurrency', 'mochipay-for-woocommerce')),
            'description' => $this->get_setting(
                'description',
                __('Pay securely with cryptocurrency through MochiPay.', 'mochipay-for-woocommerce')
            ),
            'checkoutMode' => $this->get_setting('checkout_mode', 'onsite'),
            'paymentMethods' => $payment_methods,
            'supports' => array('products'),
        );
    }
}
