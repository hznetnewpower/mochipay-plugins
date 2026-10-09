<?php
require_once __DIR__ . '/api.php';

function report_absolute($path)
{
    return substr($path, 0, 1) === '/' || (preg_match('/^[A-Za-z]:/', $path) && isset($path[2]) && in_array($path[2], array('/', chr(92)), true));
}
function report_directory()
{
    if (!report_absolute(REPORT_STORAGE)) throw new RuntimeException('Configure a private absolute storage directory.');
    if (!is_dir(REPORT_STORAGE) && !mkdir(REPORT_STORAGE, 0700, true) && !is_dir(REPORT_STORAGE)) throw new RuntimeException('Private storage unavailable.');
    $dir = realpath(REPORT_STORAGE);
    $root = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    if (!$dir || !$root) throw new RuntimeException('Unable to validate private storage location.');
    $a = strtolower(str_replace('\\', '/', rtrim($dir, '/\\'))) . '/';
    $b = strtolower(str_replace('\\', '/', rtrim($root, '/\\'))) . '/';
    if (strpos($a, $b) === 0) throw new RuntimeException('Storage must be outside the document root.');
    return $dir;
}
function report_configured()
{
    if (MOCHIPAY_API_KEY === 'YOUR_API_KEY' || MOCHIPAY_API_SECRET === 'YOUR_API_SECRET' || REPORT_ACCESS_CODE === '') return false;
    foreach (array(MOCHIPAY_BASE_URL, REPORT_PUBLIC_URL) as $url) {
        $u = parse_url($url);
        if (!$u || !isset($u['scheme'], $u['host']) || $u['scheme'] !== 'https' || isset($u['user']) || isset($u['pass']) || isset($u['query']) || isset($u['fragment'])) return false;
    }
    $u = parse_url(MOCHIPAY_BASE_URL);
    if ((isset($u['port']) && $u['port'] !== 443) || (isset($u['path']) && !in_array($u['path'], array('', '/'), true))) return false;
    report_directory();
    return true;
}
function report_path($reference, $suffix)
{
    if (!preg_match('/^report-[a-f0-9]{32}$/', $reference)) throw new RuntimeException('Invalid report reference.');
    return report_directory() . '/' . $reference . $suffix;
}
function report_write($reference, $record)
{
    $path = report_path($reference, '.json');
    $tmp = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
    $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) throw new RuntimeException('Unable to persist report.');
    $handle = fopen($tmp, 'xb');
    if (!$handle) throw new RuntimeException('Unable to persist report.');
    chmod($tmp, 0600);
    $ok = fwrite($handle, $json) === strlen($json) && fflush($handle);
    if ($ok && function_exists('fsync')) $ok = fsync($handle);
    fclose($handle);
    if (!$ok) { unlink($tmp); throw new RuntimeException('Unable to persist report.'); }
    if (!rename($tmp, $path)) { unlink($tmp); throw new RuntimeException('Unable to persist report.'); }
}
function report_locked($reference, $operation)
{
    $handle = fopen(report_path($reference, '.lock'), 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) throw new RuntimeException('Report is busy.');
    chmod(report_path($reference, '.lock'), 0600);
    try {
        $path = report_path($reference, '.json');
        $record = null;
        if (is_file($path)) {
            $record = json_decode(file_get_contents($path), true);
            if (!is_array($record)) throw new RuntimeException('Report record needs review.');
        }
        return $operation($record);
    } finally { flock($handle, LOCK_UN); fclose($handle); }
}
function report_load($reference)
{
    return report_locked($reference, function ($record) { return $record; });
}
function report_url($action, $reference)
{
    return rtrim(REPORT_PUBLIC_URL, '/') . '/index.php?' . http_build_query(array('action'=>$action, 'reference'=>$reference), '', '&', PHP_QUERY_RFC3986);
}
function report_binding($record, $data)
{
    if (!$record || empty($record['snapshot']) || !MochiPayPortable::matches($data, $record['payload'], $record['snapshot']['order_id'])) return false;
    return isset($data['pay_amount'], $data['payment_address'], $data['payment_url']) &&
        MochiPayPortable::decimal($data['pay_amount']) === MochiPayPortable::decimal($record['snapshot']['pay_amount']) &&
        hash_equals($record['snapshot']['payment_address'], (string)$data['payment_address']) &&
        $data['payment_url'] === rtrim(MOCHIPAY_BASE_URL, '/') . '/pay/' . $record['snapshot']['order_id'];
}
function report_begin($reference, $topic, $method, $direction)
{
    return report_locked($reference, function ($record) use ($reference, $topic, $method, $direction) {
        if ($record) {
            if ($record['topic'] !== $topic || $record['payload']['payment_method'] !== $method || $record['payload']['unique_amount_direction'] !== $direction) throw new RuntimeException('This request already has different details. Reopen it or explicitly start a new report.');
            $result = empty($record['snapshot']) ? mochipay_create_order($record['payload']) : mochipay_query_order('order_id', $record['snapshot']['order_id']);
        } else {
            $record = array('topic'=>$topic, 'snapshot'=>array(), 'stage'=>'creating', 'unlocked_at'=>null, 'report'=>null, 'callback_token'=>bin2hex(random_bytes(24)), 'created_at'=>gmdate('c'));
            $record['payload'] = array('request_id'=>'report-'.substr(hash('sha256', $reference),0,56), 'merchant_order_id'=>$reference, 'amount'=>REPORT_PRICE, 'currency'=>REPORT_CURRENCY, 'payment_method'=>$method, 'unique_amount_direction'=>$direction, 'product_type'=>'DIGITAL', 'description'=>'Sample report access', 'redirect_url'=>report_url('return', $reference), 'notify_url'=>rtrim(REPORT_PUBLIC_URL,'/').'/callback.php?'.http_build_query(array('reference'=>$reference,'token'=>$record['callback_token']),'','&',PHP_QUERY_RFC3986));
            // Persist before POST; retries reuse this exact request ID and payload.
            report_write($reference, $record);
            $result = mochipay_create_order($record['payload']);
        }
        if (!$result['ok']) throw new RuntimeException('Payment request needs verification. Retry this same report; do not create another one to bypass a timeout.');
        $data = $result['data'];
        if (!MochiPayPortable::matches($data, $record['payload'], '') || !isset($data['order_id']) || !preg_match('/^[a-f0-9]{32}$/', $data['order_id'])) throw new RuntimeException('Payment binding needs review.');
        MochiPayPortable::view($data);
        if ($data['payment_url'] !== rtrim(MOCHIPAY_BASE_URL,'/').'/pay/'.$data['order_id'] || (!empty($record['snapshot']) && !report_binding($record, $data))) throw new RuntimeException('Payment instructions changed.');
        $record['snapshot'] = array('order_id'=>$data['order_id'], 'payment_address'=>$data['payment_address'], 'pay_amount'=>MochiPayPortable::decimal($data['pay_amount']));
        $record['stage']='ready';
        report_write($reference, $record);
        return $record;
    });
}
function report_sample($topic)
{
    return "SAMPLE REPORT — NOT GENERATED BY A LIVE AI MODEL\n\nTopic: " . $topic . "\n\n1. Define the outcome\nIdentify the intended audience and the decision this report should support.\n\n2. Gather evidence\nUse relevant primary sources, record their dates, and separate verified facts from assumptions.\n\n3. Build an action plan\nRank the next three actions by expected benefit, effort, and dependencies.\n\n4. Measure results\nChoose one success metric, establish a baseline, and review progress after implementation.\n\nThis reusable sample demonstrates paid access. It does not contain live AI-generated analysis.";
}
function report_verify($reference)
{
    return report_locked($reference, function ($record) use ($reference) {
        if (!$record || empty($record['snapshot'])) throw new RuntimeException('Saved payment is not ready. Retry the original request.');
        $response = mochipay_query_order('order_id', $record['snapshot']['order_id']);
        if (!$response['ok'] && !empty($response['retryable'])) throw new MochiPayDemoQueryUnavailable('Connection failed. Your order is saved. Please check again.');
        if (!$response['ok'] || !report_binding($record, $response['data'])) throw new RuntimeException('Unable to verify the saved payment.');
        $data = $response['data'];
        $paid = $data['status'] === 'PAID' && isset($data['received_amount']) &&
            MochiPayPortable::decimal($data['received_amount']) === MochiPayPortable::decimal($record['snapshot']['pay_amount']);
        $view = MochiPayPortable::view($data);
        if ($view['status'] === 'PAID' && !$paid) $view['status'] = 'REVIEW_REQUIRED';
        if ($paid && $record['unlocked_at'] === null) {
            // Under the same per-order lock, persist the result once. Never use browser/callback status.
            $record['report'] = report_sample($record['topic']);
            $record['unlocked_at'] = gmdate('c');
            $record['stage'] = 'unlocked';
            report_write($reference, $record);
        }
        return array('paid'=>$paid, 'data'=>$view, 'record'=>$record);
    });
}
