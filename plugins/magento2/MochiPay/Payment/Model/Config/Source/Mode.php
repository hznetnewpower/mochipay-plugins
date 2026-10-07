<?php
namespace MochiPay\Payment\Model\Config\Source;
class Mode implements \Magento\Framework\Option\ArrayInterface
{
 public function toOptionArray(){return array(array('value'=>'ON_SITE','label'=>'On-site — Payment dialog'),array('value'=>'HPP','label'=>'HPP — Redirect to MochiPay'));}
}
