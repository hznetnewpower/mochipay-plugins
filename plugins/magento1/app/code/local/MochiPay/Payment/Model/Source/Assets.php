<?php
class MochiPay_Payment_Model_Source_Assets
{
 public function toOptionArray(){ $codes=array('USDT_TRC20','USDC_ERC20','BTC_BITCOIN','ETH_ERC20','SOL_SOLANA');$result=array();foreach($codes as $code)$result[]=array('value'=>$code,'label'=>$code);return $result; }
}
