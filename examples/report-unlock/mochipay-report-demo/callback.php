<?php
require_once __DIR__ . '/report-lib.php';
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !report_configured()) throw new RuntimeException('Invalid callback.');
    $reference=isset($_GET['reference']) && is_string($_GET['reference']) ? $_GET['reference'] : '';
    $token=isset($_GET['token']) && is_string($_GET['token']) ? $_GET['token'] : '';
    $record=report_load($reference);
    if (!$record || !hash_equals($record['callback_token'],$token)) throw new RuntimeException('Invalid callback.');
    $raw=file_get_contents('php://input',false,null,0,65537);
    $input=strlen($raw)<=65536 ? mochipay_decode($raw) : null;
    if (!is_array($input) || !isset($input['order_id']) || !is_string($input['order_id']) || empty($record['snapshot']) || !hash_equals($record['snapshot']['order_id'],(string)$input['order_id'])) throw new RuntimeException('Invalid callback.');
    $verified=report_verify($reference);
    if (!$verified['paid']) throw new RuntimeException('Payment not verified.');
    echo 'OK';
} catch (Exception $e) { http_response_code(409);echo 'PAYMENT_NOT_VERIFIED'; }
