<?php

namespace PayPal\CommercePlatform\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use PayPal\CommercePlatform\Model\Config;

class ThreeDsMode implements OptionSourceInterface
{
    public function toOptionArray()
    {
        return [
            [
                'value' => Config::ACDC_3DS_MODE_MERCHANT_INITIATED,
                'label' => __('Enables 3DS starting from a specific amount.'),
            ],
            [
                'value' => Config::ACDC_3DS_MODE_RISK_INITIATED,
                'label' => __('PayPal automatically manages 3DS activation based on risk criteria.'),
            ],
        ];
    }
}
