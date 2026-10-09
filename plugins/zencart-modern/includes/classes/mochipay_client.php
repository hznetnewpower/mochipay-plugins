<?php


class MochiPayTransportException extends RuntimeException {}

class MochiPayApiException extends RuntimeException
{
    public $http, $apiCode, $recoveryContract;
    public function __construct($http, $code, $contract)
    {
        $this->http=(int)$http; $this->apiCode=$code; $this->recoveryContract=$contract;
        parent::__construct('MochiPay API error: ' . $code);
    }
    public function canRecoverNotFound() { return $this->http===404 && $this->apiCode==='ORDER_NOT_FOUND' && $this->recoveryContract==='merchant-reference-v1'; }
    public function isRejected() { return $this->http>=400 && $this->http<500 && !in_array($this->http,array(408,409,429),true); }
}

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
        $this->timeout = max(30, (int) $timeout);

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

    public function queryRequest($requestId)
    {
        $query = 'request_id=' . rawurlencode(trim((string) $requestId));
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
            if (!preg_match('/\A[A-Z][A-Z0-9_]{0,100}\z/', $message)) $message='API_UNAVAILABLE';
            throw new MochiPayApiException($status, $message, isset($decoded['recovery_contract']) ? $decoded['recovery_contract'] : '');
        }

        return $decoded;
    }

    private function curlRequest($url, $method, $body, array $headers)
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
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
            throw new MochiPayTransportException('MochiPay connection failed: ' . $error);
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
            throw new MochiPayTransportException('MochiPay connection failed.');
        }
        $status = 0;
        if (!empty($http_response_header[0]) && preg_match('/\s([0-9]{3})\s/', $http_response_header[0], $match)) {
            $status = (int) $match[1];
        }
        return array($status, $response);
    }
}

function mochipay_query_retryable($e) { return $e instanceof MochiPayTransportException || ($e instanceof MochiPayApiException && ($e->http===408 || $e->http===429 || $e->http>=500)); }
