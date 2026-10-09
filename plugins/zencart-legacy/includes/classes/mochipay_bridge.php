<?php
require_once DIR_FS_CATALOG . DIR_WS_CLASSES . 'mochipay_client.php';
require_once DIR_FS_CATALOG . DIR_WS_CLASSES . 'mochipay/mochipay_core.php';
function mochipay_service()
{
    global $db;
    // Payment state and advisory locks must never reuse Zen Cart queryCache results.
    $service = new MochiPayPortable(new MochiPayClient(MODULE_PAYMENT_MOCHIPAY_API_URL, MODULE_PAYMENT_MOCHIPAY_API_KEY, MODULE_PAYMENT_MOCHIPAY_API_SECRET),
        function($sql) use ($db) { $r=$db->Execute($sql, false, false, 0, true); $rows=array(); while(!$r->EOF) { $rows[]=$r->fields; $r->MoveNext(); } return $rows; },
        function($text) { return zen_db_input($text); }, DB_PREFIX . 'mochipay_attempt');
    $service->install(); return $service;
}
function mochipay_settle($id, $data, $payload)
{
    global $db;
    $r=$db->Execute('SELECT orders_status, currency, currency_value, order_total FROM ' . TABLE_ORDERS . ' WHERE orders_id=' . (int)$id, false, false, 0, true);
    if ($r->EOF || strtoupper($r->fields['currency'])!==strtoupper($payload['currency']) || MochiPayPortable::decimal(mochipay_order_amount($r->fields['order_total'], $r->fields['currency_value'], $r->fields['currency']))!==MochiPayPortable::decimal($payload['amount'])) throw new RuntimeException('Store order mismatch.');
    $paid=defined('MODULE_PAYMENT_MOCHIPAY_PAID_ORDER_STATUS_ID')?(int)MODULE_PAYMENT_MOCHIPAY_PAID_ORDER_STATUS_ID:2;
    $waiting=defined('MODULE_PAYMENT_MOCHIPAY_ORDER_STATUS_ID')?(int)MODULE_PAYMENT_MOCHIPAY_ORDER_STATUS_ID:1;
    if($paid===$waiting)throw new RuntimeException('Waiting and paid statuses must differ.');
    if((int)$r->fields['orders_status']===$paid)return;
    if((int)$r->fields['orders_status']!==$waiting)throw new RuntimeException('Store order requires manual review.');
    $tx=isset($data['tx_hash'])?$data['tx_hash']:$data['order_id'];
    $db->Execute('UPDATE ' . TABLE_ORDERS . ' SET orders_status=' . $paid . ',last_modified=NOW() WHERE orders_id=' . (int)$id);
    $db->Execute("INSERT INTO " . TABLE_ORDERS_STATUS_HISTORY . " (orders_id,orders_status_id,date_added,customer_notified,comments) VALUES (" . (int)$id . ',' . $paid . ",NOW(),0,'MochiPay payment confirmed. Transaction: " . zen_db_input($tx) . "')");
}

// Stored Zen Cart order_total (2 decimals) x currency_value (6 decimals).
// String arithmetic preserves the exact amount without requiring BCMath.
function mochipay_order_amount($total, $currencyValue, $currency)
{
    $parts = array();
    $scale = 0;
    foreach (array($total, $currencyValue) as $value) {
        $value = trim((string) $value);
        if (strlen($value) > 40 || !preg_match('/^([0-9]+)(?:\.([0-9]+))?$/', $value, $matches)) {
            throw new RuntimeException('MochiPay saved order amount or exchange rate is invalid.');
        }
        $fraction = isset($matches[2]) ? rtrim($matches[2], '0') : '';
        $scale += strlen($fraction);
        $digits = ltrim($matches[1] . $fraction, '0');
        if ($digits === '') throw new RuntimeException('MochiPay saved order amount and exchange rate must be positive.');
        $parts[] = $digits;
    }
    if ($scale > 8) throw new RuntimeException('MochiPay saved order amount exceeds supported precision.');
    $a = $parts[0]; $b = $parts[1];
    $result = array_fill(0, strlen($a) + strlen($b), 0);
    for ($i = strlen($a) - 1; $i >= 0; $i--) {
        for ($j = strlen($b) - 1; $j >= 0; $j--) {
            $position = $i + $j + 1;
            $sum = $result[$position] + (int) $a[$i] * (int) $b[$j];
            $result[$position] = $sum % 10;
            $result[$position - 1] += (int) floor($sum / 10);
        }
    }
    $digits = ltrim(implode('', $result), '0');
    $digits = str_pad($digits, $scale + 1, '0', STR_PAD_LEFT);
    $currency = strtoupper(trim((string) $currency));
    // Mirrors the API defaults: fiat=2; USDT/USDC=6; BTC/ETH/SOL=8.
    // Payment asset and payable-amount matching precision are separate.
    $limits = array('USDT' => 6, 'USDC' => 6, 'BTC' => 8, 'ETH' => 8, 'SOL' => 8);
    $decimals = isset($limits[$currency]) ? $limits[$currency] : 2;
    if ($scale > $decimals) {
        $cut = strlen($digits) - ($scale - $decimals);
        $roundUp = (int) $digits[$cut] >= 5;
        $digits = substr($digits, 0, $cut);
        if ($roundUp) {
            $carry = 1;
            for ($i = strlen($digits) - 1; $i >= 0 && $carry; $i--) {
                $next = (int) $digits[$i] + $carry;
                $digits[$i] = (string) ($next % 10);
                $carry = $next >= 10 ? 1 : 0;
            }
            if ($carry) $digits = '1' . $digits;
        }
    } else {
        $digits .= str_repeat('0', $decimals - $scale);
    }
    if (trim($digits, '0') === '') throw new RuntimeException('MochiPay converted order amount rounds to zero.');
    $digits = str_pad($digits, $decimals + 1, '0', STR_PAD_LEFT);
    $whole = ltrim(substr($digits, 0, -$decimals), '0');
    return ($whole === '' ? '0' : $whole) . '.' . substr($digits, -$decimals);
}

// These files are standalone catalog endpoints, not main_page routes.
function mochipay_endpoint_url($file, array $parameters = array())
{
    if (!in_array($file, array('mochipay_pay.php', 'mochipay_return.php', 'mochipay_callback.php'), true)) {
        throw new RuntimeException('Invalid MochiPay endpoint.');
    }
    $query = http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    // The sixth Zen Cart parameter is static=true. Disable SEO rewriting.
    return html_entity_decode(zen_href_link($file, $query, 'SSL', $file !== 'mochipay_callback.php', false, true), ENT_QUOTES, 'UTF-8');
}
