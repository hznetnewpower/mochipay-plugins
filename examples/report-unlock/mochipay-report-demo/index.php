<?php
require_once __DIR__ . '/report-lib.php';
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_secure', '1');
// PHP 7.0–7.2 do not expose session.cookie_samesite.
if (PHP_VERSION_ID < 70300) session_set_cookie_params(0, '/; SameSite=Lax', '', true, true);
session_name('mochipay_report_demo');
session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(24));
if (empty($_SESSION['draft'])) $_SESSION['draft']='report-'.bin2hex(random_bytes(16));
if (empty($_SESSION['orders'])) $_SESSION['orders']=array();
$action=isset($_GET['action']) && is_string($_GET['action']) ? $_GET['action'] : '';
$reference=isset($_GET['reference']) && is_string($_GET['reference']) ? $_GET['reference'] : '';
$error='';$configured=false;$verified=null;$record=null;$mode=REPORT_DEFAULT_MODE;
try { $configured=report_configured(); } catch (Exception $e) { $configured=false; }
function report_json($status, $data) { http_response_code($status);header('Content-Type: application/json');echo json_encode($data,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);exit; }
function report_post($key) { return isset($_POST[$key]) && is_string($_POST[$key]) ? $_POST[$key] : ''; }
try {
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        if (!hash_equals($_SESSION['csrf'],report_post('csrf'))) throw new RuntimeException('Refresh the page and try again.');
        if (report_post('operation')==='login') {
            if (!$configured || !hash_equals(REPORT_ACCESS_CODE, report_post('access_code'))) throw new RuntimeException('Unable to open the demo.');
            session_regenerate_id(true);$_SESSION['authorized']=true;
            header('Location: index.php',true,303);exit;
        }
        if (!$configured || empty($_SESSION['authorized'])) throw new RuntimeException('Open the demo with your access code first.');
        if (report_post('operation')==='new') { $_SESSION['draft']='report-'.bin2hex(random_bytes(16));header('Location: index.php',true,303);exit; }
        if (report_post('operation')==='create') {
            $reference=$_SESSION['draft'];$topic=trim(report_post('topic'));$method=report_post('payment_method');$mode=report_post('checkout_mode');$direction=report_post('direction');
            if (strlen($topic)<3 || strlen($topic)>180 || !in_array($method,explode(',',REPORT_METHODS),true) || !in_array($mode,array('ON_SITE','HPP'),true) || !in_array($direction,array('UP','DOWN'),true)) throw new RuntimeException('Check the topic and payment options.');
            // Assign session ownership BEFORE creating the remote order.
            $_SESSION['orders'][$reference]=true;
            $record=report_begin($reference,$topic,$method,$direction);
            header('Location: index.php?'.http_build_query(array('action'=>'checkout','reference'=>$reference,'mode'=>$mode),'','&',PHP_QUERY_RFC3986),true,303);exit;
        }
        throw new RuntimeException('Unsupported action.');
    }
    if ($action!=='') {
        if (!$configured || empty($_SESSION['authorized']) || empty($_SESSION['orders'][$reference])) throw new RuntimeException('This report is not available in your current session.');
        $record=report_load($reference);
        if (!$record || empty($record['snapshot'])) throw new RuntimeException('Retry the original report request to recover payment.');
        $verified=report_verify($reference);$record=$verified['record'];
        if ($action==='status') report_json(200,array('success'=>true,'data'=>$verified['data']));
        if ($action==='download') {
            if (!$verified['paid']) throw new RuntimeException('Payment has not been verified.');
            header('Content-Type: text/plain; charset=utf-8');header('Content-Disposition: attachment; filename="mochipay-sample-report.txt"');echo $record['report'];exit;
        }
        if ($action==='checkout') {
            $mode=isset($_GET['mode']) && is_string($_GET['mode']) ? $_GET['mode'] : REPORT_DEFAULT_MODE;
            if (!in_array($mode,array('ON_SITE','HPP'),true)) throw new RuntimeException('Unsupported checkout mode.');
            if ($verified['paid']) { header('Location: '.report_url('return',$reference),true,303);exit; }
            if ($mode==='HPP') { header('Location: '.rtrim(MOCHIPAY_BASE_URL,'/').'/pay/'.$record['snapshot']['order_id'],true,303);exit; }
        } elseif (!in_array($action,array('return','view'),true)) throw new RuntimeException('Unsupported action.');
    }
} catch (Exception $e) {
    if ($action==='status') report_json(403,array('success'=>false,'message'=>'Unable to verify this report payment.'));
    if ($action==='download') { http_response_code(403);header('Content-Type: text/plain');echo 'Report access denied.';exit; }
    $error=$e->getMessage();http_response_code(400);
}
$logged=$configured && !empty($_SESSION['authorized']);
$paid=$verified && $verified['paid'];
$showDialog=$error==='' && $action==='checkout' && $mode==='ON_SITE' && !$paid;
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Report Unlock Demo · MochiPay</title><link rel="stylesheet" href="portable/onsite.css?v=78"><link rel="stylesheet" href="report.css"></head><body>
<main class="report-shell"><a class="brand-link" href="index.php">MOCHIPAY · REPORT UNLOCK DEMO</a><span class="sample-label">SAMPLE CONTENT · NO LIVE AI MODEL</span>
<h1>A report request.<br>A verified payment.<br>Your unlocked result.</h1><p class="intro">Try paid access to a sample report with on-site or hosted checkout.</p>
<?php if ($error!==''): ?><p class="notice" role="alert"><?php echo mochipay_h($error); ?></p><?php endif; ?>
<?php if (!$configured): ?><section class="report-card"><h2>Configure your private demo</h2><p>Set merchant credentials, the public HTTPS URL, a persistent directory outside the document root, and a demo access code. Follow the included README.</p></section>
<?php elseif (!$logged): ?><form class="report-card" method="post"><h2>Open the demo</h2><label>Demo access code<input type="password" name="access_code" required autocomplete="off"></label><input type="hidden" name="csrf" value="<?php echo mochipay_h($_SESSION['csrf']); ?>"><input type="hidden" name="operation" value="login"><button>Continue</button></form>
<?php elseif ($record && $error===''): ?><section class="report-card"><span class="eyebrow"><?php echo $paid ? 'PAYMENT VERIFIED' : 'SAVED REPORT REQUEST'; ?></span><h2><?php echo mochipay_h($record['topic']); ?></h2><p>Order total: <strong><?php echo mochipay_h(REPORT_PRICE.' '.REPORT_CURRENCY); ?></strong></p>
<?php if ($paid): ?><p>Your payment was verified on the server. The same saved result is available when you return.</p><pre class="report-content"><?php echo mochipay_h($record['report']); ?></pre><a class="button-link" href="<?php echo mochipay_h(report_url('download',$reference)); ?>">Download report</a>
<?php else: ?><p>Current status: <strong><?php echo mochipay_h($verified['data']['status']); ?></strong></p><p>The full sample stays private until payment is verified. Switching checkout modes keeps this order.</p><div class="report-actions"><a class="button-link" href="index.php?<?php echo mochipay_h(http_build_query(array('action'=>'checkout','reference'=>$reference,'mode'=>'ON_SITE'),'','&',PHP_QUERY_RFC3986)); ?>">Pay on-site</a><a class="button-link secondary" href="index.php?<?php echo mochipay_h(http_build_query(array('action'=>'checkout','reference'=>$reference,'mode'=>'HPP'),'','&',PHP_QUERY_RFC3986)); ?>">Open hosted checkout</a><a href="<?php echo mochipay_h(report_url('return',$reference)); ?>">Check payment again</a></div><?php endif; ?>
<?php if ($showDialog): ?><button id="reopen" type="button">Open payment dialog</button><?php endif; ?></section>
<?php else: ?><form class="report-card" method="post"><h2>Request a sample report</h2><label>Report topic<input name="topic" maxlength="180" minlength="3" placeholder="A launch plan for my online store" required value="<?php echo mochipay_h(report_post('topic')); ?>"></label><div class="form-grid"><label>Payment asset<select name="payment_method"><?php foreach (explode(',',REPORT_METHODS) as $method): ?><option value="<?php echo mochipay_h($method); ?>"<?php echo report_post('payment_method')===$method ? ' selected' : ''; ?>><?php echo mochipay_h(str_replace('_',' · ',$method)); ?></option><?php endforeach; ?></select></label><label>Checkout mode<select name="checkout_mode"><option value="ON_SITE">On-site (default)</option><option value="HPP"<?php echo report_post('checkout_mode')==='HPP' ? ' selected' : ''; ?>>Hosted payment page</option></select></label><label>Unique amount direction<select name="direction"><option value="UP">Up (default)</option><option value="DOWN"<?php echo report_post('direction')==='DOWN' ? ' selected' : ''; ?>>Down</option></select></label></div><p class="price">Report access · <?php echo mochipay_h(REPORT_PRICE.' '.REPORT_CURRENCY); ?></p><p class="muted">Preview: define an outcome, gather evidence, plan actions and measure results. This sample is a payment integration example.</p><input type="hidden" name="csrf" value="<?php echo mochipay_h($_SESSION['csrf']); ?>"><input type="hidden" name="operation" value="create"><button>Create payment request</button><p class="muted">If creation is uncertain, submit the same topic/options again to query the saved request.</p></form><?php endif; ?>
<?php if ($logged): ?><section class="report-card"><h2>Your saved requests</h2><?php foreach ($_SESSION['orders'] as $ref=>$owned): ?><p><a href="<?php echo mochipay_h(report_url('view',$ref)); ?>"><?php echo mochipay_h($ref); ?></a></p><?php endforeach; ?><form method="post"><input type="hidden" name="csrf" value="<?php echo mochipay_h($_SESSION['csrf']); ?>"><input type="hidden" name="operation" value="new"><button class="secondary">Start a separate new report</button></form></section><?php endif; ?>
<p class="footer-note">Your merchant server verifies payment before delivery. This example uses sample content; connect your AI provider in your own application.</p></main>
<?php if ($showDialog): ?><script>window.MochiPayConfig=<?php echo json_encode(array('poll'=>report_url('status',$reference),'complete'=>report_url('return',$reference)),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;</script><script src="portable/qrcode.min.js"></script><script src="portable/onsite.js?v=78"></script><?php endif; ?></body></html>
