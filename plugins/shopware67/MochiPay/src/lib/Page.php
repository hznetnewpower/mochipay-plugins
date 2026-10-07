<?php
namespace MochiPayShared;

final class Page
{
    public static function handle(Payment $payment,array $input,$httpMethod,$settle)
    {
        $headers=['Cache-Control'=>'no-store, private','Referrer-Policy'=>'no-referrer','X-Robots-Tag'=>'noindex, nofollow','X-Content-Type-Options'=>'nosniff'];
        $action=$input['action']??'view';
        try {
            $row=$payment->authorized($input['id']??'', $input['token']??'');
            if ($action==='start') {
                if ($httpMethod!=='POST' || !hash_equals(hash_hmac('sha256','start',$row['token']),(string)($input['nonce']??''))) throw new \RuntimeException('Invalid payment submission.');
                $row=$payment->begin($row['local_id'],$row['token'],(string)($input['payment_method']??''));
                return self::redirect(Payment::url($row,$payment->config['mode']==='HPP'?'hpp':'view'),$headers);
            }
            if ($action==='notify' || $action==='poll' || $action==='return') {
                if ($action==='notify' && $httpMethod!=='POST') throw new \RuntimeException('Notification must use POST.');
                $view=$payment->check($row['local_id'],$row['token'],$settle);
                if ($action==='notify') {
                    if ($view['status']!=='PAID') throw new \RuntimeException('Payment is not confirmed.');
                    return [200,$headers+['Content-Type'=>'text/plain; charset=utf-8'],'OK'];
                }
                if ($action==='poll') return [200,$headers+['Content-Type'=>'application/json'],Payment::json(['success'=>true,'data'=>$view])];
                if ($view['status']==='PAID') return self::redirect($row['return_url'],$headers);
                return self::redirect(Payment::url($row,'view'),$headers);
            }
            if (!$row['system_id']) return [200,$headers+['Content-Type'=>'text/html; charset=utf-8'],self::choices($row)];
            if ($action==='hpp') return self::redirect($payment->client->hosted($row['snapshot']['payment_url'],$row['system_id']),$headers);
            if ($action!=='view') throw new \RuntimeException('Unknown payment action.');
            return [200,$headers+['Content-Type'=>'text/html; charset=utf-8'],self::onsite($row)];
        } catch (\Throwable $e) {
            $message='Unable to verify this saved payment. Check again or contact the merchant. Do not pay twice.';
            if ($action==='poll') return [409,$headers+['Content-Type'=>'application/json'],Payment::json(['success'=>false,'message'=>$message])];
            return [409,$headers+['Content-Type'=>'text/html; charset=utf-8'],self::shell('<h1>Payment needs attention</h1><p>'.$message.'</p>')];
        }
    }
    private static function redirect($url,array $headers) { return [303,$headers+['Location'=>$url],'']; }
    private static function e($s) { return htmlspecialchars($s,ENT_QUOTES,'UTF-8'); }
    private static function shell($content) { return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>MochiPay payment</title><style>'.file_get_contents(__DIR__.'/onsite.css').'</style></head><body><main>'.$content.'</main></body></html>'; }
    private static function choices(array $row)
    {
        $body='<h1>Select payment currency and network</h1><p>Your order total is '.self::e($row['amount'].' '.$row['currency']).'.</p><form method="post" action="'.self::e(Payment::url($row,'start')).'"><input type="hidden" name="nonce" value="'.hash_hmac('sha256','start',$row['token']).'">';
        $methods=isset($row['payload'])?[$row['payload']['payment_method']]:$row['methods'];
        foreach ($methods as $i=>$code) $body.='<label class="mp-choice"><input type="radio" name="payment_method" value="'.self::e($code).'"'.($i===0?' checked':'').'> '.self::e(Payment::labels()[$code]).'</label>';
        return self::shell($body.'<button type="submit">Continue to payment</button></form><p>Select once. Closing or switching checkout mode keeps the same saved payment.</p>');
    }
    private static function onsite(array $row)
    {
        $config=json_encode(['poll'=>Payment::url($row,'poll'),'complete'=>Payment::url($row,'return')],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
        $body='<h1>Complete your payment</h1><p>Your order is saved. Reopen the dialog to continue.</p><button type="button" id="reopen">Open payment dialog</button><a class="mp-link" href="'.self::e(Payment::url($row,'hpp')).'">Use hosted checkout</a>';
        return str_replace('</body>', '<script>window.MochiPayConfig='.$config.';</script><script>'.file_get_contents(__DIR__.'/qrcode.min.js').'</script><script>'.file_get_contents(__DIR__.'/onsite.js').'</script></body>',self::shell($body));
    }
}
