<?php
defined('ABSPATH') || exit;

final class MochiPay_Onsite
{
    public static function boot()
    {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue'));
        add_action('template_redirect', array(__CLASS__, 'payment_page'), 0);
        add_action('wp_ajax_mochipay_payment', array(__CLASS__, 'ajax'));
        add_action('wp_ajax_nopriv_mochipay_payment', array(__CLASS__, 'ajax'));
    }

    private static function gateway()
    {
        $all = WC()->payment_gateways()->payment_gateways();
        return isset($all['mochipay']) ? $all['mochipay'] : null;
    }

    public static function enqueue()
    {
        if (!is_checkout() && !isset($_GET['mochipay_pay'])) { return; }
        $gateway = self::gateway();
        if (!$gateway || ('onsite' !== $gateway->get_option('checkout_mode', 'onsite') && !isset($_GET['mochipay_pay']))) { return; }
        wp_enqueue_style('mochipay-onsite', MOCHIPAY_WC_URL . 'assets/css/onsite.css', array(), MOCHIPAY_WC_VERSION . '.ui78');
        wp_enqueue_script('mochipay-qr', MOCHIPAY_WC_URL . 'assets/js/qrcode.min.js', array(), MOCHIPAY_WC_VERSION, true);
        wp_enqueue_script('mochipay-onsite', MOCHIPAY_WC_URL . 'assets/js/onsite.js', array('jquery', 'mochipay-qr'), MOCHIPAY_WC_VERSION . '.ui78', true);
        wp_localize_script('mochipay-onsite', 'MochiPayOnsiteConfig', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mochipay_payment'),
        ));
    }

    private static function authorized_order($id, $key)
    {
        $order = wc_get_order(absint($id));
        if (!$order || !$key || !hash_equals($order->get_order_key(), (string) $key) ||
            'mochipay' !== $order->get_payment_method() || !$order->get_meta('_mochipay_order_id')) { return false; }
        return $order;
    }

    public static function payment_page()
    {
        if (!isset($_GET['mochipay_pay'])) { return; }
        $key = isset($_GET['key']) ? wc_clean(wp_unslash($_GET['key'])) : '';
        $order = self::authorized_order($_GET['mochipay_pay'], $key);
        if (!$order) { wp_die(esc_html__('Invalid payment link.', 'mochipay-woocommerce'), '', array('response' => 403)); }
        if ($order->is_paid()) { wp_safe_redirect($order->get_checkout_order_received_url()); exit; }
        nocache_headers();
        header('Referrer-Policy: same-origin');
        header('X-Robots-Tag: noindex, nofollow');
        self::enqueue();
        $launch = array('orderId' => $order->get_id(), 'key' => $key);
        wp_add_inline_script('mochipay-onsite', 'window.MochiPayOnsiteLaunch = ' . wp_json_encode($launch) . ';', 'before');
        get_header();
        echo '<main class="mp-payment-page"><h1>' . esc_html__('Complete your payment', 'mochipay-woocommerce') . '</h1>';
        echo '<p>' . esc_html__('Your order has been created. Reopen the payment dialog to view the same payment details.', 'mochipay-woocommerce') . '</p>';
        echo '<button type="button" class="button mp-reopen">' . esc_html__('Open payment dialog', 'mochipay-woocommerce') . '</button></main>';
        get_footer();
        exit;
    }

    public static function ajax()
    {
        nocache_headers();
        if (!check_ajax_referer('mochipay_payment', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Payment session expired. Reload the payment page.'), 403);
        }
        $id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $key = isset($_POST['key']) ? wc_clean(wp_unslash($_POST['key'])) : '';
        $order = self::authorized_order($id, $key);
        if (!$order) { wp_send_json_error(array('message' => 'Invalid payment link.'), 403); }
        $gateway = self::gateway();
        if (!$gateway) { wp_send_json_error(array('message' => 'Payment method unavailable.'), 503); }
        if ($order->is_paid()) {
            wp_send_json_success(array('status' => 'PAID', 'redirect' => $order->get_checkout_order_received_url()));
        }
        if ($order->has_status(array('cancelled', 'refunded', 'failed'))) {
            wp_send_json_error(array('message' => 'This order requires store review. Do not send another payment.'), 409);
        }
        $cache_key = 'mochipay_status_' . $id;
        $data = get_transient($cache_key);
        if (!is_array($data)) {
            $data = $gateway->verified_order($order);
            if (is_wp_error($data)) {
                wp_send_json_error(array('message' => $data->get_error_message()), 503);
            }
            set_transient($cache_key, $data, 8);
        }
        $expected = (string) $order->get_meta('_mochipay_merchant_order_id');
        if (!MochiPay_Payment_Data::matches($data, $order, $expected)) {
            delete_transient($cache_key);
            wp_send_json_error(array('message' => 'Payment details mismatch. Contact the store.'), 409);
        }
        if ('PAID' === strtoupper(isset($data['status']) ? (string) $data['status'] : '')) {
            // Serialize callback and polling settlement with the same atomic lock.
            $lock = 'mochipay_settle_' . $id;
            if (!add_option($lock, time(), '', false)) {
                wp_send_json_error(array('message' => 'Payment confirmation is in progress.'), 503);
            }
            try {
                $order = wc_get_order($id);
                if (!$order->is_paid() && !$order->has_status(array('cancelled', 'refunded', 'failed'))) {
                    $order->payment_complete(isset($data['tx_hash']) ? sanitize_text_field($data['tx_hash']) : '');
                    $order->add_order_note('MochiPay payment confirmed by authenticated API query.');
                }
                $paid = $order->is_paid();
            } finally { delete_option($lock); }
            if (!$paid) { wp_send_json_error(array('message' => 'Payment requires store review.'), 409); }
            delete_transient($cache_key);
            wp_send_json_success(array('status' => 'PAID', 'redirect' => $order->get_checkout_order_received_url()));
        }
        $view = MochiPay_Payment_Data::view($data, $order);
        if (is_wp_error($view)) { wp_send_json_error(array('message' => $view->get_error_message()), 503); }
        wp_send_json_success($view);
    }
}
