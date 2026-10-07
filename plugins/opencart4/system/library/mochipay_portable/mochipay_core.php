<?php
/** MochiPay portable payment engine. PHP 5.6+ syntax; no platform credentials in HTML. */
class MochiPayPortable
{
    private $client;
    private $query;
    private $escape;
    private $table;
    public function __construct($client, $query, $escape, $table)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) throw new RuntimeException('Invalid payment table.');
        $this->client = $client; $this->query = $query; $this->escape = $escape; $this->table = '`' . $table . '`';
    }
    private function sql($s) { return call_user_func($this->query, $s); }
    private function quote($s) { return "'" . call_user_func($this->escape, (string) $s) . "'"; }
    public function install()
    {
        $this->sql('CREATE TABLE IF NOT EXISTS ' . $this->table . ' (local_id BIGINT UNSIGNED NOT NULL, reference VARCHAR(100) NOT NULL, system_id VARCHAR(32) NOT NULL DEFAULT \'\', token VARCHAR(64) NOT NULL, stage VARCHAR(20) NOT NULL, payload MEDIUMTEXT NOT NULL, snapshot MEDIUMTEXT NOT NULL, settled TINYINT NOT NULL DEFAULT 0, PRIMARY KEY(local_id), KEY system_id(system_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8');
    }
    public function get($id)
    {
        $rows = $this->sql('SELECT * FROM ' . $this->table . ' WHERE local_id=' . (int) $id . ' LIMIT 1');
        return $rows ? $rows[0] : null;
    }
    public function find($system)
    {
        $rows = $this->sql('SELECT * FROM ' . $this->table . ' WHERE system_id=' . $this->quote($system) . ' LIMIT 1');
        return $rows ? $rows[0] : null;
    }
    public function adopt($id, array $payload, array $snapshot)
    {
        // Only adapters with a trusted, previously stored mapping may call this.
        if (!self::matches($snapshot, $payload, '') || empty($snapshot['order_id'])) throw new RuntimeException('Legacy payment does not match its store order.');
        self::view($snapshot);
        $self = $this;
        return $this->locked($id, function () use ($self, $id, $payload, $snapshot) {
            $row = $self->get($id);
            if ($row) {
                if (!hash_equals((string)$row['system_id'], (string)$snapshot['order_id'])) throw new RuntimeException('Payment already belongs to another attempt.');
                return $row;
            }
            $self->sql('INSERT INTO ' . $self->table . ' (local_id, reference, system_id, token, stage, payload, snapshot) VALUES (' . (int)$id . ',' . $self->quote($payload['merchant_order_id']) . ',' . $self->quote($snapshot['order_id']) . ',' . $self->quote(self::token()) . ',\'ready\',' . $self->quote(self::json($payload)) . ',' . $self->quote(self::json($snapshot)) . ')');
            return $self->get($id);
        });
    }
    public function locked($id, $fn)
    {
        $key = 'mp_' . substr(sha1($this->table), 0, 20) . '_' . (int) $id;
        $r = $this->sql('SELECT GET_LOCK(' . $this->quote($key) . ', 0) AS acquired');
        if (!$r || (int) $r[0]['acquired'] !== 1) throw new RuntimeException('Payment is being processed. Please check again.');
        try { return call_user_func($fn); }
        finally { $this->sql('SELECT RELEASE_LOCK(' . $this->quote($key) . ') AS released'); }
    }
    public function begin($id, array $payload)
    {
        $self = $this;
        return $this->locked($id, function () use ($self, $id, $payload) {
            $row = $self->get($id);
            if (!$row) {
                $token = self::token();
                if (isset($payload['redirect_url'])) $payload['redirect_url'] = str_replace('MOCHIPAY_ATTEMPT_TOKEN', $token, $payload['redirect_url']);
                $self->sql('INSERT INTO ' . $self->table . ' (local_id, reference, token, stage, payload, snapshot) VALUES (' . (int) $id . ',' . $self->quote($payload['merchant_order_id']) . ',' . $self->quote($token) . ',\'creating\',' . $self->quote(self::json($payload)) . ',\'{}\')');
                // Commit the attempt before the network request. Uncertain responses are never reposted.
                $result = $self->client->createOrder($payload);
                $row = $self->get($id);
            } elseif ($row['system_id'] !== '') {
                return $row;
            } else {
                $result = $self->client->queryReference($row['reference']);
                $payload = json_decode($row['payload'], true);
            }
            if (!self::matches($result, $payload, '') || empty($result['order_id']) || empty($result['payment_url'])) {
                throw new RuntimeException('Payment creation needs review. Do not create another payment.');
            }
            self::view($result);
            if (!preg_match('/^[a-f0-9]{32}$/i', $result['order_id'])) throw new RuntimeException('Invalid payment identifier.');
            $self->sql('UPDATE ' . $self->table . ' SET system_id=' . $self->quote($result['order_id']) . ', stage=\'ready\', snapshot=' . $self->quote(self::json($result)) . ' WHERE local_id=' . (int) $id);
            return $self->get($id);
        });
    }
    public function check($id, $token, $settle)
    {
        $self = $this;
        return $this->locked($id, function () use ($self, $id, $token, $settle) {
            $row = $self->get($id);
            if (!$row || !$token || !hash_equals($row['token'], (string) $token) || !$row['system_id']) throw new RuntimeException('Invalid payment link.');
            $data = $self->client->queryOrder($row['system_id']);
            $payload = json_decode($row['payload'], true); $snapshot = json_decode($row['snapshot'], true);
            if (!self::matches($data, $payload, $row['system_id'])) throw new RuntimeException('Payment order does not match this store order.');
            foreach (array('payment_address', 'pay_amount') as $key) {
                if (!isset($data[$key], $snapshot[$key]) || ($key === 'pay_amount' ? self::decimal($data[$key]) !== self::decimal($snapshot[$key]) : (string) $data[$key] !== (string) $snapshot[$key])) throw new RuntimeException('Payment instructions have changed. Contact the store.');
            }
            $view = self::view($data);
            if ($view['status'] === 'PAID') {
                if (!isset($data['received_amount']) || self::decimal($data['received_amount']) !== self::decimal($data['pay_amount'])) throw new RuntimeException('Received payment requires store review.');
                if (!(int) $row['settled']) {
                    call_user_func($settle, $data, $payload);
                    $self->sql('UPDATE ' . $self->table . ' SET settled=1 WHERE local_id=' . (int) $id);
                }
            }
            return $view;
        });
    }
    public static function token()
    {
        if (function_exists('random_bytes')) return bin2hex(random_bytes(24));
        $strong = false; $bytes = openssl_random_pseudo_bytes(24, $strong);
        if (!$bytes || !$strong) throw new RuntimeException('Secure random generation is unavailable.');
        return bin2hex($bytes);
    }
    public static function json($v)
    {
        $text = json_encode($v, JSON_UNESCAPED_SLASHES);
        if ($text === false) throw new RuntimeException('Unable to encode payment data.');
        return $text;
    }
    public static function decimal($value)
    {
        $text = trim((string) $value);
        if (strlen($text) > 150 || !preg_match('/^([+-]?)([0-9]+)(?:\.([0-9]+))?(?:[eE]([+-]?[0-9]+))?$/', $text, $m)) return '';
        $fraction = isset($m[3]) ? $m[3] : ''; $power = isset($m[4]) ? (int) $m[4] : 0;
        if (abs($power) > 100) return '';
        $digits = $m[2] . $fraction; $point = strlen($m[2]) + $power;
        if ($point <= 0) { $digits = str_repeat('0', 1 - $point) . $digits; $point = 1; }
        if ($point >= strlen($digits)) $digits .= str_repeat('0', $point - strlen($digits));
        $whole = ltrim(substr($digits, 0, $point), '0'); $tail = rtrim(substr($digits, $point), '0');
        $out = ($whole === '' ? '0' : $whole) . ($tail === '' ? '' : '.' . $tail);
        return $m[1] === '-' && $out !== '0' ? '-' . $out : $out;
    }
    public static function matches($data, $payload, $id)
    {
        if (!is_array($data) || !is_array($payload)) return false;
        foreach (array('merchant_order_id', 'amount', 'currency') as $key) if (!isset($data[$key], $payload[$key])) return false;
        $method = isset($data['payment_method']) ? $data['payment_method'] : (isset($data['wallet_type'], $data['network']) ? $data['wallet_type'] . '_' . $data['network'] : '');
        return ($id === '' || (isset($data['order_id']) && hash_equals((string) $id, (string) $data['order_id']))) &&
            hash_equals((string) $payload['merchant_order_id'], (string) $data['merchant_order_id']) &&
            self::decimal($payload['amount']) !== '' && self::decimal($payload['amount']) === self::decimal($data['amount']) &&
            strtoupper($payload['currency']) === strtoupper($data['currency']) && strtoupper($payload['payment_method']) === strtoupper($method);
    }
    public static function view($data)
    {
        $method = isset($data['payment_method']) ? $data['payment_method'] : (isset($data['wallet_type'], $data['network']) ? $data['wallet_type'] . '_' . $data['network'] : '');
        $parts = explode('_', $method, 2);
        $pay = isset($data['pay_amount']) ? self::decimal($data['pay_amount']) : '';
        $address = isset($data['payment_address']) ? (string) $data['payment_address'] : '';
        $status = isset($data['status']) ? strtoupper($data['status']) : '';
        if ($pay === '' || $pay === '0' || $pay[0] === '-' || !preg_match('/^[A-Za-z0-9]{20,120}$/', $address) || count($parts) !== 2 || !in_array($status, array('PAID','PENDING','WAITING','WAITING_PAYMENT','CONFIRMING','UNDERPAID','OVERPAID','EXPIRED','CANCELLED','CANCELED'), true)) throw new RuntimeException('Payment data is incomplete or unsupported.');
        $expires = isset($data['expires_at']) ? (string) $data['expires_at'] : '';
        if (in_array($status, array('PENDING','WAITING','WAITING_PAYMENT'), true) && isset($data['updated_at']) && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $expires) && strcmp($data['updated_at'], $expires) >= 0) $status = 'EXPIRED';
        return array('reference'=>$data['order_id'], 'amount'=>self::decimal($data['amount']), 'currency'=>$data['currency'], 'payAmount'=>$pay, 'asset'=>$parts[0], 'network'=>$parts[1], 'address'=>$address, 'status'=>$status, 'expiresAt'=>$expires);
    }
    public static function page($pollUrl, $returnUrl)
    {
        $config = json_encode(array('poll'=>$pollUrl,'complete'=>$returnUrl), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $css = file_get_contents(__DIR__ . '/onsite.css'); $js = file_get_contents(__DIR__ . '/onsite.js'); $qr = file_get_contents(__DIR__ . '/qrcode.min.js');
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="referrer" content="same-origin"><title>MochiPay payment</title><style>' . $css . '</style></head><body><main><h1>Complete your payment</h1><p>Your order is saved. Reopen the dialog to continue.</p><button id="reopen">Open payment dialog</button></main><script>window.MochiPayConfig=' . $config . ';</script><script>' . $qr . '</script><script>' . $js . '</script></body></html>';
    }
}
