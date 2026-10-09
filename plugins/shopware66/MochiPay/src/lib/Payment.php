<?php
namespace MochiPayShared;

final class Payment
{
    public $store, $client, $config;
    public static function defaults() { return ['enabled'=>false,'url'=>'https://mochi.bz','key'=>'','secret'=>'','mode'=>'ON_SITE','direction'=>'UP','methods'=>array_keys(self::labels())]; }
    public static function labels() { return ['USDT_TRC20'=>'USDT — TRON (TRC20)','USDC_ERC20'=>'USDC — Ethereum (ERC20)','BTC_BITCOIN'=>'BTC — Bitcoin','ETH_ERC20'=>'ETH — Ethereum','SOL_SOLANA'=>'SOL — Solana']; }
    public function __construct(Store $store, array $config, $client = null)
    {
        $this->store=$store; $this->config=$config+self::defaults();
        if (!$this->config['enabled']) throw new \RuntimeException('MochiPay is not enabled.');
        $this->config['methods'] = array_values(array_intersect(array_keys(self::labels()), (array)$this->config['methods']));
        if (!$this->config['methods'] || !in_array($this->config['mode'], ['ON_SITE','HPP'], true) || !in_array($this->config['direction'], ['UP','DOWN'], true)) throw new \RuntimeException('Invalid MochiPay settings.');
        $this->client=$client ?: new Client($this->config);
    }
    public function prepare($id, $amount, $currency, $returnUrl, $endpoint, $platform, array $extra = [])
    {
        if (!preg_match('/^[A-Za-z0-9._:-]{1,120}$/D', (string)$id)) throw new \RuntimeException('Invalid local order.');
        $amount=self::decimal($amount); $currency=strtoupper($currency);
        if ($amount==='' || $amount==='0' || $amount[0]==='-' || !preg_match('/^[A-Z0-9]{2,20}$/D',$currency)) throw new \RuntimeException('Invalid order total or currency.');
        self::localUrl($returnUrl,$endpoint);
        $self=$this;
        return $this->store->locked($id,function()use($self,$id,$amount,$currency,$returnUrl,$endpoint,$platform,$extra){
            $token=bin2hex(random_bytes(24));
            $returnUrl=str_replace('MOCHIPAY_ATTEMPT_TOKEN',$token,$returnUrl);
            $row=['local_id'=>(string)$id,'amount'=>$amount,'currency'=>$currency,'token'=>$token,'system_id'=>'','fingerprint'=>$self->client->fingerprint(),'return_url'=>$returnUrl,'endpoint'=>$endpoint,'methods'=>$self->config['methods'],'stage'=>'selected','settled'=>false,'platform'=>$platform,'extra'=>$extra];
            $self->store->insert($id,$row); return $row;
        });
    }
    private function owner(array $row) { if (!hash_equals($row['fingerprint'],$this->client->fingerprint())) throw new \RuntimeException('Payment belongs to another merchant configuration.'); }
    public function authorized($id,$token)
    {
        $row=$this->store->get($id,$token);
        if (!$row || !is_string($token) || $token==='' || !hash_equals($row['token'],$token)) throw new \RuntimeException('Invalid payment link.');
        $this->owner($row); return $row;
    }
    public function begin($id,$token,$method)
    {
        $self=$this;
        return $this->store->locked($id,function()use($self,$id,$token,$method){
            $row=$self->authorized($id,$token);
            if (isset($row['payload']) && ($row['stage']??'')!=='rejected' && $method!==$row['payload']['payment_method']) throw new \RuntimeException('This order already has a payment method. Do not create another payment.');
            if ($row['system_id']!=='') return $row;
            if (($row['stage']??'')==='rejected') unset($row['payload']);
            $resume = isset($row['payload']);
            if (!isset($row['payload'])) {
                if (!in_array($method,$row['methods'],true) || !in_array($method,$self->config['methods'],true)) throw new \RuntimeException('Payment method is unavailable.');
                $reference='MP-'.substr(hash('sha256',$row['fingerprint'].':'.$row['platform']),0,12).'-'.$id;
                if (strlen($reference)>100) $reference='MP-'.hash('sha256',$reference);
                $row['payload']=['request_id'=>'mp:'.bin2hex(random_bytes(24)),'merchant_order_id'=>$reference,'amount'=>$row['amount'],'currency'=>$row['currency'],'payment_method'=>$method,'unique_amount_direction'=>$self->config['direction'],'description'=>$row['platform'].' order '.$id,'product_type'=>$row['extra']['product_type']??'PHYSICAL','notify_url'=>self::url($row,'notify'),'redirect_url'=>self::url($row,'return')];
                // Store API payload before transport. Keep byte-equivalent retries on Web65+.
                $row['stage']='creating'; $self->store->save($id,$row);
            }
            $creating=false;
            try {
                // The persisted request ID alone makes a retry idempotent; references may repeat.
                $creating=true; $data=$self->client->create($row['payload']);
            } catch (ApiException $e) {
                if ($creating && $e->isRejected()) { $row['stage']='rejected'; $self->store->save($id,$row); }
                throw $e;
            }
            self::matches($data,$row['payload'],''); self::view($data);
            $self->client->hosted($data['payment_url']??'', $data['order_id']??'');
            $row['system_id']=$data['order_id']; $row['snapshot']=$data; $row['stage']='ready';
            $self->store->save($id,$row); return $row;
        });
    }
    public function check($id,$token,$settle)
    {
        $self=$this;
        return $this->store->locked($id,function()use($self,$id,$token,$settle){
            $row=$self->authorized($id,$token);
            if (!$row['system_id']) throw new \RuntimeException('Payment setup is incomplete. Continue the saved payment.');
            $data=$self->client->query($row['system_id']); self::matches($data,$row['payload'],$row['system_id']);
            $snapshot=$row['snapshot'];
            if (($data['payment_address']??'')!==$snapshot['payment_address'] || self::decimal($data['pay_amount']??'')!==self::decimal($snapshot['pay_amount'])) throw new \RuntimeException('Payment instructions changed. Contact the merchant.');
            $view=self::view($data);
            if ($view['status']==='PAID') {
                if (self::decimal($data['received_amount']??'')!==self::decimal($snapshot['pay_amount'])) throw new \RuntimeException('Received amount requires merchant review.');
                if (!$row['settled']) {
                    // Native adapter checks the current total, currency, gateway and state.
                    // It must accept an already recorded identical payment on retries.
                    call_user_func($settle,$row,$data);
                    $row['settled']=true; $self->store->save($id,$row);
                }
            }
            return $view;
        });
    }
    public static function matches(array $data,array $payload,$id)
    {
        $method=$data['payment_method']??(($data['wallet_type']??'').'_' .($data['network']??''));
        $parts=explode('_',$payload['payment_method'],2);if((isset($data['wallet_type'])&&$data['wallet_type']!==$parts[0])||(isset($data['network'])&&$data['network']!==$parts[1]))throw new \RuntimeException('Payment asset or network does not match.');
        if (!preg_match('/^[a-f0-9]{32}$/iD',$data['order_id']??'') || ($id!==''&&!hash_equals($id,$data['order_id'])) || ($data['merchant_order_id']??'')!==$payload['merchant_order_id'] || self::decimal($data['amount']??'')!==self::decimal($payload['amount']) || ($data['currency']??'')!==$payload['currency'] || $method!==$payload['payment_method']) throw new \RuntimeException('Payment does not match this store order.');
    }
    public static function view(array $data)
    {
        $method=$data['payment_method']??(($data['wallet_type']??'').'_' .($data['network']??''));
        $parts=explode('_',$method,2); $pay=self::decimal($data['pay_amount']??''); $status=strtoupper($data['status']??''); $address=$data['payment_address']??'';
        if (!isset(self::labels()[$method]) || $pay==='' || $pay==='0' || $pay[0]==='-' || !is_string($address) || !preg_match('/^[A-Za-z0-9]{20,120}$/D',$address) || !in_array($status,['PAID','PENDING','WAITING','WAITING_PAYMENT','CONFIRMING','UNDERPAID','OVERPAID','REVIEW_REQUIRED','EXPIRED','CANCELLED','CANCELED','FAILED'],true)) throw new \RuntimeException('Payment details are unavailable.');
        return ['reference'=>$data['order_id'],'amount'=>self::decimal($data['amount']),'currency'=>$data['currency'],'payAmount'=>$pay,'asset'=>$parts[0],'network'=>$parts[1],'address'=>$address,'status'=>$status,'expiresAt'=>(string)($data['expires_at']??'')];
    }
    public static function url(array $row,$action)
    {
        return $row['endpoint'].(strpos($row['endpoint'],'?')===false?'?':'&').http_build_query(['id'=>$row['local_id'],'token'=>$row['token'],'action'=>$action],'','&',PHP_QUERY_RFC3986);
    }
    public static function localUrl($url,$endpoint)
    {
        $u=parse_url($url); $e=parse_url($endpoint);
        if (!$u || !$e || ($u['scheme']??'')!=='https' || ($e['scheme']??'')!=='https' || strtolower($u['host']??'')!==strtolower($e['host']??'') || ($u['port']??443)!==($e['port']??443) || isset($u['user']) || isset($u['pass']) || isset($e['user']) || isset($e['pass']) || isset($u['fragment']) || isset($e['fragment'])) throw new \RuntimeException('Checkout and return URLs must use the same HTTPS store origin.');
    }
    public static function decimal($value)
    {
        if (!is_string($value) && !is_int($value)) return '';
        $text=trim((string)$value);
        if (strlen($text)>150 || !preg_match('/^([+-]?)([0-9]+)(?:\.([0-9]+))?(?:[eE]([+-]?[0-9]+))?$/D',$text,$m)) return '';
        $fraction=$m[3]??''; $power=(int)($m[4]??0); if(abs($power)>100)return '';
        $digits=$m[2].$fraction; $point=strlen($m[2])+$power;
        if($point<=0){$digits=str_repeat('0',1-$point).$digits;$point=1;}
        if($point>=strlen($digits))$digits.=str_repeat('0',$point-strlen($digits));
        $whole=ltrim(substr($digits,0,$point),'0');$tail=rtrim(substr($digits,$point),'0');$out=($whole===''?'0':$whole).($tail===''?'':'.'.$tail);
        return $m[1]==='-'&&$out!=='0'?'-'.$out:$out;
    }
    public static function json($data) { $s=json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);return $s; }
}
