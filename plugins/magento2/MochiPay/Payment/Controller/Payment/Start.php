<?php

namespace MochiPay\Payment\Controller\Payment;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use MochiPay\Payment\Model\Client;

class Start extends Action
{
    private $checkoutSession;
    private $client;
    private $orderRepository;
    private $storeManager;
    private $scopeConfig;
    private $portable;

    public function __construct(Context $context, CheckoutSession $checkoutSession, Client $client, OrderRepositoryInterface $orderRepository, StoreManagerInterface $storeManager, ScopeConfigInterface $scopeConfig, \MochiPay\Payment\Model\Portable $portable)
    {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->client = $client;
        $this->orderRepository = $orderRepository;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->portable = $portable;
    }

    public function execute()
    {
        $order = $this->checkoutSession->getLastRealOrder();
        if (!$order || !$order->getId() || $order->getPayment()->getMethod() !== 'mochipay') return $this->resultRedirectFactory->create()->setPath('checkout/cart');
        try {
            $storeId = (int) $order->getStoreId();
            if (!$this->scopeConfig->getValue('payment/mochipay/active', ScopeInterface::SCOPE_STORE, $storeId)) throw new \RuntimeException('MochiPay is disabled.');
            $method = strtoupper((string) $order->getPayment()->getAdditionalInformation('mochipay_payment_method'));
            $enabled = array_map('trim', explode(',', (string)$this->scopeConfig->getValue('payment/mochipay/payment_methods', ScopeInterface::SCOPE_STORE, $storeId)));
            if (!in_array($method,$enabled,true) || !isset(\MochiPay\Payment\Model\ConfigProvider::methodLabels()[$method])) throw new \RuntimeException('Invalid payment method.');
            $merchantId = 'M2-' . strtoupper(substr(md5($this->storeManager->getStore($storeId)->getBaseUrl()), 0, 8)) . '-' . $order->getIncrementId();
            $address = $order->getShippingAddress() ?: $order->getBillingAddress();
            $items = array();
            $physical = false;
            foreach ($order->getAllVisibleItems() as $item) {
                $items[] = array('name' => $item->getName(), 'quantity' => (float) $item->getQtyOrdered(), 'total' => (string) $item->getRowTotalInclTax());
                if (!$item->getIsVirtual()) $physical = true;
            }
            $returnUrl = $this->_url->getUrl('mochipay/payment/complete', array('_secure' => true, 'id' => $order->getId(), 'token' => 'MOCHIPAY_ATTEMPT_TOKEN'));
            $payload = array(
                'merchant_order_id' => $merchantId,
                'amount' => number_format((float) $order->getGrandTotal(), 8, '.', ''),
                'currency' => strtoupper($order->getOrderCurrencyCode()),
                'payment_method' => $method,
                'unique_amount_direction' => strtoupper((string) $this->scopeConfig->getValue('payment/mochipay/unique_direction', ScopeInterface::SCOPE_STORE, $storeId)) === 'DOWN' ? 'DOWN' : 'UP',
                'source' => 'MAGENTO2',
                'product_type' => $physical ? 'PHYSICAL' : 'DIGITAL_SERVICE',
                'description' => 'Magento 2 order #' . $order->getIncrementId(),
                'product_info' => json_encode(array('platform' => 'Magento 2', 'order_id' => $order->getIncrementId(), 'items' => $items), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'customer_email' => $order->getCustomerEmail(),
                'customer_phone' => $address ? $address->getTelephone() : '',
                'first_name' => $address ? $address->getFirstname() : '',
                'last_name' => $address ? $address->getLastname() : '',
                'company' => $address ? $address->getCompany() : '',
                'country' => $address ? $address->getCountryId() : '',
                'state' => $address ? $address->getRegion() : '',
                'city' => $address ? $address->getCity() : '',
                'address1' => $address ? implode(' ', (array) $address->getStreet()) : '',
                'postal_code' => $address ? $address->getPostcode() : '',
                'customer_ip' => filter_var($order->getRemoteIp(), FILTER_VALIDATE_IP) ? $order->getRemoteIp() : '',
                'notify_url' => $this->_url->getUrl('mochipay/payment/callback', array('_secure' => true)),
                'redirect_url' => $returnUrl,
            );
            $engine = $this->portable->engine($storeId);
            $attempt = $engine->begin((int)$order->getId(), $payload);
            $result = json_decode($attempt['snapshot'],true);
            $order->getPayment()->setAdditionalInformation('mochipay_order_id',$attempt['system_id']);
            $order->getPayment()->setAdditionalInformation('mochipay_merchant_order_id',$merchantId);
            $this->orderRepository->save($order);
            if ($this->scopeConfig->getValue('payment/mochipay/checkout_mode', ScopeInterface::SCOPE_STORE,$storeId) !== 'HPP') return $this->resultRedirectFactory->create()->setUrl($this->_url->getUrl('mochipay/payment/pay',array('_secure'=>true,'id'=>$order->getId(),'token'=>$attempt['token'])));
            return $this->resultRedirectFactory->create()->setUrl($result['payment_url']);
        } catch (\Exception $exception) {
            $this->messageManager->addErrorMessage(__('Unable to start the MochiPay payment. Please contact the store.'));
            $order->addCommentToStatusHistory('MochiPay error: ' . $exception->getMessage());
            $this->orderRepository->save($order);
            return $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }
    }
}
