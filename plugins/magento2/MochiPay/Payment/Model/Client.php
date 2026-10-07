<?php

namespace MochiPay\Payment\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Store\Model\ScopeInterface;

class Client
{
    private $scopeConfig;
    private $encryptor;
    private $curl;

    public function __construct(ScopeConfigInterface $scopeConfig, EncryptorInterface $encryptor, Curl $curl)
    {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
        $this->curl = $curl;
    }

    public function createOrder(array $payload, $storeId = null)
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) throw new \RuntimeException('Unable to encode MochiPay request.');
        return $this->request('/api/v1/orders/create', 'POST', $body, $body, $storeId);
    }

    public function queryOrder($systemOrderId, $storeId = null)
    {
        $query = 'order_id=' . rawurlencode(trim((string) $systemOrderId));
        return $this->request('/api/v1/orders/query?' . $query, 'GET', '', $query, $storeId);
    }

    public function queryReference($reference, $storeId = null)
    {
        $query = 'merchant_order_id=' . rawurlencode(trim((string)$reference));
        return $this->request('/api/v1/orders/query?' . $query, 'GET', '', $query, $storeId);
    }
    private function request($path, $method, $body, $signingText, $storeId)
    {
        $base = rtrim((string) $this->scopeConfig->getValue('payment/mochipay/api_url', ScopeInterface::SCOPE_STORE, $storeId), '/');
        $key = $this->decryptConfig('payment/mochipay/api_key', $storeId);
        $secret = $this->decryptConfig('payment/mochipay/api_secret', $storeId);
        if (strtolower((string)parse_url($base, PHP_URL_SCHEME)) !== 'https') throw new \RuntimeException('MochiPay URL must use HTTPS.');
        if ($base === '' || $key === '' || $secret === '') throw new \RuntimeException('MochiPay API settings are incomplete.');
        $signature = base64_encode(hash_hmac('sha256', $signingText, $secret, true));
        $this->curl->setTimeout(30);
        $this->curl->addHeader('Accept', 'application/json');
        $this->curl->addHeader('X-Mochi-Key', $key);
        $this->curl->addHeader('X-Mochi-Signature', $signature);
        if ($method === 'POST') {
            $this->curl->addHeader('Content-Type', 'application/json; charset=utf-8');
            $this->curl->post($base . $path, $body);
        } else {
            $this->curl->get($base . $path);
        }
        $status = (int) $this->curl->getStatus();
        $decoded = json_decode(preg_replace('/("(?:amount|pay_amount|received_amount|exchange_rate|unique_amount_delta)"\s*:\s*)(-?[0-9]+(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?)(?=\s*[,}])/', '$1"$2"', (string)$this->curl->getBody()), true);
        if ($status < 200 || $status >= 300 || !is_array($decoded) || empty($decoded['success'])) {
            $message = is_array($decoded) && !empty($decoded['message']) ? $decoded['message'] : 'HTTP_' . $status;
            throw new \RuntimeException('MochiPay API error: ' . $message);
        }
        return $decoded;
    }

    private function decryptConfig($path, $storeId)
    {
        $value = (string) $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $storeId);
        try { return trim($this->encryptor->decrypt($value)); } catch (\Exception $e) { return trim($value); }
    }
}
