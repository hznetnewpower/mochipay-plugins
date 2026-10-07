<?php
/**
 * MochiPay callback demo.
 *
 * POST = asynchronous server notification (notify_url)
 * GET  = synchronous browser return (redirect_url)
 *
 * Never mark an order as paid from callback fields alone. This example always
 * calls Query Order with the API secret and uses that verified response.
 */
define('MOCHIPAY_HELPERS_ONLY', true);
require_once __DIR__ . '/order.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: same-origin');

function callback_text($status, $message)
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo $message;
    exit;
}

if (!mochipay_is_configured()) {
    callback_text(500, 'API_NOT_CONFIGURED');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $callback = mochipay_decode($raw);
    if (!is_array($callback)) {
        callback_text(400, 'INVALID_JSON');
    }

    $orderId = isset($callback['order_id']) ? trim((string) $callback['order_id']) : '';
    if ($orderId === '') {
        callback_text(400, 'ORDER_ID_REQUIRED');
    }

    // Authenticate the result by querying MochiPay server-to-server.
    $verified = mochipay_query_order('order_id', $orderId);
    if (!$verified['ok'] || !is_array($verified['data'])) {
        callback_text(503, 'VERIFICATION_FAILED');
    }

    $order = $verified['data'];
    if (!isset($order['order_id']) || !hash_equals((string) $order['order_id'], $orderId)) {
        callback_text(409, 'ORDER_MISMATCH');
    }

    $verifiedMerchantId = isset($order['merchant_order_id']) ? (string)$order['merchant_order_id'] : '';
    try { $record = mochipay_load($verifiedMerchantId); }
    catch (Exception $e) { callback_text(503, 'LOCAL_STORAGE_UNAVAILABLE'); }
    if (!mochipay_bound($record, $order) || (isset($callback['merchant_order_id']) && !hash_equals($verifiedMerchantId, (string)$callback['merchant_order_id']))) callback_text(409, 'LOCAL_ORDER_MISMATCH');
    if (!isset($order['received_amount']) || MochiPayPortable::decimal($order['received_amount']) !== MochiPayPortable::decimal($order['pay_amount'])) callback_text(409, 'PAYMENT_AMOUNT_REQUIRES_REVIEW');

    if (!isset($order['status']) || strtoupper((string) $order['status']) !== 'PAID') {
        callback_text(409, 'ORDER_NOT_PAID');
    }

    /*
     * TODO: In your production database, atomically fulfill the bound local order.
     * This demo acknowledges verification only; it does not deliver goods.
     * Make the operation idempotent: repeated callbacks must not deliver goods
     * or credit the customer more than once.
     */
    try { mochipay_record_verified($record, $order); }
    catch (Exception $e) { callback_text(503, 'LOCAL_UPDATE_FAILED'); }
    callback_text(200, 'OK');
}

// Browser return: query the saved order, then compare its original local binding.
$merchantOrderId = isset($_GET['merchant_order_id']) ? trim((string)$_GET['merchant_order_id']) : '';
$verified = null; $record = null; $paid = false; $authorized = false;
try {
    $record = mochipay_load($merchantOrderId);
    $returnToken = isset($_GET['token']) ? (string)$_GET['token'] : '';
    $authorized = $record && $returnToken !== '' && hash_equals($record['token'], $returnToken);
    if ($authorized && !empty($record['snapshot']['order_id'])) {
        $verified = mochipay_query_order('order_id', $record['snapshot']['order_id']);
        $data = $verified['data'];
        $paid = $verified['ok'] && mochipay_bound($record, $data) && strtoupper($data['status']) === 'PAID' && isset($data['received_amount']) && MochiPayPortable::decimal($data['received_amount']) === MochiPayPortable::decimal($data['pay_amount']);
        if ($paid) mochipay_record_verified($record, $data);
    }
} catch (Exception $e) { $paid = false; }
// Show only the safe payment view, never the full API/customer response on a public return page.
$safeView = null;
if ($authorized && $verified && $verified['ok'] && mochipay_bound($record, $verified['data'])) {
    try { $safeView = MochiPayPortable::view($verified['data']); } catch (Exception $e) { $safeView = null; }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow"><title>Payment result · MochiPay PHP Demo</title>
    <style>
        body{margin:0;background:#fff9f3;color:#172033;font:15px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.box{max-width:760px;margin:60px auto;padding:30px;background:#fff;border:1px solid #dfe4ec;border-radius:18px;box-shadow:0 8px 28px rgba(23,32,51,.06)}h1{margin-top:0}.paid{color:#087a55}.waiting{color:#b42318}pre{overflow:auto;padding:16px;border-radius:12px;background:#111827;color:#e5e7eb;white-space:pre-wrap;word-break:break-word}a{color:#b64d0e;font-weight:700}
    </style>
</head>
<body>
<main class="box">
    <h1 class="<?php echo $paid ? 'paid' : 'waiting'; ?>"><?php echo $paid ? 'Payment verified' : 'Payment could not be verified'; ?></h1>
    <p>This page does not trust the browser redirect. It queries MochiPay server-to-server before showing the result.</p>
    <?php if ($verified === null): ?>
        <p>No order identifier was supplied.</p>
    <?php else: ?>
        <p>HTTP <?php echo (int) $verified['http_code']; ?> · <?php echo mochipay_h($verified['ok'] ? 'SUCCESS' : $verified['error']); ?></p>
        <pre><?php echo mochipay_h(mochipay_pretty_json($safeView)); ?></pre>
    <?php endif; ?>
    <p><a href="order.php">← Back to API Demo</a></p>
</main>
</body>
</html>
