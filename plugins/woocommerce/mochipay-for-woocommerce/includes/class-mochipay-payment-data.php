<?php
defined('ABSPATH') || exit;

/** No floating point arithmetic is used for payable amounts. */
final class MochiPay_Payment_Data
{
    public static function decimal($value)
    {
        $text = trim((string) $value);
        if (!preg_match('/^([+-]?)([0-9]+)(?:\.([0-9]+))?(?:[eE]([+-]?[0-9]+))?$/', $text, $m)) {
            return '';
        }
        $fraction = isset($m[3]) ? $m[3] : '';
        $power = isset($m[4]) ? (int) $m[4] : 0;
        if (abs($power) > 100 || strlen($text) > 150) { return ''; }
        $digits = $m[2] . $fraction;
        $point = strlen($m[2]) + $power;
        if ($point <= 0) { $digits = str_repeat('0', 1 - $point) . $digits; $point = 1; }
        if ($point >= strlen($digits)) { $digits .= str_repeat('0', $point - strlen($digits)); }
        $whole = ltrim(substr($digits, 0, $point), '0');
        $tail = rtrim(substr($digits, $point), '0');
        $out = ($whole === '' ? '0' : $whole) . ($tail === '' ? '' : '.' . $tail);
        return $m[1] === '-' && $out !== '0' ? '-' . $out : $out;
    }

    public static function matches($data, $order, $reference, $attempt = null)
    {
        foreach (array('order_id', 'merchant_order_id', 'amount', 'currency') as $key) {
            if (!isset($data[$key])) { return false; }
        }
        if ($attempt === false) { return false; }
        $snapshot = is_array($attempt) ? $attempt['snapshot'] : $order->get_meta('_mochipay_payment_snapshot');
        $expected_id = is_array($attempt) ? $snapshot['order_id'] : $order->get_meta('_mochipay_order_id');
        $expected_method = is_array($attempt) ? $attempt['payload']['payment_method'] : $order->get_meta('_mochipay_payment_method');
        if (is_array($attempt)) { $reference = $attempt['payload']['merchant_order_id']; }
        if (!hash_equals((string) $expected_id, (string) $data['order_id']) ||
            !hash_equals($reference, (string) $data['merchant_order_id']) ||
            strtoupper((string) $data['currency']) !== strtoupper($order->get_currency()) ||
            self::decimal($data['amount']) === '' ||
            self::decimal($data['amount']) !== self::decimal($order->get_total())) {
            return false;
        }
        $method = isset($data['payment_method']) ? $data['payment_method'] :
            (isset($data['wallet_type'], $data['network']) ? $data['wallet_type'] . '_' . $data['network'] : '');
        if (strtoupper((string) $method) !== (string) $expected_method) { return false; }
        if (is_array($snapshot)) {
            foreach (array('pay_amount', 'payment_address') as $field) {
                if (isset($snapshot[$field])) {
                    if (!isset($data[$field])) { return false; }
                    $a = $snapshot[$field]; $b = $data[$field];
                    if ('pay_amount' === $field) { $a = self::decimal($a); $b = self::decimal($b); }
                    if ((string) $a !== (string) $b) { return false; }
                }
            }
        }
        if ('PAID' === strtoupper(isset($data['status']) ? (string)$data['status'] : '')) {
            $pay = isset($data['pay_amount']) ? self::decimal($data['pay_amount']) : '';
            if ($pay === '' || $pay === '0' || $pay[0] === '-' || !isset($data['received_amount']) || self::decimal($data['received_amount']) !== $pay) { return false; }
        }
        return true;
    }

    public static function view($data, $order, $attempt = null)
    {
        $method = is_array($attempt) ? (string) $attempt['payload']['payment_method'] : (string) $order->get_meta('_mochipay_payment_method');
        $parts = explode('_', $method, 2);
        $pay = isset($data['pay_amount']) ? self::decimal($data['pay_amount']) : '';
        $address = isset($data['payment_address']) ? trim((string) $data['payment_address']) : '';
        $status = strtoupper(isset($data['status']) ? (string) $data['status'] : '');
        $states = array('WAITING', 'WAITING_PAYMENT', 'PENDING', 'PAID', 'EXPIRED', 'CANCELLED', 'CANCELED', 'UNDERPAID', 'OVERPAID', 'CONFIRMING');
        if ($pay === '' || $pay === '0' || $pay[0] === '-' ||
            !preg_match('/^[A-Za-z0-9]{20,120}$/', $address) || !in_array($status, $states, true)) {
            return new WP_Error('mochipay_data', 'Payment data is incomplete or unsupported. Contact the store.');
        }
        $expires = isset($data['expires_at']) ? (string) $data['expires_at'] : '';
        // API timestamps have no zone. Compare only two server-origin timestamps.
        if (in_array($status, array('WAITING', 'WAITING_PAYMENT', 'PENDING'), true) &&
            isset($data['updated_at']) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $expires) &&
            strcmp((string) $data['updated_at'], $expires) >= 0) { $status = 'EXPIRED'; }
        return array(
            'orderId' => $order->get_id(), 'reference' => $data['order_id'],
            'orderNumber' => (string) $order->get_order_number(),
            'amount' => self::decimal($order->get_total()), 'currency' => $order->get_currency(),
            'payAmount' => $pay, 'asset' => $parts[0], 'network' => isset($parts[1]) ? $parts[1] : '',
            'address' => $address, 'status' => $status, 'expiresAt' => $expires,
            'exchangeRate' => isset($data['exchange_rate']) ? self::decimal($data['exchange_rate']) : '',
            'delta' => isset($data['unique_amount_delta']) ? self::decimal($data['unique_amount_delta']) : '',
        );
    }
}
