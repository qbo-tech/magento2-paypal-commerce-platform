<?php

namespace PayPal\CommercePlatform\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class InstallmentsOptions implements OptionSourceInterface
{
    /**
     * Get available options
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'installments', 'label' => __('Installments')],
            ['value' => 'installments_cost_to_buyer', 'label' => __('Installments Cost to Buyer')],
            ['value' => 'no', 'label' => __('No')],
        ];
    }
}
