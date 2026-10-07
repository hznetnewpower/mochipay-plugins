<?php

class MochiPayClient
{
    private $baseUrl;
    private $apiKey;
    private $apiSecret;
    private $timeout;

    public function __construct($baseUrl, $apiKey, $apiSecret, $timeout = 30)
    {
        $this->baseUrl = rtrim(trim((string) $baseUrl), '/');
        $this->apiKey = trim((string) $apiKey);
        $this->apiSecret = trim((string) $apiSecret);
        $this->timeout = max(5, (int) $timeout);

        if (strtolower((string) parse_url($this->baseUrl, PHP_URL_SCHEME)) !== 'https' || parse_url($this->baseUrl, PHP_URL_USER) || parse_url($this->baseUrl, PHP_URL_QUERY)) { throw new RuntimeException('MochiPay URL must be an HTTPS site URL.'); }
        if ($this->baseUrl === '' || $this->apiKey === '' || $this->apiSecret === '') {
            throw new RuntimeException('MochiPay API settings are incomplete.');
        }
    }

    public function createOrder(array $payload)
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            throw new RuntimeException('Unable to encode the MochiPay order request.');
        }

        return $this->request('/api/v1/orders/create', 'POST', $body, $body);
    }

    public function queryOrder($systemOrderId)
    {
        $query = 'order_id=' . rawurlencode(trim((string) $systemOrderId));
        return $this->request('/api/v1/orders/query?' . $query, 'GET', '', $query);
    }

    public function queryReference($reference)
    {
        $query = 'merchant_order_id=' . rawurlencode(trim((string) $reference));
        return $this->request('/api/v1/orders/query?' . $query, 'GET', '', $query);
    }

    private function request($path, $method, $body, $signingText)
    {
        $signature = base64_encode(hash_hmac('sha256', $signingText, $this->apiSecret, true));
        $headers = array(
            'Accept: application/json',
            'X-Mochi-Key: ' . $this->apiKey,
            'X-Mochi-Signature: ' . $signature,
        );
        if ($method === 'POST') {
            $headers[] = 'Content-Type: application/json; charset=utf-8';
        }

        if (function_exists('curl_init')) {
            list($status, $responseBody) = $this->curlRequest($this->baseUrl . $path, $method, $body, $headers);
        } else {
            list($status, $responseBody) = $this->streamRequest($this->baseUrl . $path, $method, $body, $headers);
        }

        $decoded = json_decode(preg_replace('/("(?:amount|pay_amount|received_amount|exchange_rate|unique_amount_delta)"\s*:\s*)(-?[0-9]+(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?)(?=\s*[,}])/', '$1"$2"', (string) $responseBody), true);
        if ($status < 200 || $status >= 300 || !is_array($decoded) || empty($decoded['success'])) {
            $message = is_array($decoded) && !empty($decoded['message'])
                ? (string) $decoded['message'] : 'HTTP_' . $status;
            throw new RuntimeException('MochiPay API error: ' . $message);
        }

        return $decoded;
    }

    private function curlRequest($url, $method, $body, array $headers)
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        if ($method === 'POST') {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }
        $response = curl_exec($curl);
        if ($response === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new RuntimeException('MochiPay connection failed: ' . $error);
        }
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        return array($status, $response);
    }

    private function streamRequest($url, $method, $body, array $headers)
    {
        $options = array('ssl'=>array('verify_peer'=>true,'verify_peer_name'=>true), 'http' => array(
            'method' => $method,
            'timeout' => $this->timeout,
            'ignore_errors' => true,
            'follow_location' => 0,
            'max_redirects' => 0,
            'header' => implode("\r\n", $headers),
        ));
        if ($method === 'POST') {
            $options['http']['content'] = $body;
        }
        $response = @file_get_contents($url, false, stream_context_create($options));
        if ($response === false) {
            throw new RuntimeException('MochiPay connection failed.');
        }
        $status = 0;
        if (!empty($http_response_header[0]) && preg_match('/\s([0-9]{3})\s/', $http_response_header[0], $match)) {
            $status = (int) $match[1];
        }
        return array($status, $response);
    }
}
