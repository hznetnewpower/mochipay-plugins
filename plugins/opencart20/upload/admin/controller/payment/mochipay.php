<?php
class ControllerPaymentMochipay extends Controller
{
    private $error = array();

    public function index()
    {
        if (isset($this->request->post['mochipay_payment_methods']) && is_array($this->request->post['mochipay_payment_methods'])) $this->request->post['mochipay_payment_methods'] = implode(',', array_intersect(array('USDT_TRC20','USDC_ERC20','BTC_BITCOIN','ETH_ERC20','SOL_SOLANA'), $this->request->post['mochipay_payment_methods']));
        $this->load->language('payment/mochipay');
        $this->englishLanguage();
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');
        if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validate()) {
            $this->model_setting_setting->editSetting('mochipay', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('extension/payment', 'token=' . $this->session->data['token'] . '&type=payment', true));
        }
        $keys = array('mochipay_status', 'mochipay_title', 'mochipay_api_url', 'mochipay_api_key', 'mochipay_api_secret', 'mochipay_payment_methods', 'mochipay_unique_direction', 'mochipay_pending_status_id', 'mochipay_paid_status_id', 'mochipay_sort_order', 'mochipay_checkout_mode');
        foreach ($keys as $key) $data[$key] = isset($this->request->post[$key]) ? $this->request->post[$key] : $this->config->get($key);
        $defaults = array('mochipay_api_url'=>'https://mochi.bz','mochipay_title'=>'Cryptocurrency (MochiPay)','mochipay_payment_methods'=>'USDT_TRC20,USDC_ERC20,BTC_BITCOIN,ETH_ERC20,SOL_SOLANA','mochipay_unique_direction'=>'UP','mochipay_checkout_mode'=>'ON_SITE','mochipay_pending_status_id'=>1,'mochipay_paid_status_id'=>5,'mochipay_sort_order'=>90);
        foreach($defaults as $key=>$value) if($data[$key]===null || $data[$key]==='') $data[$key]=$value;
        $data['method_options'] = array('USDT_TRC20'=>'USDT — TRON (TRC20)','USDC_ERC20'=>'USDC — Ethereum (ERC20)','BTC_BITCOIN'=>'BTC — Bitcoin','ETH_ERC20'=>'ETH — Ethereum','SOL_SOLANA'=>'SOL — Solana');
        $data['selected_methods'] = explode(',', (string)$data['mochipay_payment_methods']);
        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
        $data['breadcrumbs'] = array(
            array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], true)),
            array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('extension/payment', 'token=' . $this->session->data['token'] . '&type=payment', true)),
            array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('payment/mochipay', 'token=' . $this->session->data['token'], true)),
        );
        $data['action'] = $this->url->link('payment/mochipay', 'token=' . $this->session->data['token'], true);
        $data['cancel'] = $this->url->link('extension/payment', 'token=' . $this->session->data['token'] . '&type=payment', true);
        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        $data['callback_url'] = HTTPS_CATALOG . 'index.php?route=payment/mochipay/callback';
        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_edit'] = $this->language->get('text_edit');
        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');
        foreach (array('status','title','api_url','api_key','api_secret','payment_methods','unique_direction','pending_status','paid_status','sort_order','checkout_mode') as $name) $data['entry_' . $name] = $this->language->get('entry_' . $name);
        $data['help_payment_methods'] = $this->language->get('help_payment_methods');
        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view(version_compare(VERSION, '2.2.0.0', '>=') ? 'payment/mochipay' : 'payment/mochipay.tpl', $data));
    }

    public function install()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "mochipay_order` (`mochipay_order_id` VARCHAR(32) NOT NULL, `order_id` INT NOT NULL, `merchant_order_id` VARCHAR(100) NOT NULL, `payment_method` VARCHAR(30) NOT NULL, `return_token` VARCHAR(64) NOT NULL, `tx_hash` VARCHAR(255) NULL, `date_added` DATETIME NOT NULL, `date_paid` DATETIME NULL, PRIMARY KEY (`mochipay_order_id`), KEY `idx_order` (`order_id`), KEY `idx_merchant` (`merchant_order_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8");
        $this->load->model('setting/setting');
        if (!$this->config->get('mochipay_api_key')) $this->model_setting_setting->editSetting('mochipay', array('mochipay_checkout_mode' => 'ON_SITE', 'mochipay_status' => 0, 'mochipay_title' => 'Cryptocurrency (MochiPay)', 'mochipay_api_url' => 'https://mochi.bz', 'mochipay_api_key' => '', 'mochipay_api_secret' => '', 'mochipay_payment_methods' => 'USDT_TRC20,USDC_ERC20,BTC_BITCOIN,ETH_ERC20,SOL_SOLANA', 'mochipay_unique_direction' => 'UP', 'mochipay_pending_status_id' => $this->config->get('config_order_status_id'), 'mochipay_paid_status_id' => 5, 'mochipay_sort_order' => 90));
    }

    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'payment/mochipay')) $this->error['warning'] = $this->language->get('error_permission');
        if (empty($this->request->post['mochipay_api_url']) || empty($this->request->post['mochipay_api_key']) || empty($this->request->post['mochipay_api_secret'])) $this->error['warning'] = $this->language->get('error_api');
        if (!(int)$this->request->post['mochipay_pending_status_id'] || !(int)$this->request->post['mochipay_paid_status_id'] || (int)$this->request->post['mochipay_pending_status_id'] === (int)$this->request->post['mochipay_paid_status_id']) $this->error['warning'] = 'Choose distinct waiting and paid order statuses.';
        if (empty($this->request->post['mochipay_payment_methods'])) $this->error['warning']='Select at least one payment currency and network.';
        return !$this->error;
    }

    private function englishLanguage()
    {
        $_ = array(); require __DIR__ . '/../../language/en-gb/payment/mochipay.php';
        foreach ($_ as $key=>$value) $this->language->set($key,$value);
    }
}
