<?php
$_mp_lang = require DIR_FS_CATALOG . 'includes/languages/english/modules/payment/lang.mochipay.php';
foreach ($_mp_lang as $_mp_key=>$_mp_value) if (!defined($_mp_key)) define($_mp_key,$_mp_value);
unset($_mp_lang,$_mp_key,$_mp_value);


class mochipay
{
    public $code;
    public $title;
    public $description;
    public $enabled;
    public $sort_order;
    public $order_status;
    public $_check;

    public function __construct()
    {
        $this->code = 'mochipay';
        $this->title = MODULE_PAYMENT_MOCHIPAY_TEXT_TITLE;
        $this->description = MODULE_PAYMENT_MOCHIPAY_TEXT_DESCRIPTION;
        $this->sort_order = defined('MODULE_PAYMENT_MOCHIPAY_SORT_ORDER') ? MODULE_PAYMENT_MOCHIPAY_SORT_ORDER : 0;
        $this->enabled = defined('MODULE_PAYMENT_MOCHIPAY_STATUS') && MODULE_PAYMENT_MOCHIPAY_STATUS === 'True';
        $this->order_status = defined('MODULE_PAYMENT_MOCHIPAY_ORDER_STATUS_ID') ? (int) MODULE_PAYMENT_MOCHIPAY_ORDER_STATUS_ID : 1;
    }

    public function update_status()
    {
    }

    public function javascript_validation()
    {
        return false;
    }

    public function selection()
    {
        $options = array();
        foreach ($this->enabledMethods() as $code => $label) {
            $options[] = array('id' => $code, 'text' => $label);
        }
        return array(
            'id' => $this->code,
            'module' => $this->title,
            'fields' => array(array(
                'title' => MODULE_PAYMENT_MOCHIPAY_TEXT_PAYMENT_METHOD,
                'field' => zen_draw_pull_down_menu('mochipay_payment_method', $options, 'USDT_TRC20'),
                'tag' => 'mochipay_payment_method',
            )),
        );
    }

    public function pre_confirmation_check()
    {
        $method = isset($_POST['mochipay_payment_method']) ? strtoupper(trim($_POST['mochipay_payment_method'])) : '';
        $enabledMethods = $this->enabledMethods();
        if (!isset($enabledMethods[$method])) {
            global $messageStack;
            $messageStack->add_session('checkout_payment', 'Please select an available MochiPay payment currency and network.', 'error');
            zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'));
        }
        $_SESSION['mochipay_payment_method'] = $method;
    }

    public function confirmation()
    {
        return false;
    }

    public function process_button()
    {
        $method = isset($_SESSION['mochipay_payment_method']) ? $_SESSION['mochipay_payment_method'] : 'USDT_TRC20';
        return zen_draw_hidden_field('mochipay_payment_method', $method);
    }

    public function before_process()
    {
        if (!$this->enabled || !MODULE_PAYMENT_MOCHIPAY_API_KEY || !MODULE_PAYMENT_MOCHIPAY_API_SECRET || (int)MODULE_PAYMENT_MOCHIPAY_ORDER_STATUS_ID === (int)MODULE_PAYMENT_MOCHIPAY_PAID_ORDER_STATUS_ID) throw new RuntimeException('MochiPay is not ready. Review credentials and order statuses.');
        return false;
    }

    public function after_process()
    {
        global $db, $insert_id, $order;
        require_once DIR_FS_CATALOG . DIR_WS_CLASSES . 'mochipay_bridge.php';

        try {
            // Use the saved order amount, independent of checkout/cart cleanup.
            if ((int) $insert_id <= 0) throw new RuntimeException('MochiPay requires a saved store order.');
            $savedOrder = $db->Execute('SELECT order_total, currency, currency_value FROM ' . TABLE_ORDERS . ' WHERE orders_id=' . (int) $insert_id . ' LIMIT 1');
            if ($savedOrder->EOF || !isset($savedOrder->fields['currency']) || trim($savedOrder->fields['currency']) === '') {
                throw new RuntimeException('MochiPay could not read a valid saved order amount and currency.');
            }
            $amount = mochipay_order_amount($savedOrder->fields['order_total'], $savedOrder->fields['currency_value'], $savedOrder->fields['currency']);
            $products = $this->orderProducts($order, $insert_id);
            $method = isset($_SESSION['mochipay_payment_method']) ? $_SESSION['mochipay_payment_method'] : 'USDT_TRC20';
            $merchantOrderId = 'ZC-' . strtoupper(substr(md5(HTTP_SERVER . DIR_WS_CATALOG), 0, 8)) . '-' . (int) $insert_id;
            $returnUrl = mochipay_endpoint_url('mochipay_return.php', array('local_order_id' => (int) $insert_id));
            $payload = array(
                'merchant_order_id' => $merchantOrderId,
                'amount' => $amount,
                'currency' => strtoupper($savedOrder->fields['currency']),
                'payment_method' => $method,
                'unique_amount_direction' => $this->direction(),
                'source' => 'ZENCART',
                'product_type' => $this->productType($products),
                'description' => 'Zen Cart order #' . (int) $insert_id,
                'product_info' => $this->productInfo($products, $insert_id),
                'customer_email' => isset($order->customer['email_address']) ? $order->customer['email_address'] : '',
                'customer_phone' => isset($order->customer['telephone']) ? $order->customer['telephone'] : '',
                'first_name' => isset($order->delivery['firstname']) ? $order->delivery['firstname'] : '',
                'last_name' => isset($order->delivery['lastname']) ? $order->delivery['lastname'] : '',
                'company' => isset($order->delivery['company']) ? $order->delivery['company'] : '',
                'country' => isset($order->delivery['country']['title']) ? $order->delivery['country']['title'] : '',
                'state' => isset($order->delivery['state']) ? $order->delivery['state'] : '',
                'city' => isset($order->delivery['city']) ? $order->delivery['city'] : '',
                'address1' => isset($order->delivery['street_address']) ? $order->delivery['street_address'] : '',
                'address2' => isset($order->delivery['suburb']) ? $order->delivery['suburb'] : '',
                'postal_code' => isset($order->delivery['postcode']) ? $order->delivery['postcode'] : '',
                'customer_ip' => $this->customerIp(),
                'notify_url' => mochipay_endpoint_url('mochipay_callback.php'),
                'redirect_url' => $returnUrl,
            );
            $service = mochipay_service();
            $client = new MochiPayClient(MODULE_PAYMENT_MOCHIPAY_API_URL, MODULE_PAYMENT_MOCHIPAY_API_KEY, MODULE_PAYMENT_MOCHIPAY_API_SECRET);
            $attempt = $service->begin((int) $insert_id, $payload);
            if (!is_array($attempt) || empty($attempt['token']) || !is_string($attempt['token']) ||
                !preg_match('/^[a-f0-9]{48,64}$/iD', $attempt['token'])) {
                throw new RuntimeException('MochiPay saved payment link is incomplete. Please retry this order.');
            }
            unset($_SESSION['mochipay_payment_method']);
            $data = json_decode($attempt['snapshot'], true);
            // Clear the completed cart before either HPP or on-site navigation.
            global $cart;
            if (is_object($cart)) $cart->reset(true);
            unset($_SESSION['cartID']);
            if (defined('MODULE_PAYMENT_MOCHIPAY_CHECKOUT_MODE') && MODULE_PAYMENT_MOCHIPAY_CHECKOUT_MODE === 'ON_SITE') {
                return zen_redirect(mochipay_endpoint_url('mochipay_pay.php', array('id' => (int) $insert_id, 'token' => $attempt['token'])));
            }
            return zen_redirect($data['payment_url']);
        } catch (Exception $exception) {
            $this->paymentError($exception, $insert_id);
        } catch (Throwable $exception) {
            // PHP 7+ type/runtime errors do not extend Exception.
            $this->paymentError($exception, $insert_id);
        }
    }

    private function paymentError($exception, $orderId)
    {
        global $db, $messageStack;
        $db->Execute("INSERT INTO " . TABLE_ORDERS_STATUS_HISTORY . " (orders_id, orders_status_id, date_added, customer_notified, comments) VALUES (" .
            (int) $orderId . ", " . (int) $this->order_status . ", NOW(), 0, 'MochiPay error: " . zen_db_input($exception->getMessage()) . "')");
        $messageStack->add_session('account', 'Unable to start payment. Your cart is kept. Please return to checkout and try again.', 'error');
        zen_redirect(zen_href_link(FILENAME_ACCOUNT_HISTORY_INFO, 'order_id=' . (int) $orderId, 'SSL'));
    }

    public function get_error()
    {
        return false;
    }

    public function check()
    {
        global $db;
        $db->Execute("UPDATE " . TABLE_CONFIGURATION . " SET set_function='mochipay_cfg_methods(' WHERE configuration_key='MODULE_PAYMENT_MOCHIPAY_PAYMENT_METHODS'");
        $db->Execute("UPDATE " . TABLE_CONFIGURATION . " SET set_function='zen_cfg_pull_down_order_statuses(',use_function='zen_get_order_status_name' WHERE configuration_key IN ('MODULE_PAYMENT_MOCHIPAY_ORDER_STATUS_ID','MODULE_PAYMENT_MOCHIPAY_PAID_ORDER_STATUS_ID')");
        $mode = $db->Execute("SELECT configuration_id FROM " . TABLE_CONFIGURATION . " WHERE configuration_key='MODULE_PAYMENT_MOCHIPAY_CHECKOUT_MODE'");
        if ($mode->EOF && defined('MODULE_PAYMENT_MOCHIPAY_STATUS')) $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title,configuration_key,configuration_value,configuration_description,configuration_group_id,sort_order,set_function,date_added) VALUES ('Payment Interface','MODULE_PAYMENT_MOCHIPAY_CHECKOUT_MODE','ON_SITE','HPP or ON_SITE',6,10,'zen_cfg_select_option(array(\"HPP\", \"ON_SITE\"), ',NOW())");
        if (!isset($this->_check)) {
            $result = $db->Execute("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_PAYMENT_MOCHIPAY_STATUS'");
            $this->_check = $result->RecordCount();
        }
        return $this->_check;
    }

    public function install()
    {
        global $db;
        $group = 6;
        $rows = array(
            array('Enable MochiPay', 'MODULE_PAYMENT_MOCHIPAY_STATUS', 'False', 'Enable cryptocurrency payments.', "zen_cfg_select_option(array('True', 'False'), ", 1),
            array('MochiPay URL', 'MODULE_PAYMENT_MOCHIPAY_API_URL', 'https://mochi.bz', 'Site URL without /api.', '', 2),
            array('API Key', 'MODULE_PAYMENT_MOCHIPAY_API_KEY', '', 'Merchant API key.', '', 3),
            array('API Secret', 'MODULE_PAYMENT_MOCHIPAY_API_SECRET', '', 'Merchant API secret.', '', 4),
            array('Payment Methods', 'MODULE_PAYMENT_MOCHIPAY_PAYMENT_METHODS', 'USDT_TRC20,USDC_ERC20,BTC_BITCOIN,ETH_ERC20,SOL_SOLANA', 'Select customer payment choices.', 'mochipay_cfg_methods(', 5),
            array('Unique Amount Direction', 'MODULE_PAYMENT_MOCHIPAY_UNIQUE_AMOUNT_DIRECTION', 'UP', 'Increase or decrease a reserved exact amount.', "zen_cfg_select_option(array('UP', 'DOWN'), ", 6),
            array('Waiting Order Status', 'MODULE_PAYMENT_MOCHIPAY_ORDER_STATUS_ID', '1', 'Status while awaiting payment.', 'zen_cfg_pull_down_order_statuses(', 7),
            array('Paid Order Status', 'MODULE_PAYMENT_MOCHIPAY_PAID_ORDER_STATUS_ID', '2', 'Status after verified payment.', 'zen_cfg_pull_down_order_statuses(', 8),
            array('Payment Interface', 'MODULE_PAYMENT_MOCHIPAY_CHECKOUT_MODE', 'ON_SITE', 'HPP redirects to MochiPay. ON_SITE opens a payment dialog on this store.', "zen_cfg_select_option(array('HPP', 'ON_SITE'), ", 10),
            array('Sort Order', 'MODULE_PAYMENT_MOCHIPAY_SORT_ORDER', '0', 'Display order.', '', 9),
        );
        foreach ($rows as $row) {
            $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) VALUES ('" .
                zen_db_input($row[0]) . "', '" . zen_db_input($row[1]) . "', '" . zen_db_input($row[2]) . "', '" . zen_db_input($row[3]) . "', " . $group . ", " . (int) $row[5] . ", '" . zen_db_input($row[4]) . "', '" . (in_array($row[1], array('MODULE_PAYMENT_MOCHIPAY_ORDER_STATUS_ID','MODULE_PAYMENT_MOCHIPAY_PAID_ORDER_STATUS_ID'),true) ? 'zen_get_order_status_name' : '') . "', NOW())");
        }
    }

    public function remove()
    {
        global $db;
        $db->Execute("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key IN ('" . implode("','", $this->keys()) . "')");
    }

    public function keys()
    {
        return array('MODULE_PAYMENT_MOCHIPAY_STATUS', 'MODULE_PAYMENT_MOCHIPAY_API_URL', 'MODULE_PAYMENT_MOCHIPAY_API_KEY', 'MODULE_PAYMENT_MOCHIPAY_API_SECRET', 'MODULE_PAYMENT_MOCHIPAY_PAYMENT_METHODS', 'MODULE_PAYMENT_MOCHIPAY_UNIQUE_AMOUNT_DIRECTION', 'MODULE_PAYMENT_MOCHIPAY_ORDER_STATUS_ID', 'MODULE_PAYMENT_MOCHIPAY_PAID_ORDER_STATUS_ID', 'MODULE_PAYMENT_MOCHIPAY_SORT_ORDER', 'MODULE_PAYMENT_MOCHIPAY_CHECKOUT_MODE');
    }

    private function enabledMethods()
    {
        $labels = array('USDT_TRC20' => 'USDT — TRON (TRC20)', 'USDC_ERC20' => 'USDC — Ethereum (ERC20)', 'BTC_BITCOIN' => 'BTC — Bitcoin', 'ETH_ERC20' => 'ETH — Ethereum', 'SOL_SOLANA' => 'SOL — Solana');
        $enabled = array();
        $value = defined('MODULE_PAYMENT_MOCHIPAY_PAYMENT_METHODS') ? MODULE_PAYMENT_MOCHIPAY_PAYMENT_METHODS : 'USDT_TRC20';
        foreach (explode(',', $value) as $method) {
            $method = strtoupper(trim($method));
            if (isset($labels[$method])) $enabled[$method] = $labels[$method];
        }
        return !empty($enabled) ? $enabled : array('USDT_TRC20' => $labels['USDT_TRC20']);
    }

    private function direction()
    {
        return defined('MODULE_PAYMENT_MOCHIPAY_UNIQUE_AMOUNT_DIRECTION') && MODULE_PAYMENT_MOCHIPAY_UNIQUE_AMOUNT_DIRECTION === 'DOWN' ? 'DOWN' : 'UP';
    }

    private function orderProducts($checkoutOrder, $orderId)
    {
        global $db;
        // FEC/custom checkouts may clear or replace the global order object.
        $products = array();
        if (is_object($checkoutOrder) && isset($checkoutOrder->products) && is_array($checkoutOrder->products)) {
            foreach ($checkoutOrder->products as $product) {
                if (is_array($product)) $products[] = $product;
            }
        }
        if (!empty($products)) return $products;

        $rows = $db->Execute('SELECT products_id, products_name, products_model, products_quantity, final_price FROM ' . TABLE_ORDERS_PRODUCTS .
            ' WHERE orders_id=' . (int) $orderId . ' ORDER BY orders_products_id');
        while (!$rows->EOF) {
            $products[] = array(
                'id' => $rows->fields['products_id'],
                'name' => $rows->fields['products_name'],
                'model' => $rows->fields['products_model'],
                'qty' => $rows->fields['products_quantity'],
                'final_price' => $rows->fields['final_price'],
            );
            $rows->MoveNext();
        }
        return $products;
    }

    private function productType(array $products)
    {
        global $db;
        if (empty($products)) return 'PHYSICAL';
        foreach ($products as $product) {
            $id = isset($product['id']) ? (int) $product['id'] : 0;
            $result = $db->Execute("SELECT products_virtual FROM " . TABLE_PRODUCTS . " WHERE products_id = " . $id . " LIMIT 1");
            if ($result->EOF || (int) $result->fields['products_virtual'] !== 1) return 'PHYSICAL';
        }
        return 'DIGITAL_SERVICE';
    }

    private function productInfo(array $products, $orderId)
    {
        $items = array();
        foreach ($products as $product) {
            $items[] = array('name' => isset($product['name']) ? $product['name'] : '', 'quantity' => isset($product['qty']) ? (int) $product['qty'] : 1, 'total' => isset($product['final_price']) ? (string) $product['final_price'] : '');
        }
        return json_encode(array('platform' => 'Zen Cart', 'order_id' => (int) $orderId, 'items' => $items), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function customerIp()
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? trim($_SERVER['REMOTE_ADDR']) : '';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }
}

if (!function_exists('mochipay_cfg_methods')) {
    function mochipay_cfg_methods($value, $key='MODULE_PAYMENT_MOCHIPAY_PAYMENT_METHODS')
    {
        $codes=array('USDT_TRC20','USDC_ERC20','BTC_BITCOIN','ETH_ERC20','SOL_SOLANA');$selected=explode(',',(string)$value);
        $out='<div id="mp-asset-settings"><input type="hidden" id="mp-asset-value" name="configuration['.htmlspecialchars($key,ENT_QUOTES,'UTF-8').']" value="'.htmlspecialchars($value,ENT_QUOTES,'UTF-8').'">';
        foreach($codes as $code)$out.='<label style="display:block"><input class="mp-asset-setting" type="checkbox" value="'.$code.'"'.(in_array($code,$selected,true)?' checked':'').'> '.$code.'</label>';
        return $out.'</div><script>(function(){var root=document.getElementById("mp-asset-settings"),hidden=document.getElementById("mp-asset-value");root.addEventListener("change",function(){hidden.value=Array.prototype.map.call(root.querySelectorAll(".mp-asset-setting:checked"),function(n){return n.value}).join(",")})})();</script>';
    }
}
