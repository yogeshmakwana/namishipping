<?php

namespace Namicargo\Shipping\Model\Config\Source;

class Vehicle implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * Get Option Array
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
        ['value' => 'delivery', 'label' => __('Delivery')],
        ['value' => 'transportation', 'label' => __('Transportation')]
        ];
    }
}
