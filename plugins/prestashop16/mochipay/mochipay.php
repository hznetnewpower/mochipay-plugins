<?php

if (!defined('_PS_VERSION_')) {
    exit;
}



class Mochipay extends PaymentModule
{
    const VERSION = '1.1.0';

    public function __construct()
    {
        $this->name = 'mochipay';
        $this->tab = 'payments_gateways';
        $this->version = self::VERSION;
        $this->author = 'MochiPay';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->controllers = array('validation', 'callback', 'return', 'pay');
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        parent::__construct();
        $this->displayName = $this->l('MochiPay Cryptocurrency Payments');
        $this->description = $this->l('Accept cryptocurrency payments through an on-site dialog or hosted checkout.');
        $this->confirmUninstall = $this->l('Historical MochiPay mapping data will be retained. Continue?');
        $this->ps_versions_compliancy = array('min' => '1.6.1.0', 'max' => '1.6.99.99');
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('payment')
            && $this->registerHook('displayPaymentReturn')
            && $this->installOrderState()
            && $this->installTable()
            && $this->installDefaults();
    }


    private function installDefaults()
    {
        $values=array('MOCHIPAY_ENABLED'=>0,'MOCHIPAY_API_URL'=>'https://mochi.bz','MOCHIPAY_PAYMENT_METHODS'=>'USDT_TRC20,USDC_ERC20,BTC_BITCOIN,ETH_ERC20,SOL_SOLANA','MOCHIPAY_UNIQUE_DIRECTION'=>'UP','MOCHIPAY_CHECKOUT_MODE'=>'ON_SITE');
        foreach($values as $key=>$value) if(Configuration::get($key)===false && !Configuration::updateValue($key,$value)) return false;
        return true;
    }
    public function isEnabled()
    {
        $enabled=Configuration::get('MOCHIPAY_ENABLED');
        if($enabled===false) $enabled=(bool)Configuration::get('MOCHIPAY_API_KEY');
        return $this->active && $enabled && Configuration::get('MOCHIPAY_API_KEY') && Configuration::get('MOCHIPAY_API_SECRET');
    }

    public function uninstall()
    {
        Configuration::deleteByName('MOCHIPAY_ENABLED');
        Configuration::deleteByName('MOCHIPAY_CHECKOUT_MODE');
        Configuration::deleteByName('MOCHIPAY_API_URL');
        Configuration::deleteByName('MOCHIPAY_API_KEY');
        Configuration::deleteByName('MOCHIPAY_API_SECRET');
        Configuration::deleteByName('MOCHIPAY_PAYMENT_METHODS');
        Configuration::deleteByName('MOCHIPAY_UNIQUE_DIRECTION');
        return parent::uninstall();
    }

    public function getContent()
    {
        $output = '';
        if (Tools::isSubmit('submitMochipay')) {
            $url = rtrim(trim(Tools::getValue('MOCHIPAY_API_URL')), '/');
            $key = trim(Tools::getValue('MOCHIPAY_API_KEY'));
            $secret = trim(Tools::getValue('MOCHIPAY_API_SECRET'));
            $methods = array();
            foreach(array_keys($this->paymentMethodLabels()) as $code) if(Tools::getValue('MOCHIPAY_ASSET_'.$code)) $methods[]=$code;
            $methods = implode(',', $methods);
            $direction = strtoupper(trim(Tools::getValue('MOCHIPAY_UNIQUE_DIRECTION')));
            if ($url === '' || $key === '' || $secret === '') {
                $output .= $this->displayError($this->l('MochiPay URL, API key and API secret are required.'));
            } elseif ($methods === '') {
                $output .= $this->displayError($this->l('Enable at least one payment currency and network.'));
            } else {
                Configuration::updateValue('MOCHIPAY_ENABLED', (int)(bool)Tools::getValue('MOCHIPAY_ENABLED'));
                Configuration::updateValue('MOCHIPAY_CHECKOUT_MODE', Tools::getValue('MOCHIPAY_CHECKOUT_MODE') === 'ON_SITE' ? 'ON_SITE' : 'HPP');
                Configuration::updateValue('MOCHIPAY_API_URL', $url);
                Configuration::updateValue('MOCHIPAY_API_KEY', $key);
                Configuration::updateValue('MOCHIPAY_API_SECRET', $secret);
                Configuration::updateValue('MOCHIPAY_PAYMENT_METHODS', $methods);
                Configuration::updateValue('MOCHIPAY_UNIQUE_DIRECTION', $direction === 'DOWN' ? 'DOWN' : 'UP');
                $output .= $this->displayConfirmation($this->l('Settings saved.'));
            }
        }
        return $output . $this->renderForm();
    }

    public function hookPayment($params)
    {
        if (!$this->isEnabled() || !$this->checkCurrency($params['cart'])) return '';
        $this->context->smarty->assign(array('mochipay_action'=>$this->context->link->getModuleLink($this->name,'validation',array(),true),'mochipay_methods'=>$this->paymentMethods()));
        return $this->display(__FILE__, 'views/templates/hook/payment.tpl');
    }

    public function hookDisplayPaymentReturn($params)
    {
        return '<p>' . $this->l('Your MochiPay payment status is verified automatically. You may safely return to your account.') . '</p>';
    }

    public function client()
    {
        require_once __DIR__ . '/classes/MochiPayClient.php';
        return new MochiPayClient(
            Configuration::get('MOCHIPAY_API_URL') ?: 'https://mochi.bz',
            Configuration::get('MOCHIPAY_API_KEY'),
            Configuration::get('MOCHIPAY_API_SECRET')
        );
    }

    public function portable()
    {
        require_once __DIR__ . '/classes/portable/mochipay_core.php';
        $db = Db::getInstance();
        $service = new MochiPayPortable($this->client(), function($sql) use ($db) {
            if (preg_match('/^SELECT/i', $sql)) return $db->executeS($sql);
            if (!$db->execute($sql)) throw new RuntimeException('Unable to save payment attempt.');
            return array();
        }, function($text) { return pSQL($text, true); }, _DB_PREFIX_ . 'mochipay_attempt');
        $service->install(); return $service;
    }

    public function settle($id, $data, $payload)
    {
        $order = new Order((int)$id); $currency = new Currency((int)$order->id_currency);
        if (!Validate::isLoadedObject($order) || $order->module !== 'mochipay' || strtoupper($currency->iso_code) !== strtoupper($payload['currency']) || MochiPayPortable::decimal($order->total_paid) !== MochiPayPortable::decimal($payload['amount'])) throw new RuntimeException('Store order mismatch.');
        if ($order->hasBeenPaid()) return;
        if ((int)$order->current_state !== (int)Configuration::get('PS_OS_MOCHIPAY_WAITING')) throw new RuntimeException('Store order requires manual review.');
        $order->setCurrentState((int)Configuration::get('PS_OS_PAYMENT'));
    }

    public function paymentMethods()
    {
        $labels = array(
            'USDT_TRC20' => 'USDT — TRON (TRC20)',
            'USDC_ERC20' => 'USDC — Ethereum (ERC20)',
            'BTC_BITCOIN' => 'BTC — Bitcoin',
            'ETH_ERC20' => 'ETH — Ethereum',
            'SOL_SOLANA' => 'SOL — Solana',
        );
        $result = array();
        foreach (explode(',', (string) Configuration::get('MOCHIPAY_PAYMENT_METHODS')) as $method) {
            $method = strtoupper(trim($method));
            if (isset($labels[$method])) $result[$method] = $labels[$method];
        }
        return $result ?: array('USDT_TRC20' => $labels['USDT_TRC20']);
    }

    public function direction()
    {
        return strtoupper((string) Configuration::get('MOCHIPAY_UNIQUE_DIRECTION')) === 'DOWN' ? 'DOWN' : 'UP';
    }

    public function saveMapping($orderId, $systemOrderId, $merchantOrderId, $paymentMethod)
    {
        return Db::getInstance()->insert('mochipay_order', array(
            'id_order' => (int) $orderId,
            'mochipay_order_id' => pSQL($systemOrderId),
            'merchant_order_id' => pSQL($merchantOrderId),
            'payment_method' => pSQL($paymentMethod),
            'date_add' => date('Y-m-d H:i:s'),
        ));
    }

    private function renderForm()
    {
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitMochipay';
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->fields_value = array(
            'MOCHIPAY_ENABLED' => Configuration::get('MOCHIPAY_ENABLED')===false ? (int)(bool)Configuration::get('MOCHIPAY_API_KEY') : (int)Configuration::get('MOCHIPAY_ENABLED'),
            'MOCHIPAY_CHECKOUT_MODE' => Configuration::get('MOCHIPAY_CHECKOUT_MODE') ?: 'ON_SITE',
            'MOCHIPAY_API_URL' => Configuration::get('MOCHIPAY_API_URL') ?: 'https://mochi.bz',
            'MOCHIPAY_API_KEY' => Configuration::get('MOCHIPAY_API_KEY'),
            'MOCHIPAY_API_SECRET' => Configuration::get('MOCHIPAY_API_SECRET'),
            'MOCHIPAY_PAYMENT_METHODS' => Configuration::get('MOCHIPAY_PAYMENT_METHODS') ?: 'USDT_TRC20,USDC_ERC20,BTC_BITCOIN,ETH_ERC20,SOL_SOLANA',
            'MOCHIPAY_UNIQUE_DIRECTION' => Configuration::get('MOCHIPAY_UNIQUE_DIRECTION') ?: 'UP',
        );
        $choices = array();
        $selected = explode(',', Configuration::get('MOCHIPAY_PAYMENT_METHODS') ?: 'USDT_TRC20,USDC_ERC20,BTC_BITCOIN,ETH_ERC20,SOL_SOLANA');
        foreach(array_keys($this->paymentMethodLabels()) as $code){$choices[]=array('id'=>$code,'name'=>$code,'val'=>1);$helper->fields_value['MOCHIPAY_ASSET_'.$code]=in_array($code,$selected,true)?1:0;}
        return $helper->generateForm(array(array('form' => array(
            'legend' => array('title' => $this->l('MochiPay Settings'), 'icon' => 'icon-money'),
            'description' => $this->l('Callback URL: ') . $this->context->link->getModuleLink($this->name, 'callback', array(), true),
            'input' => array(
                array('type'=>'switch','label'=>'Enabled','name'=>'MOCHIPAY_ENABLED','is_bool'=>true,'values'=>array(array('id'=>'enabled_on','value'=>1,'label'=>'Yes'),array('id'=>'enabled_off','value'=>0,'label'=>'No'))),
                array('type'=>'select','label'=>'Payment Interface','name'=>'MOCHIPAY_CHECKOUT_MODE','options'=>array('query'=>array(array('id'=>'HPP','name'=>'HPP — Redirect to MochiPay'),array('id'=>'ON_SITE','name'=>'On-site — Payment dialog')),'id'=>'id','name'=>'name')),
                array('type' => 'text', 'label' => $this->l('MochiPay URL'), 'name' => 'MOCHIPAY_API_URL', 'required' => true),
                array('type' => 'text', 'label' => $this->l('API Key'), 'name' => 'MOCHIPAY_API_KEY', 'required' => true),
                array('type' => 'password', 'label' => $this->l('API Secret'), 'name' => 'MOCHIPAY_API_SECRET', 'required' => true),
                array('type'=>'checkbox','label'=>'Customer Payment Choices','name'=>'MOCHIPAY_ASSET','values'=>array('query'=>$choices,'id'=>'id','name'=>'name')),
                array('type' => 'select', 'label' => $this->l('Unique Amount Direction'), 'name' => 'MOCHIPAY_UNIQUE_DIRECTION', 'options' => array('query' => array(array('id' => 'UP', 'name' => 'UP'), array('id' => 'DOWN', 'name' => 'DOWN')), 'id' => 'id', 'name' => 'name')),
            ),
            'submit' => array('title' => $this->l('Save')),
        ))));
    }

    private function normalizeMethods($value)
    {
        $allowed = array_keys($this->paymentMethodLabels());
        $result = array();
        foreach (explode(',', (string) $value) as $method) {
            $method = strtoupper(trim($method));
            if (in_array($method, $allowed, true) && !in_array($method, $result, true)) $result[] = $method;
        }
        return implode(',', $result);
    }

    private function paymentMethodLabels()
    {
        return array('USDT_TRC20' => 1, 'USDC_ERC20' => 1, 'BTC_BITCOIN' => 1, 'ETH_ERC20' => 1, 'SOL_SOLANA' => 1);
    }

    private function checkCurrency(Cart $cart)
    {
        $currencyOrder = new Currency($cart->id_currency);
        $currencies = $this->getCurrency((int) $cart->id_currency);
        if (!is_array($currencies)) return false;
        foreach ($currencies as $row) if (is_array($row) && isset($row['id_currency']) && (int)$row['id_currency'] === (int)$cart->id_currency) return true;
        return false;
    }

    private function installOrderState()
    {
        if ((int) Configuration::get('PS_OS_MOCHIPAY_WAITING') > 0) return true;
        $state = new OrderState();
        $state->name = array();
        foreach (Language::getLanguages(false) as $language) $state->name[(int) $language['id_lang']] = 'Awaiting MochiPay payment';
        $state->send_email = false;
        $state->color = '#f39c12';
        $state->hidden = false;
        $state->delivery = false;
        $state->logable = false;
        $state->invoice = false;
        if (!$state->add()) return false;
        return Configuration::updateValue('PS_OS_MOCHIPAY_WAITING', (int) $state->id);
    }

    private function installTable()
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'mochipay_order` (`mochipay_order_id` VARCHAR(32) NOT NULL, `id_order` INT UNSIGNED NOT NULL, `merchant_order_id` VARCHAR(100) NOT NULL, `payment_method` VARCHAR(30) NOT NULL, `tx_hash` VARCHAR(255) NULL, `date_add` DATETIME NOT NULL, `date_paid` DATETIME NULL, PRIMARY KEY (`mochipay_order_id`), KEY `idx_order` (`id_order`), KEY `idx_merchant` (`merchant_order_id`)) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8';
        return Db::getInstance()->execute($sql);
    }
}
