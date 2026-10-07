<?php

class MochipayValidationModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function postProcess()
    {
        $cart = $this->context->cart;
        if (!$this->module->isEnabled() || !$cart->id || !$this->context->customer->id) {
            Tools::redirect('index.php?controller=order&step=1');
        }
        $method = strtoupper(trim(Tools::getValue('mochipay_payment_method')));
        $availableMethods = $this->module->paymentMethods();
        if (!isset($availableMethods[$method])) {
            $this->errors[] = $this->module->l('Please select an available payment currency and network.');
            return $this->setTemplate('error.tpl');
        }

        try {
            $currency = new Currency((int) $cart->id_currency);
            $customer = new Customer((int) $cart->id_customer);
            $address = new Address((int) $cart->id_address_delivery);
            $country = new Country((int) $address->id_country, (int) $this->context->language->id);
            $state = $address->id_state ? new State((int) $address->id_state) : null;
            $total = (float) $cart->getOrderTotal(true, Cart::BOTH);
            $merchantOrderId = 'PS-' . strtoupper(substr(md5(Tools::getShopDomainSsl(true)), 0, 8)) . '-' . (int) $cart->id;
            $waiting = (int) Configuration::get('PS_OS_MOCHIPAY_WAITING');
            if (!Order::getIdByCartId((int)$cart->id)) $this->module->validateOrder((int) $cart->id, $waiting, $total, $this->module->displayName, null, array(), (int) $currency->id, false, $customer->secure_key);
            $orderId = (int) Order::getIdByCartId((int) $cart->id);
            $products = $cart->getProducts();
            $items = array();
            $physical = false;
            foreach ($products as $product) {
                $items[] = array('name' => $product['name'], 'quantity' => (int) $product['cart_quantity'], 'total' => (string) $product['total_wt']);
                if (empty($product['is_virtual'])) $physical = true;
            }
            $payload = array(
                'merchant_order_id' => $merchantOrderId,
                'amount' => number_format($total, 8, '.', ''),
                'currency' => strtoupper($currency->iso_code),
                'payment_method' => $method,
                'unique_amount_direction' => $this->module->direction(),
                'source' => 'PRESTASHOP',
                'product_type' => $physical ? 'PHYSICAL' : 'DIGITAL_SERVICE',
                'description' => 'PrestaShop order #' . $orderId,
                'product_info' => json_encode(array('platform' => 'PrestaShop', 'order_id' => $orderId, 'items' => $items), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'customer_email' => $customer->email,
                'customer_phone' => $address->phone_mobile ?: $address->phone,
                'first_name' => $address->firstname,
                'last_name' => $address->lastname,
                'company' => $address->company,
                'country' => $country->name,
                'state' => $state ? $state->name : '',
                'city' => $address->city,
                'address1' => $address->address1,
                'address2' => $address->address2,
                'postal_code' => $address->postcode,
                'customer_ip' => filter_var(Tools::getRemoteAddr(), FILTER_VALIDATE_IP) ? Tools::getRemoteAddr() : '',
                'notify_url' => $this->context->link->getModuleLink('mochipay', 'callback', array(), true),
                'redirect_url' => $this->context->link->getModuleLink('mochipay', 'return', array('cart_id' => (int) $cart->id), true),
            );
            $service = $this->module->portable();
            $localOrder = new Order($orderId);
            if (!Validate::isLoadedObject($localOrder) || (int)$localOrder->id_customer !== (int)$customer->id || $localOrder->module !== 'mochipay') throw new RuntimeException('Invalid store order.');
            // Refuse a split-cart mismatch rather than paying only one of several orders.
            if (MochiPayPortable::decimal($localOrder->total_paid) !== MochiPayPortable::decimal($payload['amount'])) throw new RuntimeException('Split-cart checkout requires separate payment orders.');
            $service = $this->module->portable();
            $attempt = $service->begin($orderId, $payload);
            $data = json_decode($attempt['snapshot'], true);
            if (Configuration::get('MOCHIPAY_CHECKOUT_MODE') === 'ON_SITE') Tools::redirect($this->context->link->getModuleLink('mochipay','pay',array('id'=>$orderId,'token'=>$attempt['token']),true));
            Tools::redirect($data['payment_url']);
        } catch (Exception $exception) {

            PrestaShopLogger::addLog('MochiPay create error: ' . $exception->getMessage(), 3);
            $this->errors[] = $this->module->l('Unable to start the MochiPay payment. Please try again.');
            $this->setTemplate('error.tpl');
        }
    }
}
