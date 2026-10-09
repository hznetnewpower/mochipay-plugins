<?php
class ControllerPaymentMochipay extends Controller
{
    public function index()
    {
        $this->load->language('payment/mochipay');
        $this->englishLanguage();
        $data['text_description'] = $this->language->get('text_description');
        $data['text_payment_method'] = $this->language->get('text_payment_method');
        $data['methods'] = $this->methods();
        $data['action'] = $this->url->link('payment/mochipay/confirm', '', true);
        return version_compare(VERSION, '2.2.0.0', '>=') ? $this->load->view('payment/mochipay', $data) : $this->load->view('default/template/payment/mochipay.tpl', $data);
    }

    public function confirm()
    {
        $this->load->language('payment/mochipay');
        $this->englishLanguage();
        $json = array();
        try {
            if (empty($this->session->data['payment_method']['code']) || $this->session->data['payment_method']['code'] !== 'mochipay') throw new Exception('INVALID_CHECKOUT');
            if (empty($this->session->data['order_id'])) throw new Exception('ORDER_NOT_FOUND');
            $method = isset($this->request->post['mochipay_payment_method']) ? strtoupper(trim($this->request->post['mochipay_payment_method'])) : '';
            $available_methods = $this->methods();
            if (!isset($available_methods[$method])) throw new Exception('INVALID_PAYMENT_METHOD');
            $this->load->model('checkout/order');
            $order = $this->model_checkout_order->getOrder((int) $this->session->data['order_id']);
            if (!$order) throw new Exception('ORDER_NOT_FOUND');
            $products = $this->db->query("SELECT op.name, op.quantity, op.total, p.shipping FROM `" . DB_PREFIX . "order_product` op LEFT JOIN `" . DB_PREFIX . "product` p ON p.product_id = op.product_id WHERE op.order_id='" . (int) $order['order_id'] . "'")->rows;
            $items = array();
            $physical = false;
            foreach ($products as $product) { $items[] = array('name' => $product['name'], 'quantity' => (int) $product['quantity'], 'total' => (string) $product['total']); if (!empty($product['shipping'])) $physical = true; }
            $merchantId = 'OC2-' . strtoupper(substr(md5(HTTPS_SERVER), 0, 8)) . '-' . (int) $order['order_id'];
            $service = $this->portable();
            $payload = array(
                'merchant_order_id' => $merchantId,
                'amount' => MochiPayPortable::currencyAmount($order['total'], $order['currency_code'], $order['currency_value']),
                'currency' => strtoupper($order['currency_code']),
                'payment_method' => $method,
                'unique_amount_direction' => strtoupper($this->config->get('mochipay_unique_direction')) === 'DOWN' ? 'DOWN' : 'UP',
                'source' => 'OPENCART2',
                'product_type' => $physical ? 'PHYSICAL' : 'DIGITAL_SERVICE',
                'description' => 'OpenCart 2 order #' . (int) $order['order_id'],
                'product_info' => json_encode(array('platform' => 'OpenCart 2', 'order_id' => (int) $order['order_id'], 'items' => $items), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'customer_email' => $order['email'], 'customer_phone' => $order['telephone'],
                'first_name' => $order['shipping_firstname'] ?: $order['payment_firstname'], 'last_name' => $order['shipping_lastname'] ?: $order['payment_lastname'],
                'company' => $order['shipping_company'] ?: $order['payment_company'], 'country' => $order['shipping_country'] ?: $order['payment_country'],
                'state' => $order['shipping_zone'] ?: $order['payment_zone'], 'city' => $order['shipping_city'] ?: $order['payment_city'],
                'address1' => $order['shipping_address_1'] ?: $order['payment_address_1'], 'address2' => $order['shipping_address_2'] ?: $order['payment_address_2'],
                'postal_code' => $order['shipping_postcode'] ?: $order['payment_postcode'],
                'customer_ip' => filter_var($order['ip'], FILTER_VALIDATE_IP) ? $order['ip'] : '',
                'notify_url' => $this->url->link('payment/mochipay/callback', '', true),
                'redirect_url' => $this->url->link('payment/mochipay/complete', 'id=' . (int) $order['order_id'] . '&token=MOCHIPAY_ATTEMPT_TOKEN', true),
            );
            require_once DIR_SYSTEM . 'library/mochipay.php';
            $client = new MochiPayClient($this->config->get('mochipay_api_url'), $this->config->get('mochipay_api_key'), $this->config->get('mochipay_api_secret'));
            $attempt = $service->begin((int)$order['order_id'], $payload);
            $snapshot = json_decode($attempt['snapshot'], true);
            if (!$attempt['settled'] && !(int)$order['order_status_id']) $this->model_checkout_order->addOrderHistory((int)$order['order_id'], (int)$this->config->get('mochipay_pending_status_id'), 'Awaiting MochiPay payment', false);
            $json['redirect'] = $this->config->get('mochipay_checkout_mode') === 'ON_SITE' ? $this->url->link('payment/mochipay/pay', 'id='.(int)$order['order_id'].'&token='.$attempt['token'], true) : $snapshot['payment_url'];
        } catch (Exception $exception) {
            $json['error'] = $this->language->get('error_create');
            $this->log->write('MochiPay create error: ' . $exception->getMessage());
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    private function portable()
    {
        require_once DIR_SYSTEM . 'library/mochipay.php';
        require_once DIR_SYSTEM . 'library/mochipay_portable/mochipay_core.php';
        $db = $this->db;
        $service = new MochiPayPortable(new MochiPayClient($this->config->get('mochipay_api_url'),$this->config->get('mochipay_api_key'),$this->config->get('mochipay_api_secret')),function($sql)use($db){$r=$db->query($sql);return $r->rows;},function($text)use($db){return $db->escape($text);},DB_PREFIX.'mochipay_attempt');
        $service->install(); return $service;
    }
    private function settle($id, $data, $payload)
    {
        $this->load->model('checkout/order');$order=$this->model_checkout_order->getOrder($id);
        if (!$order || $order['payment_code'] !== 'mochipay' || strtoupper($order['currency_code'])!==strtoupper($payload['currency']) || MochiPayPortable::decimal(MochiPayPortable::currencyAmount($order['total'], $order['currency_code'], $order['currency_value']))!==MochiPayPortable::decimal($payload['amount'])) throw new Exception('Store order mismatch.');
        $paid=(int)$this->config->get('mochipay_paid_status_id');$pending=(int)$this->config->get('mochipay_pending_status_id');
        if(!$paid || !$pending || $paid===$pending) throw new Exception('Configure distinct waiting and paid order statuses.');
        if((int)$order['order_status_id']===$paid)return;
        if((int)$order['order_status_id']!==$pending && (int)$order['order_status_id']!==0)throw new Exception('Store order requires manual review.');
        $this->model_checkout_order->addOrderHistory($id,$paid,'MochiPay payment confirmed. Transaction: '.(isset($data['tx_hash'])?$data['tx_hash']:$data['order_id']),true);
    }
    public function pay()
    {
        $poll=isset($this->request->get['poll']);
        $this->response->addHeader('Cache-Control: no-store');$this->response->addHeader('Referrer-Policy: same-origin');$this->response->addHeader('X-Robots-Tag: noindex, nofollow');
        try{
            $id=isset($this->request->get['id'])?(int)$this->request->get['id']:0;$token=isset($this->request->get['token'])?(string)$this->request->get['token']:'';
            $service=$this->portable();$row=$service->get($id,$token);if(!$row||!$token||!hash_equals($row['token'],$token))throw new Exception('Invalid payment link.');
            $self=$this;
            if($poll){$data=$service->check($id,$token,function($d,$p)use($self,$id){$self->settle($id,$d,$p);});$this->response->addHeader('Content-Type: application/json');$this->response->setOutput(json_encode(array('success'=>true,'data'=>$data)));}
            else{$url=$this->url->link('payment/mochipay/pay','id='.$id.'&token='.$token.'&poll=1',true);$complete=$this->url->link('payment/mochipay/complete','id='.$id.'&token='.$token,true);$this->response->setOutput(MochiPayPortable::page(html_entity_decode($url,ENT_QUOTES,'UTF-8'),html_entity_decode($complete,ENT_QUOTES,'UTF-8')));}
        }catch(Exception $e){$this->response->addHeader('HTTP/1.1 400 Bad Request');if($poll){$this->response->addHeader('Content-Type: application/json');$this->response->setOutput(json_encode(array('success'=>false,'retryable'=>mochipay_query_retryable($e),'message'=>mochipay_query_retryable($e)?'Connection failed. Your order is saved. Please check again.':$e->getMessage())));}else $this->response->setOutput(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));}
    }
    public function callback()
    {
        try{$body=json_decode(file_get_contents('php://input'),true);$system=is_array($body)&&isset($body['order_id'])?(string)$body['order_id']:'';if(!$system)throw new Exception('ORDER_ID_REQUIRED');
            $service=$this->portable();$row=$service->find($system);
            if(!$row){$old=$this->db->query("SELECT * FROM `".DB_PREFIX."mochipay_order` WHERE mochipay_order_id='".$this->db->escape($system)."' LIMIT 1");if(!$old->num_rows)throw new Exception('ORDER_NOT_FOUND');$m=$old->row;$this->load->model('checkout/order');$o=$this->model_checkout_order->getOrder((int)$m['order_id']);if(!$o)throw new Exception('ORDER_NOT_FOUND');
                $client=new MochiPayClient($this->config->get('mochipay_api_url'),$this->config->get('mochipay_api_key'),$this->config->get('mochipay_api_secret'));
                $v=$client->queryOrder($system);$row=$service->adopt((int)$m['order_id'],array('merchant_order_id'=>$m['merchant_order_id'],'amount'=>MochiPayPortable::currencyAmount($o['total'], $o['currency_code'], $o['currency_value']),'currency'=>$o['currency_code'],'payment_method'=>$m['payment_method']),$v);}
$id=(int)$row['local_id'];$self=$this;
            $data=$service->check($id,$row['token'],function($d,$p)use($self,$id){$self->settle($id,$d,$p);});if($data['status']!=='PAID')throw new Exception('ORDER_NOT_PAID');$this->response->setOutput('OK');
        }catch(Exception $e){$this->response->addHeader('HTTP/1.1 409 Conflict');$this->response->setOutput($e->getMessage());}
    }
    public function complete()
    {
        $id=isset($this->request->get['id'])?(int)$this->request->get['id']:0;$token=isset($this->request->get['token'])?(string)$this->request->get['token']:'';
        try{$service=$this->portable();$row=$service->get($id,$token);if(!$row||!$token||!hash_equals($row['token'],$token))throw new Exception('Invalid payment link.');if(!$row['settled']){$self=$this;$data=$service->check($id,$token,function($d,$p)use($self,$id){$self->settle($id,$d,$p);});if($data['status']!=='PAID')throw new Exception('Payment is not confirmed.');}$this->session->data['order_id']=$id;$this->response->redirect($this->url->link('checkout/success','',true));}
        catch(Exception $e){$this->response->redirect($this->url->link('account/order','',true));}
    }

    private function methods()
    {
        $labels = array('USDT_TRC20' => 'USDT — TRON (TRC20)', 'USDC_ERC20' => 'USDC — Ethereum (ERC20)', 'BTC_BITCOIN' => 'BTC — Bitcoin', 'ETH_ERC20' => 'ETH — Ethereum', 'SOL_SOLANA' => 'SOL — Solana');
        $result = array();
        foreach (explode(',', (string) $this->config->get('mochipay_payment_methods')) as $method) { $method = strtoupper(trim($method)); if (isset($labels[$method])) $result[$method] = $labels[$method]; }
        return $result ?: array('USDT_TRC20' => $labels['USDT_TRC20']);
    }

    private function englishLanguage()
    {
        $_ = array(); require __DIR__ . '/../../language/en-gb/payment/mochipay.php';
        foreach ($_ as $key=>$value) $this->language->set($key,$value);
    }
}
