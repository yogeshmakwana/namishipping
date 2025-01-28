<?php

namespace Namicargo\Shipping\Model\Config\Source;

class InnerTime implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * Get Option Array
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
        ['value' => '1', 'label' => __('1')],
        ['value' => '2', 'label' => __('2')],
        ['value' => '3', 'label' => __('3')],
        ['value' => '4', 'label' => __('4')],
        ['value' => '5', 'label' => __('5')],
        ['value' => '6', 'label' => __('6')],
        ['value' => '7', 'label' => __('7')],
        ['value' => '8', 'label' => __('8')],
        ['value' => '9', 'label' => __('9')],
        ['value' => '10', 'label' => __('10')]
        ];
    }
}
