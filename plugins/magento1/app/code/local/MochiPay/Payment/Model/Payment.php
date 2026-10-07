<?php
class MochiPay_Payment_Model_Payment extends Mage_Payment_Model_Method_Abstract
{
 protected $_code='mochipay';protected $_formBlockType='mochipay/form';protected $_isOffline=true;protected $_canAuthorize=false;protected $_canCapture=false;protected $_canUseInternal=false;
 public function assignData($data){parent::assignData($data);$method=strtoupper(trim((string)$data->getMochipayPaymentMethod()));$this->getInfoInstance()->setAdditionalInformation('mochipay_payment_method',$method);return $this;}
 public function validate(){parent::validate();$method=$this->getInfoInstance()->getAdditionalInformation('mochipay_payment_method');$labels=$this->methods();if(!isset($labels[$method]))Mage::throwException('Please select an available payment currency and network.');return $this;}
 public function getOrderPlaceRedirectUrl(){return Mage::getUrl('mochipay/payment/start',array('_secure'=>true));}
 public function methods(){ $labels=array('USDT_TRC20'=>'USDT — TRON (TRC20)','USDC_ERC20'=>'USDC — Ethereum (ERC20)','BTC_BITCOIN'=>'BTC — Bitcoin','ETH_ERC20'=>'ETH — Ethereum','SOL_SOLANA'=>'SOL — Solana');$result=array();foreach(explode(',',(string)$this->getConfigData('payment_methods'))as $m){$m=strtoupper(trim($m));if(isset($labels[$m]))$result[$m]=$labels[$m];}return $result; }
}
