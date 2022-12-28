<?php

namespace PayPal\CommercePlatform\Plugin\Payment\Block;

/**
 * Override default.phtml to show the correct payment method title in frontend
 */
class Info
{
    /**
     * @param \Magento\Payment\Block\Info $subject
     * @return void
     */
    public function beforeToHtml(\Magento\Payment\Block\Info $subject)
    {
        $subject->setTemplate('PayPal_CommercePlatform::info/default.phtml');
    }
}