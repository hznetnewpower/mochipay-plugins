<?php
/**
 * MochiPay PHP API Demo (PHP 7.0-8.4)
 *
 * This single file contains configuration, API signing/request helpers,
 * Create Order, Query Order and a small browser UI. callback.php reuses the
 * same helpers for asynchronous notifications and synchronous returns.
 */

// -------------------------------------------------------------------------
// 1. Configuration - replace the two credential placeholders before use.
// -------------------------------------------------------------------------
define('MOCHIPAY_BASE_URL', 'https://mochi.bz');
define('MOCHIPAY_API_KEY', 'YOUR_API_KEY');
define('MOCHIPAY_API_SECRET', 'YOUR_API_SECRET');

// SSL peer verification is disabled in the supplied cURL example.
define('MOCHIPAY_CHECKOUT_MODE', 'ON_SITE');
// Optional absolute HTTPS directory URL, especially behind a reverse proxy.
define('MOCHIPAY_DEMO_PUBLIC_URL', '');
require_once __DIR__ . '/portable/checkout.php';

// Preserve financial JSON numbers as decimal strings before PHP can round them.
function mochipay_decode($raw)
{
    $raw = preg_replace('/("(?:amount|base_pay_amount|pay_amount|received_amount|exchange_rate|rate_markup_percent|unique_amount_delta)"\s*:\s*)(-?[0-9]+(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?)(?=\s*[,}])/', '$1"$2"', $raw);
    return json_decode($raw, true);
}

// Demo-only private records bind callbacks to the original local request.
// Use your application's database/transactions and fulfillment logic in production.
function mochipay_record_path($reference)
{
    $dir = sys_get_temp_dir() . '/mochipay-demo-' . substr(hash('sha256', __FILE__), 0, 20);
    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Private demo storage is unavailable.');
    }
    return $dir . '/' . hash('sha256', $reference) . '.json';
}
function mochipay_load($reference)
{
    $path = mochipay_record_path($reference);
    if (!is_file($path)) return null;
    $handle = fopen($path, 'rb');
    if (!$handle || !flock($handle, LOCK_SH)) throw new RuntimeException('Demo storage is busy.');
    $record = json_decode(stream_get_contents($handle), true);
    flock($handle, LOCK_UN); fclose($handle);
    return is_array($record) ? $record : null;
}
function mochipay_save_locked($handle, $record)
{
    $json = json_encode($record, JSON_UNESCAPED_SLASHES);
    if ($json === false || !rewind($handle) || !ftruncate($handle, 0) || fwrite($handle, $json) !== strlen($json) || !fflush($handle)) {
        throw new RuntimeException('Unable to persist the demo attempt.');
    }
}
function mochipay_bound($record, $data)
{
    if (!$record || empty($record['snapshot']['order_id']) || !MochiPayPortable::matches($data, $record['payload'], $record['snapshot']['order_id'])) return false;
    foreach (array('payment_address', 'pay_amount') as $key) {
        if (!isset($data[$key], $record['snapshot'][$key])) return false;
        if ($key === 'pay_amount') {
            if (MochiPayPortable::decimal($data[$key]) !== MochiPayPortable::decimal($record['snapshot'][$key])) return false;
        } elseif (!hash_equals((string)$record['snapshot'][$key], (string)$data[$key])) return false;
    }
    return true;
}
function mochipay_begin($payload)
{
    $path = mochipay_record_path($payload['merchant_order_id']);
    $handle = fopen($path, 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) throw new RuntimeException('Demo storage is busy.');
    chmod($path, 0600);
    try {
        $raw = stream_get_contents($handle);
        $record = $raw === '' ? null : json_decode($raw, true);
        if ($raw !== '' && !is_array($record)) throw new RuntimeException('Damaged demo record. Review before creating another payment.');
        if ($record) {
            if (!MochiPayPortable::matches($record['payload'], $payload, '') || $record['payload']['unique_amount_direction'] !== $payload['unique_amount_direction']) {
                throw new RuntimeException('This reference already belongs to different payment details.');
            }
            $result = mochipay_query_order(empty($record['snapshot']['order_id']) ? 'merchant_order_id' : 'order_id', empty($record['snapshot']['order_id']) ? $payload['merchant_order_id'] : $record['snapshot']['order_id']);
        } else {
            $record = array('payload'=>$payload, 'token'=>bin2hex(random_bytes(24)), 'snapshot'=>array(), 'stage'=>'creating');
            // Save BEFORE the HTTP call. An uncertain response is recovered by query, never another POST.
            mochipay_save_locked($handle, $record);
            $result = mochipay_create_order($payload);
        }
        if (!$result['ok']) throw new RuntimeException('Payment creation/query needs review: ' . $result['error'] . '. Retry the same reference to query it; do not submit a new reference blindly.');
        $data = $result['data'];
        if (!MochiPayPortable::matches($data, $record['payload'], '') || empty($data['order_id']) || !preg_match('/^[a-f0-9]{32}$/i', $data['order_id'])) throw new RuntimeException('Payment does not match the saved local request.');
        MochiPayPortable::view($data);
        if (!empty($record['snapshot']) && !mochipay_bound($record, $data)) throw new RuntimeException('Payment instructions have changed. Review required.');
        $expectedUrl = rtrim(MOCHIPAY_BASE_URL, '/') . '/pay/' . $data['order_id'];
        if (!isset($data['payment_url']) || $data['payment_url'] !== $expectedUrl) throw new RuntimeException('Unexpected hosted payment URL.');
        $record['snapshot'] = $data; $record['stage'] = 'ready';
        mochipay_save_locked($handle, $record);
        return array($result, $record);
    } finally { flock($handle, LOCK_UN); fclose($handle); }
}

function mochipay_is_configured()
{
    return MOCHIPAY_API_KEY !== 'YOUR_API_KEY' &&
        MOCHIPAY_API_SECRET !== 'YOUR_API_SECRET' &&
        MOCHIPAY_API_KEY !== '' && MOCHIPAY_API_SECRET !== '';
}

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
    $apiSuccess = is_array($data) && !empty($data['success']);
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

function mochipay_public_url($file, $query)
{
    if (MOCHIPAY_DEMO_PUBLIC_URL !== '') {
        $url = rtrim(MOCHIPAY_DEMO_PUBLIC_URL, '/') . '/' . ltrim($file, '/');
        return $query === '' ? $url : $url . '?' . $query;
    }
    $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
    $scheme = $https ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $script = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/order.php';
    $directory = rtrim(str_replace('\\', '/', dirname($script)), '/');
    $url = $scheme . '://' . $host . ($directory === '' ? '' : $directory) . '/' . ltrim($file, '/');
    return $query === '' ? $url : $url . '?' . $query;
}

function mochipay_form_value($name, $default)
{
    return isset($_POST[$name]) && is_string($_POST[$name]) ? $_POST[$name] : $default;
}

function mochipay_h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function mochipay_pretty_json($value)
{
    if (is_string($value)) {
        $decoded = mochipay_decode($value);
        if (is_array($decoded)) {
            $value = $decoded;
        } else {
            return $value;
        }
    }
    $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return $json === false ? '' : $json;
}

// When included by callback.php, expose only the helpers above.
if (defined('MOCHIPAY_HELPERS_ONLY') && MOCHIPAY_HELPERS_ONLY === true) {
    return;
}

if (isset($_GET['view'])) {
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    try {
        $reference = (string)$_GET['view'];
        $record = mochipay_load($reference);
        $token = isset($_GET['token']) ? (string)$_GET['token'] : '';
        if (!$record || empty($record['snapshot']) || $token === '' || !hash_equals($record['token'], $token)) throw new RuntimeException('Invalid payment link.');
        if (isset($_GET['poll'])) {
            $verified = mochipay_query_order('order_id', $record['snapshot']['order_id']);
            if (!$verified['ok'] || !mochipay_bound($record, $verified['data'])) throw new RuntimeException('Payment verification failed.');
            $data = $verified['data'];
            if (strtoupper($data['status']) === 'PAID' && (!isset($data['received_amount']) || MochiPayPortable::decimal($data['received_amount']) !== MochiPayPortable::decimal($data['pay_amount']))) throw new RuntimeException('Received payment requires review.');
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(array('success'=>true, 'data'=>MochiPayPortable::view($data)), JSON_UNESCAPED_SLASHES);
        } else {
            $query = http_build_query(array('view'=>$reference, 'token'=>$token, 'poll'=>1), '', '&', PHP_QUERY_RFC3986);
            $returnQuery = http_build_query(array('mode'=>'return', 'merchant_order_id'=>$reference, 'token'=>$token), '', '&', PHP_QUERY_RFC3986);
            echo MochiPayPortable::page('order.php?' . $query, 'callback.php?' . $returnQuery);
        }
    } catch (Exception $e) {
        http_response_code(403);
        if (isset($_GET['poll'])) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(array('success'=>false, 'message'=>$e->getMessage())); }
        else { header('Content-Type: text/plain; charset=utf-8'); echo $e->getMessage(); }
    }
    exit;
}

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: same-origin');
session_start();
if (empty($_SESSION['mochipay_demo_csrf'])) {
    $_SESSION['mochipay_demo_csrf'] = bin2hex(random_bytes(16));
}

$result = null;
$requestPreview = null;
$action = isset($_POST['action']) ? (string) $_POST['action'] : '';
$message = '';
$record = null;
$checkoutMode = MOCHIPAY_CHECKOUT_MODE;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = isset($_POST['csrf']) ? (string) $_POST['csrf'] : '';
    if (!hash_equals($_SESSION['mochipay_demo_csrf'], $token)) {
        $message = 'Invalid form token. Refresh the page and try again.';
    } elseif (!mochipay_is_configured()) {
        $message = 'Set MOCHIPAY_API_KEY and MOCHIPAY_API_SECRET at the top of order.php first.';
    } elseif ($action === 'create') {
        $merchantOrderId = trim(isset($_POST['merchant_order_id']) ? $_POST['merchant_order_id'] : '');
        $callbackQuery = http_build_query(array(
            'mode' => 'return',
            'merchant_order_id' => $merchantOrderId
        ), '', '&', PHP_QUERY_RFC3986);

        $payload = array(
            'merchant_order_id' => $merchantOrderId,
            'amount' => trim(isset($_POST['amount']) ? $_POST['amount'] : ''),
            'currency' => strtoupper(trim(isset($_POST['currency']) ? $_POST['currency'] : 'USD')),
            'payment_method' => strtoupper(trim(isset($_POST['payment_method']) ? $_POST['payment_method'] : 'USDT_TRC20')),
            'unique_amount_direction' => strtoupper(trim(isset($_POST['unique_amount_direction']) ? $_POST['unique_amount_direction'] : 'UP')),
            'product_type' => strtoupper(trim(isset($_POST['product_type']) ? $_POST['product_type'] : 'DIGITAL_SERVICE')),
            'description' => trim(isset($_POST['description']) ? $_POST['description'] : ''),
            'customer_email' => trim(isset($_POST['customer_email']) ? $_POST['customer_email'] : ''),
            'notify_url' => mochipay_public_url('callback.php', 'mode=notify'),
            'redirect_url' => mochipay_public_url('callback.php', $callbackQuery)
        );
        $requestPreview = $payload;
        $checkoutMode = isset($_POST['checkout_mode']) && $_POST['checkout_mode'] === 'HPP' ? 'HPP' : 'ON_SITE';
        try {
            if (!preg_match('/^[A-Za-z0-9_.-]{1,100}$/', $merchantOrderId)) throw new RuntimeException('Use a 1-100 character reference containing letters, numbers, dot, hyphen or underscore.');
            list($result, $record) = mochipay_begin($payload);
        } catch (Exception $e) { $message = $e->getMessage(); }
    } elseif ($action === 'query') {
        $field = isset($_POST['query_field']) ? (string) $_POST['query_field'] : 'order_id';
        $value = trim(isset($_POST['query_value']) ? $_POST['query_value'] : '');
        $requestPreview = array($field => $value);
        $result = mochipay_query_order($field, $value);
    }
}

$defaultMerchantOrderId = $action === 'create' && !empty($merchantOrderId) ? $merchantOrderId : 'DEMO-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow"><title>MochiPay PHP API Demo · On-site + HPP</title>
    <style>
        :root{--ink:#172033;--muted:#657085;--line:#dfe4ec;--brand:#b64d0e;--bg:#fff9f3;--good:#087a55;--bad:#b42318}
        *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
        .wrap{max-width:1080px;margin:38px auto;padding:0 18px}.hero,.card{background:#fff;border:1px solid var(--line);border-radius:18px;box-shadow:0 8px 28px rgba(23,32,51,.05)}
        .hero{padding:30px;margin-bottom:20px}.hero h1{margin:0 0 8px;font-size:30px}.hero p{margin:0;color:var(--muted)}
        .notice{margin-top:18px;padding:12px 14px;border-radius:10px;background:#fff4e5;color:#8a4b08}.notice.good{background:#eaf8f2;color:var(--good)}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}.card{padding:24px}.card h2{margin:0 0 18px;font-size:20px}
        label{display:block;margin:13px 0 5px;font-weight:650}input,select{width:100%;padding:11px 12px;border:1px solid #cbd3df;border-radius:9px;background:#fff;color:var(--ink)}
        button,.pay-link{display:inline-block;margin-top:18px;padding:11px 18px;border:0;border-radius:9px;background:var(--brand);color:#fff;font-weight:700;text-decoration:none;cursor:pointer}
        .result{margin-top:20px}.result h2{font-size:18px}.status{font-weight:800;color:var(--good)}.status.bad{color:var(--bad)}
        pre{overflow:auto;padding:16px;border-radius:12px;background:#111827;color:#e5e7eb;font:13px/1.55 ui-monospace,SFMono-Regular,Consolas,monospace;white-space:pre-wrap;word-break:break-word}
        .hint{color:var(--muted);font-size:13px}.full{grid-column:1/-1}@media(max-width:760px){.grid{grid-template-columns:1fr}.full{grid-column:auto}.wrap{margin:18px auto}}
    </style>
</head>
<body>
<main class="wrap">
    <section class="hero">
        <h1>MochiPay PHP API Demo</h1>
        <p>Create one payment order, choose an on-site dialog or Hosted Payment Page (HPP), and verify payment server-side.</p>
        <div class="notice <?php echo mochipay_is_configured() ? 'good' : ''; ?>">
            <?php echo mochipay_is_configured()
                ? 'API credentials are configured. Keep order.php server-side and never expose the API secret in browser JavaScript.'
                : 'Before testing, replace YOUR_API_KEY and YOUR_API_SECRET at the top of order.php.'; ?>
        </div>
        <?php if ($message !== ''): ?><div class="notice"><?php echo mochipay_h($message); ?></div><?php endif; ?>
    </section>

    <div class="grid">
        <section class="card">
            <h2>1. Create order</h2>
            <form method="post">
                <input type="hidden" name="csrf" value="<?php echo mochipay_h($_SESSION['mochipay_demo_csrf']); ?>">
                <input type="hidden" name="action" value="create">
                <label>Merchant order ID</label>
                <input name="merchant_order_id" maxlength="100" required value="<?php echo mochipay_h($defaultMerchantOrderId); ?>">
                <label>Amount</label><input name="amount" required value="<?php echo mochipay_h(mochipay_form_value('amount', '9.90')); ?>" inputmode="decimal">
                <label>Order currency</label><input name="currency" required value="<?php echo mochipay_h(mochipay_form_value('currency', 'USD')); ?>" maxlength="20">
                <label>Checkout mode</label>
                <select name="checkout_mode"><option value="ON_SITE" <?php echo mochipay_form_value('checkout_mode', MOCHIPAY_CHECKOUT_MODE) === 'ON_SITE' ? 'selected' : ''; ?>>On-site payment dialog (default)</option><option value="HPP" <?php echo mochipay_form_value('checkout_mode', MOCHIPAY_CHECKOUT_MODE) === 'HPP' ? 'selected' : ''; ?>>Hosted Payment Page (HPP)</option></select>
                <label>Payment method</label>
                <select name="payment_method">
                    <option <?php echo mochipay_form_value('payment_method', 'USDT_TRC20') === 'USDT_TRC20' ? 'selected' : ''; ?>>USDT_TRC20</option><option <?php echo mochipay_form_value('payment_method', 'USDT_TRC20') === 'USDC_ERC20' ? 'selected' : ''; ?>>USDC_ERC20</option><option <?php echo mochipay_form_value('payment_method', 'USDT_TRC20') === 'BTC_BITCOIN' ? 'selected' : ''; ?>>BTC_BITCOIN</option><option <?php echo mochipay_form_value('payment_method', 'USDT_TRC20') === 'ETH_ERC20' ? 'selected' : ''; ?>>ETH_ERC20</option><option <?php echo mochipay_form_value('payment_method', 'USDT_TRC20') === 'SOL_SOLANA' ? 'selected' : ''; ?>>SOL_SOLANA</option>
                </select>
                <label>Unique amount direction</label><select name="unique_amount_direction"><option <?php echo mochipay_form_value('unique_amount_direction', 'UP') === 'UP' ? 'selected' : ''; ?>>UP</option><option <?php echo mochipay_form_value('unique_amount_direction', 'UP') === 'DOWN' ? 'selected' : ''; ?>>DOWN</option></select>
                <label>Product type</label><select name="product_type"><option <?php echo mochipay_form_value('product_type', 'DIGITAL_SERVICE') === 'DIGITAL_SERVICE' ? 'selected' : ''; ?>>DIGITAL_SERVICE</option><option <?php echo mochipay_form_value('product_type', 'DIGITAL_SERVICE') === 'PHYSICAL' ? 'selected' : ''; ?>>PHYSICAL</option></select>
                <label>Description</label><input name="description" maxlength="500" value="<?php echo mochipay_h(mochipay_form_value('description', 'PHP API demo order')); ?>">
                <label>Customer email (optional)</label><input name="customer_email" type="email" maxlength="255" value="<?php echo mochipay_h(mochipay_form_value('customer_email', '')); ?>">
                <button type="submit">Create payment order</button>
            </form>
            <p class="hint">All five payment methods and UP / DOWN are supported. The checkout mode is a local UI preference, not an API request field. Set the public HTTPS demo directory URL when using a reverse proxy.</p>
        </section>

        <section class="card">
            <h2>2. Query order</h2>
            <form method="post">
                <input type="hidden" name="csrf" value="<?php echo mochipay_h($_SESSION['mochipay_demo_csrf']); ?>">
                <input type="hidden" name="action" value="query">
                <label>Identifier type</label>
                <select name="query_field"><option value="order_id">MochiPay order_id</option><option value="merchant_order_id">merchant_order_id</option></select>
                <label>Identifier</label><input name="query_value" required placeholder="Paste an order ID">
                <button type="submit">Query current status</button>
            </form>
            <p class="hint">A query signs the exact RFC 3986 query string without the leading question mark.</p>
        </section>

        <?php if ($result !== null): ?>
        <section class="card result full">
            <h2>API result — HTTP <?php echo (int) $result['http_code']; ?> · <span class="status <?php echo $result['ok'] ? '' : 'bad'; ?>"><?php echo $result['ok'] ? 'SUCCESS' : mochipay_h($result['error']); ?></span></h2>
            <h3>Request</h3><pre><?php echo mochipay_h(mochipay_pretty_json($requestPreview)); ?></pre>
            <h3>Response</h3><pre><?php echo mochipay_h(mochipay_pretty_json($result['raw'])); ?></pre>
            <?php if ($record !== null): ?>
                <?php $localQuery = http_build_query(array('view'=>$record['payload']['merchant_order_id'], 'token'=>$record['token']), '', '&', PHP_QUERY_RFC3986); ?>
                <p class="hint">Selected mode: <?php echo mochipay_h($checkoutMode); ?>. Both links use the same payment order; no second order is created.</p>
                <?php $onsiteLink = '<a class="pay-link" href="' . mochipay_h('order.php?' . $localQuery) . '">Open on-site payment dialog</a>'; $hppLink = '<a class="pay-link" target="_blank" rel="noopener" href="' . mochipay_h($record['snapshot']['payment_url']) . '">Open HPP ↗</a>'; echo $checkoutMode === 'HPP' ? $hppLink . ' ' . $onsiteLink : $onsiteLink . ' ' . $hppLink; ?>
                <?php $embeddedQuery = http_build_query(array('reference'=>$record['payload']['merchant_order_id'], 'token'=>$record['token'], 'mode'=>'ON_SITE'), '', '&', PHP_QUERY_RFC3986); $hppExampleQuery = http_build_query(array('reference'=>$record['payload']['merchant_order_id'], 'token'=>$record['token'], 'mode'=>'HPP'), '', '&', PHP_QUERY_RFC3986); ?>
                <p class="hint">Code examples using this same saved order: <a href="<?php echo mochipay_h('checkout.php?' . $embeddedQuery); ?>">Embedded store checkout</a> · <a href="<?php echo mochipay_h('checkout.php?' . $hppExampleQuery); ?>">PHP HPP redirect</a>. See checkout.php for both branches.</p>
            <?php endif; ?>
        </section>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
