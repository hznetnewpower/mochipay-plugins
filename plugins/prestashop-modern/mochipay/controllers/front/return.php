<?php

class MochipayReturnModuleFrontController extends ModuleFrontController
{
    public $ssl = true;

    public function initContent()
    {
        $cartId = (int) Tools::getValue('cart_id');
        $orderId = (int) Order::getIdByCartId($cartId);
        $order = new Order($orderId);
        if (!Validate::isLoadedObject($order) || !$this->context->customer->id || (int)$order->id_customer !== (int)$this->context->customer->id) Tools::redirect('index.php?controller=history');
        Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $cartId . '&id_module=' . (int) $this->module->id . '&id_order=' . $orderId . '&key=' . urlencode($order->secure_key));
    }
}
