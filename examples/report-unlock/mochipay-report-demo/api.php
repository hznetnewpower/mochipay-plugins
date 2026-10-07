<?php
require_once __DIR__ . "/config.php";
require_once __DIR__ . "/portable/checkout.php";

function mochipay_decode($raw)
{
    $raw = preg_replace('/("(?:amount|base_pay_amount|pay_amount|received_amount|exchange_rate|rate_markup_percent|unique_amount_delta)"\s*:\s*)(-?[0-9]+(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?)(?=\s*[,}])/', '$1"$2"', $raw);
    return json_decode($raw, true);
}

// Demo-only private records bind callbacks to the original local request.
// Use your application's database/transactions and fulfillment logic in production.

function mochipay_sign($text)
{
    return base64_encode(hash_hmac('sha256', $text, MOCHIPAY_API_SECRET, true));
}


function mochipay_request($method, $path, $signingText, $body)
{
    if (!function_exists('curl_init')) {
        return array(
            'ok' => false,
            'http_code' => 0,
            'error' => 'PHP_CURL_EXTENSION_REQUIRED',
            'raw' => '',
            'data' => null
        );
    }

    $url = rtrim(MOCHIPAY_BASE_URL, '/') . $path;
    $headers = array(
        'Accept: application/json',
        'X-Mochi-Key: ' . MOCHIPAY_API_KEY,
        'X-Mochi-Signature: ' . mochipay_sign($signingText)
    );

    $options = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => $headers
    );

    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = $body;
        $headers[] = 'Content-Type: application/json; charset=utf-8';
        $options[CURLOPT_HTTPHEADER] = $headers;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, $options);
    $raw = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        return array(
            'ok' => false,
            'http_code' => $httpCode,
            'error' => $curlError !== '' ? $curlError : 'REQUEST_FAILED',
            'raw' => '',
            'data' => null
        );
    }

    $data = mochipay_decode($raw);
    $apiSuccess = is_array($data) && isset($data['success']) && $data['success'] === true;
    return array(
        'ok' => $httpCode >= 200 && $httpCode < 300 && $apiSuccess,
        'http_code' => $httpCode,
        'error' => $apiSuccess ? '' : (is_array($data) && isset($data['message'])
            ? (string) $data['message'] : 'INVALID_API_RESPONSE'),
        'raw' => $raw,
        'data' => $data
    );
}


function mochipay_create_order($payload)
{
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return array(
            'ok' => false,
            'http_code' => 0,
            'error' => 'JSON_ENCODE_FAILED',
            'raw' => '',
            'data' => null
        );
    }

    // Create Order signs the exact raw JSON bytes sent in the HTTP body.
    return mochipay_request('POST', '/api/v1/orders/create', $json, $json);
}


function mochipay_query_order($field, $value)
{
    if ($field !== 'order_id' && $field !== 'merchant_order_id') {
        return array(
            'ok' => false,
            'http_code' => 0,
            'error' => 'INVALID_QUERY_FIELD',
            'raw' => '',
            'data' => null
        );
    }

    $query = http_build_query(array($field => $value), '', '&', PHP_QUERY_RFC3986);
    // Query Order signs the exact query string without the leading question mark.
    return mochipay_request('GET', '/api/v1/orders/query?' . $query, $query, '');
}


function mochipay_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

