<?php
namespace MochiPay\Payment\Model\Config\Source;
class Assets implements \Magento\Framework\Option\ArrayInterface
{
 public function toOptionArray(){ $result=array();foreach(\MochiPay\Payment\Model\ConfigProvider::methodLabels() as $code=>$label)$result[]=array('value'=>$code,'label'=>$label);return $result; }
}
