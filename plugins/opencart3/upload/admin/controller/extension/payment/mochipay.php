<?php
class ControllerExtensionPaymentMochipay extends Controller
{
    private $error = array();

    public function index()
    {
        if (isset($this->request->post['payment_mochipay_payment_methods']) && is_array($this->request->post['payment_mochipay_payment_methods'])) $this->request->post['payment_mochipay_payment_methods'] = implode(',', array_intersect(array('USDT_TRC20','USDC_ERC20','BTC_BITCOIN','ETH_ERC20','SOL_SOLANA'), $this->request->post['payment_mochipay_payment_methods']));
        $this->load->language('extension/payment/mochipay');
        $this->englishLanguage();
        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');
        if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_mochipay', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }
        $keys = array('payment_mochipay_status', 'payment_mochipay_title', 'payment_mochipay_api_url', 'payment_mochipay_api_key', 'payment_mochipay_api_secret', 'payment_mochipay_payment_methods', 'payment_mochipay_unique_direction', 'payment_mochipay_pending_status_id', 'payment_mochipay_paid_status_id', 'payment_mochipay_sort_order', 'payment_mochipay_checkout_mode');
        foreach ($keys as $key) $data[$key] = isset($this->request->post[$key]) ? $this->request->post[$key] : $this->config->get($key);
        $defaults = array('payment_mochipay_api_url'=>'https://mochi.bz','payment_mochipay_title'=>'Cryptocurrency (MochiPay)','payment_mochipay_payment_methods'=>'USDT_TRC20,USDC_ERC20,BTC_BITCOIN,ETH_ERC20,SOL_SOLANA','payment_mochipay_unique_direction'=>'UP','payment_mochipay_checkout_mode'=>'ON_SITE','payment_mochipay_pending_status_id'=>1,'payment_mochipay_paid_status_id'=>5,'payment_mochipay_sort_order'=>90);
        foreach($defaults as $key=>$value) if($data[$key]===null || $data[$key]==='') $data[$key]=$value;
        $data['method_options'] = array('USDT_TRC20'=>'USDT — TRON (TRC20)','USDC_ERC20'=>'USDC — Ethereum (ERC20)','BTC_BITCOIN'=>'BTC — Bitcoin','ETH_ERC20'=>'ETH — Ethereum','SOL_SOLANA'=>'SOL — Solana');
        $data['selected_methods'] = explode(',', (string)$data['payment_mochipay_payment_methods']);
        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
        $data['breadcrumbs'] = array(
            array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)),
            array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)),
            array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/payment/mochipay', 'user_token=' . $this->session->data['user_token'], true)),
        );
        $data['action'] = $this->url->link('extension/payment/mochipay', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);
        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        $data['callback_url'] = HTTPS_CATALOG . 'index.php?route=extension/payment/mochipay/callback';
        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_edit'] = $this->language->get('text_edit');
        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');
        foreach (array('status','title','api_url','api_key','api_secret','payment_methods','unique_direction','pending_status','paid_status','sort_order','checkout_mode') as $name) $data['entry_' . $name] = $this->language->get('entry_' . $name);
        $data['help_payment_methods'] = $this->language->get('help_payment_methods');
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('extension/payment/mochipay', $data));
    }

    public function install()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "mochipay_order` (`mochipay_order_id` VARCHAR(32) NOT NULL, `order_id` INT NOT NULL, `merchant_order_id` VARCHAR(100) NOT NULL, `payment_method` VARCHAR(30) NOT NULL, `return_token` VARCHAR(64) NOT NULL, `tx_hash` VARCHAR(255) NULL, `date_added` DATETIME NOT NULL, `date_paid` DATETIME NULL, PRIMARY KEY (`mochipay_order_id`), KEY `idx_order` (`order_id`), KEY `idx_merchant` (`merchant_order_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->load->model('setting/setting');
        if (!$this->config->get('payment_mochipay_api_key')) $this->model_setting_setting->editSetting('payment_mochipay', array('payment_mochipay_checkout_mode' => 'ON_SITE', 'payment_mochipay_status' => 0, 'payment_mochipay_title' => 'Cryptocurrency (MochiPay)', 'payment_mochipay_api_url' => 'https://mochi.bz', 'payment_mochipay_api_key' => '', 'payment_mochipay_api_secret' => '', 'payment_mochipay_payment_methods' => 'USDT_TRC20,USDC_ERC20,BTC_BITCOIN,ETH_ERC20,SOL_SOLANA', 'payment_mochipay_unique_direction' => 'UP', 'payment_mochipay_pending_status_id' => $this->config->get('config_order_status_id'), 'payment_mochipay_paid_status_id' => 5, 'payment_mochipay_sort_order' => 90));
    }

    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/payment/mochipay')) $this->error['warning'] = $this->language->get('error_permission');
        if (empty($this->request->post['payment_mochipay_api_url']) || empty($this->request->post['payment_mochipay_api_key']) || empty($this->request->post['payment_mochipay_api_secret'])) $this->error['warning'] = $this->language->get('error_api');
        if (!(int)$this->request->post['payment_mochipay_pending_status_id'] || !(int)$this->request->post['payment_mochipay_paid_status_id'] || (int)$this->request->post['payment_mochipay_pending_status_id'] === (int)$this->request->post['payment_mochipay_paid_status_id']) $this->error['warning'] = 'Choose distinct waiting and paid order statuses.';
        if (empty($this->request->post['payment_mochipay_payment_methods'])) $this->error['warning']='Select at least one payment currency and network.';
        return !$this->error;
    }

    private function englishLanguage()
    {
        $_ = array(); require __DIR__ . '/../../../language/en-gb/extension/payment/mochipay.php';
        foreach ($_ as $key=>$value) $this->language->set($key,$value);
    }
}
