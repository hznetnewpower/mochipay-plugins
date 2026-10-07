<?php
/** Bundled presentation helpers; PHP 7.0+; no API credentials in browser output. */
class MochiPayPortable
{
    public static function decimal($value)
    {
        $text = trim((string) $value);
        if (strlen($text) > 150 || !preg_match('/^([+-]?)([0-9]+)(?:\.([0-9]+))?(?:[eE]([+-]?[0-9]+))?$/', $text, $m)) return '';
        $fraction = isset($m[3]) ? $m[3] : ''; $power = isset($m[4]) ? (int) $m[4] : 0;
        if (abs($power) > 100) return '';
        $digits = $m[2] . $fraction; $point = strlen($m[2]) + $power;
        if ($point <= 0) { $digits = str_repeat('0', 1 - $point) . $digits; $point = 1; }
        if ($point >= strlen($digits)) $digits .= str_repeat('0', $point - strlen($digits));
        $whole = ltrim(substr($digits, 0, $point), '0'); $tail = rtrim(substr($digits, $point), '0');
        $out = ($whole === '' ? '0' : $whole) . ($tail === '' ? '' : '.' . $tail);
        return $m[1] === '-' && $out !== '0' ? '-' . $out : $out;
    }
    public static function matches($data, $payload, $id)
    {
        if (!is_array($data) || !is_array($payload)) return false;
        foreach (array('merchant_order_id', 'amount', 'currency') as $key) if (!isset($data[$key], $payload[$key])) return false;
        $method = isset($data['payment_method']) ? $data['payment_method'] : (isset($data['wallet_type'], $data['network']) ? $data['wallet_type'] . '_' . $data['network'] : '');
        return ($id === '' || (isset($data['order_id']) && hash_equals((string) $id, (string) $data['order_id']))) &&
            hash_equals((string) $payload['merchant_order_id'], (string) $data['merchant_order_id']) &&
            self::decimal($payload['amount']) !== '' && self::decimal($payload['amount']) === self::decimal($data['amount']) &&
            strtoupper($payload['currency']) === strtoupper($data['currency']) && strtoupper($payload['payment_method']) === strtoupper($method);
    }
    public static function view($data)
    {
        $method = isset($data['payment_method']) ? $data['payment_method'] : (isset($data['wallet_type'], $data['network']) ? $data['wallet_type'] . '_' . $data['network'] : '');
        $parts = explode('_', $method, 2);
        $pay = isset($data['pay_amount']) ? self::decimal($data['pay_amount']) : '';
        $address = isset($data['payment_address']) ? (string) $data['payment_address'] : '';
        $status = isset($data['status']) ? strtoupper($data['status']) : '';
        if ($pay === '' || $pay === '0' || $pay[0] === '-' || !preg_match('/^[A-Za-z0-9]{20,120}$/', $address) || count($parts) !== 2 || !in_array($status, array('PAID','PENDING','WAITING','WAITING_PAYMENT','CONFIRMING','UNDERPAID','OVERPAID','EXPIRED','CANCELLED','CANCELED'), true)) throw new RuntimeException('Payment data is incomplete or unsupported.');
        $expires = isset($data['expires_at']) ? (string) $data['expires_at'] : '';
        if (in_array($status, array('PENDING','WAITING','WAITING_PAYMENT'), true) && isset($data['updated_at']) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $expires) && strcmp($data['updated_at'], $expires) >= 0) $status = 'EXPIRED';
        return array('reference'=>$data['order_id'], 'amount'=>self::decimal($data['amount']), 'currency'=>$data['currency'], 'payAmount'=>$pay, 'asset'=>$parts[0], 'network'=>$parts[1], 'address'=>$address, 'status'=>$status, 'expiresAt'=>$expires);
    }
    public static function page($pollUrl, $returnUrl)
    {
        $config = json_encode(array('poll'=>$pollUrl,'complete'=>$returnUrl), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $css = file_get_contents(__DIR__ . '/onsite.css'); $js = file_get_contents(__DIR__ . '/onsite.js'); $qr = file_get_contents(__DIR__ . '/qrcode.min.js');
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="referrer" content="same-origin"><title>MochiPay payment</title><style>' . $css . '</style></head><body><main><h1>Complete your payment</h1><p>Your order is saved. Reopen the dialog to continue.</p><button id="reopen">Open payment dialog</button></main><script>window.MochiPayConfig=' . $config . ';</script><script>' . $qr . '</script><script>' . $js . '</script></body></html>';
    }
}
