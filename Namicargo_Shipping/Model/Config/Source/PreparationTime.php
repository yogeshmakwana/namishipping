<?php

namespace Namicargo\Shipping\Model\Config\Source;

class PreparationTime implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * Get Option Array
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
        ['value' => '5', 'label' => __('5')],
        ['value' => '10', 'label' => __('10')],
        ['value' => '15', 'label' => __('15')],
        ['value' => '20', 'label' => __('20')],
        ['value' => '25', 'label' => __('25')],
        ['value' => '30', 'label' => __('30')],
        ['value' => '35', 'label' => __('35')],
        ['value' => '40', 'label' => __('40')],
        ['value' => '45', 'label' => __('45')],
        ['value' => '50', 'label' => __('50')],
        ['value' => '55', 'label' => __('55')],
        ['value' => '60', 'label' => __('60')]
        ];
    }
}
