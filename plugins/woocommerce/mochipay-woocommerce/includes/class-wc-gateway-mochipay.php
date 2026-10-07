<?php

defined('ABSPATH') || exit;

class WC_Gateway_MochiPay extends WC_Payment_Gateway
{
    private $api_base_url;
    private $api_key;
    private $api_secret;
    private $enabled_payment_methods;
    private $unique_amount_direction;
    private $debug;
    private $logger;

    public function __construct()
    {
        $this->id = 'mochipay';
        $this->icon = '';
        $this->has_fields = true;
        $this->method_title = __('MochiPay', 'mochipay-woocommerce');
        $this->method_description = __('Direct-to-wallet cryptocurrency payments with on-site dialogs or hosted checkout.', 'mochipay-woocommerce');
        $this->supports = array('products');

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title', 'Cryptocurrency');
        $this->description = $this->get_option(
            'description',
            'Pay securely with cryptocurrency through MochiPay.'
        );
        $this->enabled = $this->get_option('enabled', 'no');
        $this->api_base_url = untrailingslashit(trim((string) $this->get_option('api_base_url', 'https://mochi.bz')));
        $this->api_key = trim((string) $this->get_option('api_key', ''));
        $this->api_secret = trim((string) $this->get_option('api_secret', ''));
        $this->enabled_payment_methods = mochipay_wc_enabled_payment_methods($this->settings);
        $this->unique_amount_direction = 'DOWN' === strtoupper((string) $this->get_option('unique_amount_direction', 'UP'))
            ? 'DOWN' : 'UP';
        $this->debug = 'yes' === $this->get_option('debug', 'no');
        $this->logger = wc_get_logger();

        add_action(
            'woocommerce_update_options_payment_gateways_' . $this->id,
            array($this, 'process_admin_options')
        );
        add_action('woocommerce_api_mochipay', array($this, 'handle_callback'));
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'mochipay-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable MochiPay payments', 'mochipay-woocommerce'),
                'default' => 'no',
            ),
            'title' => array(
                'title' => __('Checkout title', 'mochipay-woocommerce'),
                'type' => 'text',
                'default' => __('Cryptocurrency', 'mochipay-woocommerce'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Checkout description', 'mochipay-woocommerce'),
                'type' => 'textarea',
                'default' => __('Pay securely with cryptocurrency through MochiPay.', 'mochipay-woocommerce'),
            ),
            'checkout_mode' => array(
                'title' => __('Payment interface', 'mochipay-woocommerce'),
                'type' => 'select',
                'default' => 'onsite',
                'options' => array(
                    'hpp' => __('HPP — Redirect to MochiPay checkout', 'mochipay-woocommerce'),
                    'onsite' => __('On-site — Payment dialog', 'mochipay-woocommerce'),
                ),
                'description' => __('On-site mode displays the payment address, amount and QR code inside your store. New installations default to on-site. Saved settings are preserved.', 'mochipay-woocommerce'),
            ),
            'api_base_url' => array(
                'title' => __('MochiPay URL', 'mochipay-woocommerce'),
                'type' => 'url',
                'description' => __('Your MochiPay site address, without /api at the end.', 'mochipay-woocommerce'),
                'default' => 'https://mochi.bz',
            ),
            'api_key' => array(
                'title' => __('API key', 'mochipay-woocommerce'),
                'type' => 'text',
                'default' => '',
            ),
            'api_secret' => array(
                'title' => __('API secret', 'mochipay-woocommerce'),
                'type' => 'password',
                'default' => '',
            ),
            'enabled_payment_methods' => array(
                'title' => __('Customer payment choices', 'mochipay-woocommerce'),
                'type' => 'multiselect',
                'description' => __('Select the currencies and networks customers may choose during checkout.', 'mochipay-woocommerce'),
                'default' => array_keys(mochipay_wc_payment_method_options()),
                'options' => mochipay_wc_payment_method_options(),
                'class' => 'wc-enhanced-select',
                'css' => 'min-width: 350px;',
                'desc_tip' => true,
            ),
            'unique_amount_direction' => array(
                'title' => __('Unique amount direction', 'mochipay-woocommerce'),
                'type' => 'select',
                'description' => __('Choose how MochiPay adjusts a payable amount when the same exact amount is already reserved. The adjustment is capped by the MochiPay server setting.', 'mochipay-woocommerce'),
                'default' => 'UP',
                'options' => array(
                    'UP' => __('Increase slightly (UP)', 'mochipay-woocommerce'),
                    'DOWN' => __('Decrease slightly (DOWN)', 'mochipay-woocommerce'),
                ),
                'desc_tip' => true,
            ),
            'debug' => array(
                'title' => __('Debug log', 'mochipay-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Write MochiPay events to WooCommerce logs', 'mochipay-woocommerce'),
                'default' => 'no',
            ),
        );
    }

    public function is_available()
    {
        return parent::is_available() &&
            '' !== $this->api_base_url &&
            '' !== $this->api_key &&
            '' !== $this->api_secret &&
            !empty($this->enabled_payment_methods);
    }

    public function admin_options()
    {
        echo '<h2>' . esc_html($this->get_method_title()) . '</h2>';
        echo '<p>' . esc_html($this->get_method_description()) . '</p>';
        echo '<p><strong>' . esc_html__('Callback URL:', 'mochipay-woocommerce') . '</strong> <code>' .
            esc_html($this->get_callback_url()) . '</code></p>';
        echo '<p>' . esc_html__('Copy the API key and secret from your MochiPay merchant dashboard. The callback URL is submitted automatically with every order.', 'mochipay-woocommerce') . '</p>';
        echo '<table class="form-table">';
        $this->generate_settings_html();
        echo '</table>';
    }

    public function payment_fields()
    {
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }

        $options = $this->customer_payment_options();
        $selected = $this->selected_payment_method();
        woocommerce_form_field(
            'mochipay_payment_method',
            array(
                'type' => 'select',
                'label' => __('Payment currency and network', 'mochipay-woocommerce'),
                'required' => true,
                'class' => array('form-row-wide'),
                'input_class' => array('select'),
                'options' => $options,
            ),
            $selected
        );
    }

    public function validate_fields()
    {
        $selected = $this->selected_payment_method();
        if (!$this->is_allowed_payment_method($selected)) {
            wc_add_notice(
                __('Please select an available MochiPay payment currency and network.', 'mochipay-woocommerce'),
                'error'
            );
            return false;
        }
        return true;
    }

    public function process_payment($order_id)
    {
        $lock = 'mochipay_create_' . absint($order_id);
        // Atomic INSERT on the unique options key; never delete a concurrent lock.
        if (!add_option($lock, time(), '', false)) {
            wc_add_notice(__('A payment request is already in progress. Please reload the order payment page.', 'mochipay-woocommerce'), 'error');
            return array('result' => 'failure');
        }
        try {
            return $this->create_payment($order_id);
        } finally {
            delete_option($lock);
        }
    }

    public function payment_redirect($order)
    {
        if ('onsite' === $this->get_option('checkout_mode', 'onsite')) {
            return add_query_arg(array('mochipay_pay' => $order->get_id(), 'key' => $order->get_order_key()), wc_get_checkout_url());
        }
        return $order->get_meta('_mochipay_payment_url');
    }

    public function verified_order($order)
    {
        $id = (string) $order->get_meta('_mochipay_order_id');
        if (!$id) { return new WP_Error('mochipay_missing', 'Payment has not been created.'); }
        $data = $this->query_order($id);
        if (is_wp_error($data)) { return $data; }
        if (!MochiPay_Payment_Data::matches($data, $order, $this->merchant_order_id($order))) {
            return new WP_Error('mochipay_mismatch', 'Payment details no longer match this order. Contact the store.');
        }
        return $data;
    }

    private function create_payment($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            wc_add_notice(__('Unable to load your order. Please try again.', 'mochipay-woocommerce'), 'error');
            return array('result' => 'failure');
        }

        $payment_method = $this->selected_payment_method();
        if (!$this->is_allowed_payment_method($payment_method)) {
            wc_add_notice(
                __('The selected MochiPay payment currency is not available.', 'mochipay-woocommerce'),
                'error'
            );
            return array('result' => 'failure');
        }

        if ($order->is_paid()) {
            return array('result' => 'success', 'redirect' => $this->get_return_url($order));
        }
        if ($order->has_status(array('cancelled', 'refunded', 'failed'))) {
            wc_add_notice(__('This order cannot accept another payment. Please contact the store.', 'mochipay-woocommerce'), 'error');
            return array('result' => 'failure');
        }
        if ($order->get_meta('_mochipay_order_id')) {
            // Reopen the same attempt, including after refresh or a lost AJAX response.
            // Do not allocate another address or change the selected currency.
            return array('result' => 'success', 'redirect' => $this->payment_redirect($order));
        }
        if ($order->get_meta('_mochipay_create_uncertain')) {
            $query = 'merchant_order_id=' . rawurlencode($this->merchant_order_id($order));
            $recovered = $this->api_get('/api/v1/orders/query?' . $query, $query);
            if (!is_wp_error($recovered) && !empty($recovered['order_id']) && !empty($recovered['payment_url'])) {
                $order->update_meta_data('_mochipay_order_id', sanitize_text_field($recovered['order_id']));
                if (MochiPay_Payment_Data::matches($recovered, $order, $this->merchant_order_id($order))) {
                    $order->update_meta_data('_mochipay_payment_url', esc_url_raw($recovered['payment_url']));
                    $order->update_meta_data('_mochipay_payment_snapshot', $recovered);
                    $order->delete_meta_data('_mochipay_create_uncertain');
                    $order->add_order_note('MochiPay recovered the existing payment after an uncertain create response.');
                    $order->save();
                    return array('result' => 'success', 'redirect' => $this->payment_redirect($order));
                }
                $order->delete_meta_data('_mochipay_order_id');
            }
            wc_add_notice(__('The previous payment request needs verification. Contact the store before trying again.', 'mochipay-woocommerce'), 'error');
            return array('result' => 'failure');
        }
        $payload = $this->build_order_payload($order, $payment_method);
        $order->update_meta_data('_mochipay_create_uncertain', 'yes');
        $order->update_meta_data('_mochipay_payment_method', $payment_method);
        $order->update_meta_data('_mochipay_merchant_order_id', $payload['merchant_order_id']);
        $order->save();
        $result = $this->api_post('/api/v1/orders/create', $payload);
        if (is_wp_error($result)) {
            $error_data = $result->get_error_data();
            $http = is_array($error_data) && isset($error_data['status']) ? (int) $error_data['status'] : 0;
            if ($http >= 400 && $http < 500 && !in_array($http, array(408, 429), true)) {
                $order->delete_meta_data('_mochipay_create_uncertain');
                $order->save();
            }
            $this->log('error', 'Order creation failed', array(
                'wc_order_id' => $order->get_id(),
                'message' => $result->get_error_message(),
            ));
            wc_add_notice(
                __('Unable to start the MochiPay payment. Please try again or choose another payment method.', 'mochipay-woocommerce'),
                'error'
            );
            return array('result' => 'failure');
        }

        $system_order_id = isset($result['order_id']) ? sanitize_text_field($result['order_id']) : '';
        $payment_url = isset($result['payment_url']) ? esc_url_raw($result['payment_url']) : '';
        if ('' === $system_order_id || '' === $payment_url) {
            $this->log('error', 'Invalid create-order response', array('response' => $result));
            wc_add_notice(__('MochiPay returned an incomplete payment response.', 'mochipay-woocommerce'), 'error');
            return array('result' => 'failure');
        }

        $merchant_order_id = $payload['merchant_order_id'];
        $order->delete_meta_data('_mochipay_create_uncertain');
        $order->update_meta_data('_mochipay_order_id', $system_order_id);
        $order->update_meta_data('_mochipay_merchant_order_id', $merchant_order_id);
        $order->update_meta_data('_mochipay_payment_url', $payment_url);
        $order->update_meta_data('_mochipay_payment_method', $payment_method);
        $order->update_meta_data('_mochipay_payment_snapshot', $result);
        $order->add_order_note(sprintf(
            __('MochiPay payment created. MochiPay order: %1$s; payment method: %2$s', 'mochipay-woocommerce'),
            $system_order_id,
            $payment_method
        ));

        if ('PAID' === strtoupper(isset($result['status']) ? (string) $result['status'] : '')) {
            $verified = $this->verified_order($order);
            if (!is_wp_error($verified) && 'PAID' === strtoupper(isset($verified['status']) ? (string) $verified['status'] : '')) {
                $order->payment_complete(isset($verified['tx_hash']) ? sanitize_text_field($verified['tx_hash']) : '');
            }
        } elseif (!$order->has_status(array('pending', 'on-hold'))) {
            $order->update_status('pending', __('Awaiting MochiPay cryptocurrency payment.', 'mochipay-woocommerce'));
        }
        $order->save();

        if (WC()->cart) {
            WC()->cart->empty_cart();
        }

        $this->log('info', 'Order created', array(
            'wc_order_id' => $order->get_id(),
            'mochipay_order_id' => $system_order_id,
            'payment_method' => $payment_method,
            'unique_amount_direction' => $this->unique_amount_direction,
        ));

        return array(
            'result' => 'success',
            'redirect' => $order->is_paid() ? $this->get_return_url($order) : $this->payment_redirect($order),
        );
    }

    public function handle_callback()
    {
        $raw_body = file_get_contents('php://input');
        $payload = json_decode((string) $raw_body, true);
        if (!is_array($payload)) {
            $this->callback_response(400, 'INVALID_JSON');
        }

        $system_order_id = isset($payload['order_id']) ? sanitize_text_field($payload['order_id']) : '';
        $merchant_order_id = isset($payload['merchant_order_id']) ? sanitize_text_field($payload['merchant_order_id']) : '';
        if ('' === $system_order_id) {
            $this->callback_response(400, 'ORDER_ID_REQUIRED');
        }

        $verified = $this->query_order($system_order_id);
        if (is_wp_error($verified)) {
            $verification_error = sanitize_text_field($verified->get_error_message());
            $verification_data = $verified->get_error_data();
            $this->log('error', 'Callback verification failed', array(
                'mochipay_order_id' => $system_order_id,
                'message' => $verification_error,
                'upstream_status' => is_array($verification_data) && isset($verification_data['status'])
                    ? (int) $verification_data['status'] : 0,
                'upstream_response' => is_array($verification_data) && isset($verification_data['response'])
                    ? $verification_data['response'] : '',
            ));
            $this->callback_response(
                503,
                'VERIFICATION_FAILED: ' . ($verification_error ? $verification_error : 'UNKNOWN_ERROR')
            );
        }
        if ('PAID' !== strtoupper(isset($verified['status']) ? (string) $verified['status'] : '')) {
            $this->callback_response(409, 'ORDER_NOT_PAID');
        }

        $verified_merchant_order_id = isset($verified['merchant_order_id'])
            ? sanitize_text_field($verified['merchant_order_id']) : '';
        if ('' !== $merchant_order_id && !hash_equals($verified_merchant_order_id, $merchant_order_id)) {
            $this->callback_response(409, 'ORDER_MISMATCH');
        }

        $order = $this->find_wc_order($system_order_id, $verified_merchant_order_id);
        if (!$order) {
            $this->log('error', 'WooCommerce order not found for callback', array(
                'mochipay_order_id' => $system_order_id,
                'merchant_order_id' => $verified_merchant_order_id,
            ));
            $this->callback_response(404, 'WC_ORDER_NOT_FOUND');
        }

        if (!MochiPay_Payment_Data::matches($verified, $order, $this->merchant_order_id($order))) {
            $this->callback_response(409, 'PAYMENT_DETAILS_MISMATCH');
        }
        if (!$order->is_paid() && $order->has_status(array('cancelled', 'refunded', 'failed'))) {
            $this->callback_response(409, 'WC_ORDER_REQUIRES_REVIEW');
        }
        $lock = 'mochipay_settle_' . $order->get_id();
        if (!add_option($lock, time(), '', false)) {
            $this->callback_response(503, 'CONFIRMATION_IN_PROGRESS');
        }
        try {
            $order = wc_get_order($order->get_id());
            if (!$order->is_paid() && $order->has_status(array('cancelled', 'refunded', 'failed'))) {
                $settled = false;
            } else {
                if (!$order->is_paid()) {
                    $transaction_id = isset($verified['tx_hash']) ? sanitize_text_field($verified['tx_hash']) : '';
                    $order->payment_complete($transaction_id);
                    $order->add_order_note(sprintf(
                        __('MochiPay payment confirmed. MochiPay order: %1$s; transaction: %2$s', 'mochipay-woocommerce'),
                        $system_order_id,
                        $transaction_id ? $transaction_id : '—'
                    ));
                }
                $order->save();
                $settled = $order->is_paid();
            }
        } finally { delete_option($lock); }
        if (!$settled) { $this->callback_response(409, 'WC_ORDER_REQUIRES_REVIEW'); }

        $this->log('info', 'Callback processed', array(
            'wc_order_id' => $order->get_id(),
            'mochipay_order_id' => $system_order_id,
        ));
        $this->callback_response(200, 'OK');
    }

    private function customer_payment_options()
    {
        $all_options = mochipay_wc_payment_method_options();
        $options = array();

        foreach ($this->enabled_payment_methods as $payment_method) {
            if (isset($all_options[$payment_method])) {
                $options[$payment_method] = $all_options[$payment_method];
            }
        }

        return $options;
    }

    private function selected_payment_method()
    {
        $selected = '';
        if (isset($_POST['mochipay_payment_method'])) {
            $selected = strtoupper(trim((string) wc_clean(
                wp_unslash($_POST['mochipay_payment_method'])
            )));
        }

        if ('' === $selected && !empty($this->enabled_payment_methods)) {
            $selected = $this->enabled_payment_methods[0];
        }

        return $selected;
    }

    private function is_allowed_payment_method($payment_method)
    {
        return '' !== $payment_method &&
            in_array($payment_method, $this->enabled_payment_methods, true);
    }

    private function build_order_payload($order, $payment_method)
    {
        $billing = $order->get_address('billing');
        return array(
            'merchant_order_id' => $this->merchant_order_id($order),
            'amount' => (string) $order->get_total(),
            'currency' => strtoupper($order->get_currency()),
            'payment_method' => $payment_method,
            'source' => 'WOOCOMMERCE',
            'unique_amount_direction' => $this->unique_amount_direction,
            'product_type' => $this->order_product_type($order),
            'description' => sprintf(__('WooCommerce order #%s', 'mochipay-woocommerce'), $order->get_order_number()),
            'product_info' => wp_json_encode($this->get_product_summary($order), JSON_UNESCAPED_SLASHES),
            'customer_email' => $order->get_billing_email(),
            'customer_phone' => $order->get_billing_phone(),
            'first_name' => isset($billing['first_name']) ? $billing['first_name'] : '',
            'last_name' => isset($billing['last_name']) ? $billing['last_name'] : '',
            'company' => isset($billing['company']) ? $billing['company'] : '',
            'country' => isset($billing['country']) ? $billing['country'] : '',
            'state' => isset($billing['state']) ? $billing['state'] : '',
            'city' => isset($billing['city']) ? $billing['city'] : '',
            'address1' => isset($billing['address_1']) ? $billing['address_1'] : '',
            'address2' => isset($billing['address_2']) ? $billing['address_2'] : '',
            'postal_code' => isset($billing['postcode']) ? $billing['postcode'] : '',
            'customer_ip' => $this->customer_ip($order),
            'notify_url' => $this->get_callback_url(),
            'redirect_url' => $this->get_return_url($order),
        );
    }

    private function order_product_type($order)
    {
        foreach ($order->get_items('line_item') as $item) {
            $product = $item->get_product();
            if ($product && !$product->is_virtual()) {
                return 'PHYSICAL';
            }
        }

        return 'DIGITAL_SERVICE';
    }

    private function get_product_summary($order)
    {
        $items = array();
        foreach ($order->get_items() as $item) {
            $items[] = array(
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total' => $item->get_total(),
            );
        }
        return array(
            'platform' => 'WooCommerce',
            'wc_order_id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'items' => $items,
        );
    }

    private function merchant_order_id($order)
    {
        $store_hash = substr(md5(home_url('/')), 0, 8);
        return 'WC-' . strtoupper($store_hash) . '-' . $order->get_id();
    }

    private function customer_ip($order)
    {
        $ip = trim((string) $order->get_customer_ip_address());
        if ('' === $ip && class_exists('WC_Geolocation')) {
            $ip = trim((string) WC_Geolocation::get_ip_address());
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }

    private function get_callback_url()
    {
        return WC()->api_request_url('mochipay');
    }

    private function query_order($system_order_id)
    {
        $query = 'order_id=' . rawurlencode($system_order_id);
        return $this->api_get('/api/v1/orders/query?' . $query, $query);
    }

    private function api_post($path, $payload)
    {
        $body = wp_json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (false === $body) {
            return new WP_Error('mochipay_json', __('Unable to encode the payment request.', 'mochipay-woocommerce'));
        }
        return $this->api_request($path, 'POST', $body, $body);
    }

    private function api_get($path, $signing_text)
    {
        return $this->api_request($path, 'GET', '', $signing_text);
    }

    private function api_request($path, $method, $body, $signing_text)
    {
        if ('https' !== strtolower((string) parse_url($this->api_base_url, PHP_URL_SCHEME))) {
            return new WP_Error('mochipay_https', 'The MochiPay API URL must use HTTPS.');
        }
        $url = $this->api_base_url . $path;
        $signature = base64_encode(hash_hmac('sha256', $signing_text, $this->api_secret, true));
        $args = array(
            'method' => $method,
            'timeout' => 30,
            'redirection' => 0,
            'sslverify' => true,
            'headers' => array(
                'Accept' => 'application/json',
                'X-Mochi-Key' => $this->api_key,
                'X-Mochi-Signature' => $signature,
            ),
        );
        if ('POST' === $method) {
            $args['headers']['Content-Type'] = 'application/json; charset=utf-8';
            $args['body'] = $body;
        }

        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) {
            return $response;
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $response_body = (string) wp_remote_retrieve_body($response);
        // Preserve monetary JSON number lexemes instead of converting through floats.
        $decimal_body = preg_replace('/("(?:amount|pay_amount|base_pay_amount|received_amount|exchange_rate|unique_amount_delta|rate_markup_percent)"\\s*:\\s*)(-?[0-9]+(?:\\.[0-9]+)?(?:[eE][+-]?[0-9]+)?)/', '$1"$2"', $response_body);
        $decoded = json_decode($decimal_body, true);
        if ($status < 200 || $status >= 300 || !is_array($decoded) || empty($decoded['success'])) {
            $message = is_array($decoded) && !empty($decoded['message'])
                ? sanitize_text_field($decoded['message'])
                : 'HTTP_' . $status;
            $response_excerpt = sanitize_text_field(wp_strip_all_tags($response_body));
            if (function_exists('mb_substr')) {
                $response_excerpt = mb_substr($response_excerpt, 0, 500);
            } else {
                $response_excerpt = substr($response_excerpt, 0, 500);
            }
            return new WP_Error('mochipay_api', $message, array(
                'status' => $status,
                'response' => $response_excerpt,
            ));
        }
        return $decoded;
    }

    private function find_wc_order($system_order_id, $merchant_order_id)
    {
        // Use the exact deterministic reference before meta queries. This works
        // even on WooCommerce versions where HPOS ignores legacy meta_key args.
        if (preg_match('/^WC-[A-F0-9]{8}-([0-9]+)$/', $merchant_order_id, $matches)) {
            $candidate = wc_get_order((int) $matches[1]);
            if ($candidate && hash_equals($this->merchant_order_id($candidate), $merchant_order_id)) {
                return $candidate;
            }
        }
        $orders = wc_get_orders(array(
            'limit' => 1,
            'return' => 'objects',
            'meta_key' => '_mochipay_order_id',
            'meta_value' => $system_order_id,
        ));
        if (!empty($orders) && (string) $orders[0]->get_meta('_mochipay_order_id') === $system_order_id) {
            return $orders[0];
        }

        if ('' !== $merchant_order_id) {
            // The merchant reference ends with the WooCommerce database order ID.
            // Loading it directly works with legacy order tables and HPOS, even
            // when an older unpaid MochiPay attempt is confirmed after a retry.
            if (preg_match('/^WC-[A-F0-9]{8}-([0-9]+)$/', $merchant_order_id, $matches)) {
                $candidate = wc_get_order((int) $matches[1]);
                if ($candidate && hash_equals($this->merchant_order_id($candidate), $merchant_order_id)) {
                    return $candidate;
                }
            }

            $orders = wc_get_orders(array(
                'limit' => 1,
                'return' => 'objects',
                'meta_key' => '_mochipay_merchant_order_id',
                'meta_value' => $merchant_order_id,
            ));
            if (!empty($orders)) {
                return $orders[0];
            }
        }
        return false;
    }

    private function callback_response($status, $message)
    {
        status_header($status);
        nocache_headers();
        header('Content-Type: text/plain; charset=utf-8');
        echo esc_html($message);
        exit;
    }

    private function log($level, $message, $context = array())
    {
        if (!$this->debug && 'error' !== $level) {
            return;
        }
        $context['source'] = 'mochipay';
        $this->logger->log($level, $message . ' ' . wp_json_encode($context), $context);
    }
}
