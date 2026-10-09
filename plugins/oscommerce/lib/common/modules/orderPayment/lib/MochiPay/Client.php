<?php
namespace MochiPayShared;


class TransportException extends \RuntimeException {}

class ApiException extends \RuntimeException
{
    public $http, $apiCode, $recoveryContract;
    public function __construct($http, $code, $contract)
    {
        $this->http=(int)$http; $this->apiCode=$code; $this->recoveryContract=$contract;
        parent::__construct('MochiPay API error: ' . $code);
    }
    public function canRecoverNotFound() { return $this->http===404 && $this->apiCode==='ORDER_NOT_FOUND' && $this->recoveryContract==='request-id-v1'; }
    public function isRejected() { return $this->http>=400 && $this->http<500 && !in_array($this->http,array(408,409,429),true); }
}

final class Client
{
    private $base, $key, $secret, $transport;
    public function __construct(array $config, $transport = null)
    {
        $this->base = rtrim(trim($config['url'] ?? 'https://mochi.bz'), '/');
        $u = parse_url($this->base);
        if (!$u || ($u['scheme'] ?? '') !== 'https' || empty($u['host']) || isset($u['user']) || isset($u['pass']) || isset($u['query']) || isset($u['fragment']) || !empty($u['path']) || (isset($u['port']) && $u['port'] !== 443)) throw new \RuntimeException('MochiPay URL must be an HTTPS site origin without /api.');
        $this->key = trim($config['key'] ?? ''); $this->secret = trim($config['secret'] ?? '');
        if ($this->key === '' || $this->secret === '') throw new \RuntimeException('Enter the merchant API key and secret.');
        $this->transport = $transport;
    }
    public function fingerprint() { return hash('sha256', $this->base . "\n" . $this->key); }
    public function create(array $payload)
    {
        $body = Payment::json($payload);
        return $this->request('/api/v1/orders/create', 'POST', $body, $body);
    }
    public function queryRequest($requestId)
    {
        $query = 'request_id=' . rawurlencode(trim((string) $requestId));
        return $this->request('/api/v1/orders/query?' . $query, 'GET', '', $query);
    }

    public function queryReference($reference)
    {
        $q = 'merchant_order_id=' . rawurlencode($reference);
        return $this->request('/api/v1/orders/query?' . $q, 'GET', '', $q);
    }
    public function query($id)
    {
        if (!preg_match('/^[a-f0-9]{32}$/iD', $id)) throw new \RuntimeException('Invalid payment identifier.');
        $q = 'order_id=' . rawurlencode($id);
        return $this->request('/api/v1/orders/query?' . $q, 'GET', '', $q);
    }
    public function hosted($url, $id)
    {
        $u = parse_url($url); $base = parse_url($this->base);
        if (!$u || ($u['scheme'] ?? '') !== 'https' || strtolower($u['host'] ?? '') !== strtolower($base['host']) || isset($u['user']) || isset($u['pass']) || isset($u['query']) || isset($u['fragment']) || (isset($u['port']) && $u['port'] !== 443) || ($u['path'] ?? '') !== '/pay/' . $id || !preg_match('/^[a-f0-9]{32}$/iD', $id)) throw new \RuntimeException('Unexpected hosted payment URL.');
        return $url;
    }
    private function request($path, $method, $body, $signed)
    {
        $headers = ['Accept: application/json', 'X-Mochi-Key: ' . $this->key, 'X-Mochi-Signature: ' . base64_encode(hash_hmac('sha256', $signed, $this->secret, true))];
        if ($method === 'POST') $headers[] = 'Content-Type: application/json; charset=utf-8';
        if ($this->transport) $response = call_user_func($this->transport, $this->base . $path, $method, $body, $headers);
        else {
            if (!function_exists('curl_init')) throw new \RuntimeException('PHP cURL is required.');
            $c = curl_init($this->base . $path);
            curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 30, CURLOPT_TIMEOUT => 30, CURLOPT_FOLLOWLOCATION => false, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
            if ($method === 'POST') curl_setopt($c, CURLOPT_POSTFIELDS, $body);
            $raw = curl_exec($c); $status = (int) curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
            if ($raw === false) throw new TransportException('Connection failed. Your saved payment can be retried. Do not create another order.');
            $response = [$status, $raw];
        }
        // Quote numeric JSON tokens before decoding: no binary-float round trip.
        $text = self::exactJson((string) $response[1]);
        $data = json_decode($text, true);
        if ((int) $response[0] !== 200 || !is_array($data) || ($data['success'] ?? null) !== true) {
            $code = $data['message'] ?? 'API_UNAVAILABLE';
            if (!is_string($code) || !preg_match('/\A[A-Z][A-Z0-9_]{0,100}\z/', $code)) $code='API_UNAVAILABLE';
            throw new ApiException($response[0], $code, $data['recovery_contract'] ?? '');
        }
        return $data;
    }
    public static function exactJson($json)
    {
        return preg_replace_callback('/"(?:[^"\\\\]|\\\\.)*"|-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?/', function ($m) { return $m[0][0] === '"' ? $m[0] : '"' . $m[0] . '"'; }, $json);
    }
}
