<?php

namespace MochiPay\Payment\Model\Config\Source;

class Direction implements \Magento\Framework\Option\ArrayInterface
{
    public function toOptionArray()
    {
        return array(
            array('value' => 'UP', 'label' => __('Increase slightly (UP)')),
            array('value' => 'DOWN', 'label' => __('Decrease slightly (DOWN)')),
        );
    }
}
