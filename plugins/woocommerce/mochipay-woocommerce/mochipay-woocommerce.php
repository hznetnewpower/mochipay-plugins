<?php
/**
 * Plugin Name: MochiPay for WooCommerce
 * Plugin URI: https://mochi.bz/
 * Description: Accept direct-to-wallet cryptocurrency payments with on-site payment dialogs or MochiPay hosted checkout.
 * Version: 1.5.2
 * Author: MochiPay
 * Author URI: https://mochi.bz/
 * Text Domain: mochipay-woocommerce
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * WC requires at least: 5.8
 * License: GPL-2.0-or-later
 */

defined('ABSPATH') || exit;

define('MOCHIPAY_WC_VERSION', '1.5.2');
define('MOCHIPAY_WC_FILE', __FILE__);
define('MOCHIPAY_WC_PATH', plugin_dir_path(__FILE__));
define('MOCHIPAY_WC_URL', plugin_dir_url(__FILE__));

function mochipay_wc_payment_method_options()
{
    return array(
        'USDT_TRC20' => __('USDT — TRON (TRC20)', 'mochipay-woocommerce'),
        'USDC_ERC20' => __('USDC — Ethereum (ERC20)', 'mochipay-woocommerce'),
        'BTC_BITCOIN' => __('BTC — Bitcoin', 'mochipay-woocommerce'),
        'ETH_ERC20' => __('ETH — Ethereum', 'mochipay-woocommerce'),
        'SOL_SOLANA' => __('SOL — Solana', 'mochipay-woocommerce'),
    );
}

function mochipay_wc_enabled_payment_methods($settings)
{
    $options = mochipay_wc_payment_method_options();
    $methods = isset($settings['enabled_payment_methods'])
        ? $settings['enabled_payment_methods'] : array();

    if (!is_array($methods)) {
        $methods = array_filter(array_map('trim', explode(',', (string) $methods)));
    }

    // Preserve the selected method when upgrading from version 1.0.0.
    if (empty($methods) && !empty($settings['payment_method'])) {
        $methods = array($settings['payment_method']);
    }

    if (empty($methods)) {
        $methods = array_keys($options);
    }

    $allowed = array();
    foreach ($methods as $method) {
        $method = strtoupper(trim((string) $method));
        if (isset($options[$method]) && !in_array($method, $allowed, true)) {
            $allowed[] = $method;
        }
    }

    // USDT on TRON is the system-wide preferred default whenever enabled.
    $preferred = array_search('USDT_TRC20', $allowed, true);
    if (false !== $preferred && 0 !== $preferred) {
        unset($allowed[$preferred]);
        array_unshift($allowed, 'USDT_TRC20');
        $allowed = array_values($allowed);
    }
    return $allowed;
}

add_action('before_woocommerce_init', function () {
    if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});

function mochipay_wc_missing_woocommerce_notice()
{
    echo '<div class="notice notice-error"><p>' .
        esc_html__('MochiPay for WooCommerce requires WooCommerce to be installed and active.', 'mochipay-woocommerce') .
        '</p></div>';
}

function mochipay_wc_initialize_gateway()
{
    if (!class_exists('WC_Payment_Gateway')) {
        add_action('admin_notices', 'mochipay_wc_missing_woocommerce_notice');
        return;
    }

    require_once MOCHIPAY_WC_PATH . 'includes/class-mochipay-payment-data.php';
    require_once MOCHIPAY_WC_PATH . 'includes/class-wc-gateway-mochipay.php';
    require_once MOCHIPAY_WC_PATH . 'includes/class-mochipay-onsite.php';
    MochiPay_Onsite::boot();

    add_filter('woocommerce_payment_gateways', function ($gateways) {
        $gateways[] = 'WC_Gateway_MochiPay';
        return $gateways;
    });
}
add_action('plugins_loaded', 'mochipay_wc_initialize_gateway', 11);

function mochipay_wc_register_blocks_support()
{
    if (!class_exists('Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType')) {
        return;
    }

    require_once MOCHIPAY_WC_PATH . 'includes/class-wc-mochipay-blocks.php';
    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function ($registry) {
            $registry->register(new WC_MochiPay_Blocks());
        }
    );
}
add_action('woocommerce_blocks_loaded', 'mochipay_wc_register_blocks_support');
