<?php
require_once DIR_FS_CATALOG . DIR_WS_CLASSES . 'mochipay_client.php';
require_once DIR_FS_CATALOG . DIR_WS_CLASSES . 'mochipay/mochipay_core.php';
function mochipay_service()
{
    global $db;
    $service = new MochiPayPortable(new MochiPayClient(MODULE_PAYMENT_MOCHIPAY_API_URL, MODULE_PAYMENT_MOCHIPAY_API_KEY, MODULE_PAYMENT_MOCHIPAY_API_SECRET),
        function($sql) use ($db) { $r=$db->Execute($sql); $rows=array(); while(!$r->EOF) { $rows[]=$r->fields; $r->MoveNext(); } return $rows; },
        function($text) { return zen_db_input($text); }, DB_PREFIX . 'mochipay_attempt');
    $service->install(); return $service;
}
function mochipay_settle($id, $data, $payload)
{
    global $db;
    $r=$db->Execute('SELECT orders_status, currency, currency_value, order_total FROM ' . TABLE_ORDERS . ' WHERE orders_id=' . (int)$id);
    if ($r->EOF || strtoupper($r->fields['currency'])!==strtoupper($payload['currency']) || MochiPayPortable::decimal(number_format((float)$r->fields['order_total']*(float)$r->fields['currency_value'],8,'.',''))!==MochiPayPortable::decimal($payload['amount'])) throw new RuntimeException('Store order mismatch.');
    $paid=defined('MODULE_PAYMENT_MOCHIPAY_PAID_ORDER_STATUS_ID')?(int)MODULE_PAYMENT_MOCHIPAY_PAID_ORDER_STATUS_ID:2;
    $waiting=defined('MODULE_PAYMENT_MOCHIPAY_ORDER_STATUS_ID')?(int)MODULE_PAYMENT_MOCHIPAY_ORDER_STATUS_ID:1;
    if($paid===$waiting)throw new RuntimeException('Waiting and paid statuses must differ.');
    if((int)$r->fields['orders_status']===$paid)return;
    if((int)$r->fields['orders_status']!==$waiting)throw new RuntimeException('Store order requires manual review.');
    $tx=isset($data['tx_hash'])?$data['tx_hash']:$data['order_id'];
    $db->Execute('UPDATE ' . TABLE_ORDERS . ' SET orders_status=' . $paid . ',last_modified=NOW() WHERE orders_id=' . (int)$id);
    $db->Execute("INSERT INTO " . TABLE_ORDERS_STATUS_HISTORY . " (orders_id,orders_status_id,date_added,customer_notified,comments) VALUES (" . (int)$id . ',' . $paid . ",NOW(),0,'MochiPay payment confirmed. Transaction: " . zen_db_input($tx) . "')");
}
