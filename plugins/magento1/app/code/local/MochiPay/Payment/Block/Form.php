<?php
class MochiPay_Payment_Block_Form extends Mage_Payment_Block_Form
{
 protected function _construct(){parent::_construct();$this->setTemplate('mochipay/form.phtml');}
 public function methods(){return Mage::getModel('mochipay/payment')->methods();}
}
