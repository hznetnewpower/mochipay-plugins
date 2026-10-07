<?php
/** Existing-order presentation example. PHP 7.0-8.4. No order is created here. */
define('MOCHIPAY_HELPERS_ONLY', true);
require_once __DIR__ . '/order.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: same-origin');
try {
    $reference = isset($_GET['reference']) && is_string($_GET['reference']) ? $_GET['reference'] : '';
    $token = isset($_GET['token']) && is_string($_GET['token']) ? $_GET['token'] : '';
    $mode = isset($_GET['mode']) && is_string($_GET['mode']) ? strtoupper($_GET['mode']) : MOCHIPAY_CHECKOUT_MODE;
    if (!preg_match('/^[A-Za-z0-9_.-]{1,100}$/', $reference) || !in_array($mode, array('ON_SITE', 'HPP'), true)) throw new RuntimeException('Invalid checkout request.');
    $attempt = mochipay_load($reference);
    if (!$attempt || empty($attempt['snapshot']['order_id']) || $token === '' || !hash_equals($attempt['token'], $token)) throw new RuntimeException('Invalid payment link.');
    $response = mochipay_query_order('order_id', $attempt['snapshot']['order_id']);
    if (!$response['ok'] || !mochipay_bound($attempt, $response['data'])) throw new RuntimeException('Payment verification failed.');
    $payment = $response['data'];
    MochiPayPortable::view($payment);
    $expectedHpp = rtrim(MOCHIPAY_BASE_URL, '/') . '/pay/' . $attempt['snapshot']['order_id'];
    if (!isset($payment['payment_url']) || $payment['payment_url'] !== $expectedHpp) throw new RuntimeException('Unexpected payment destination.');

    // HPP: redirect the customer to the SAME existing order on MochiPay.
    if ($mode === 'HPP') {
        header('Location: ' . $payment['payment_url'], true, 303);
        exit;
    }

    // ON_SITE: embed the bundled dialog in the merchant's own checkout HTML.
    // The browser polls the local order.php endpoint; API credentials stay server-side.
    $pollUrl = 'order.php?' . http_build_query(array('view'=>$reference, 'token'=>$token, 'poll'=>1), '', '&', PHP_QUERY_RFC3986);
    $completeUrl = 'callback.php?' . http_build_query(array('mode'=>'return', 'merchant_order_id'=>$reference), '', '&', PHP_QUERY_RFC3986);
    $config = json_encode(array('poll'=>$pollUrl, 'complete'=>$completeUrl), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if ($config === false) throw new RuntimeException('Unable to prepare checkout.');
} catch (Exception $e) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Unable to open this payment. Return to your order or contact the merchant.';
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Your store checkout · MochiPay on-site example</title>
    <link rel="stylesheet" href="portable/onsite.css?v=76">
    <style>main.store-checkout{max-width:760px;margin:40px auto;padding:30px;text-align:left;border:1px solid #d8dfd8;border-radius:18px;background:#fffdf6}.store-checkout h1{margin-top:0}.store-checkout a{display:inline-block;margin:12px 0;color:#b64d0e}.store-total{font-size:23px;font-weight:bold}.store-checkout p{line-height:1.65}</style>
</head>
<body>
<main class="store-checkout">
    <p>YOUR STORE · CHECKOUT</p>
    <h1>Your order is ready for payment</h1>
    <p class="store-total"><?php echo mochipay_h(MochiPayPortable::decimal($payment['amount']) . ' ' . $payment['currency']); ?></p>
    <p>The payment dialog is embedded in this page. Closing it leaves your store checkout visible and keeps the original payment order.</p>
    <button id="reopen" type="button">Open payment dialog</button>
    <p><a href="order.php">Back to API demo</a></p>
</main>
<script>window.MochiPayConfig = <?php echo $config; ?>;</script>
<script src="portable/qrcode.min.js"></script>
<script src="portable/onsite.js?v=76"></script>
</body>
</html>
