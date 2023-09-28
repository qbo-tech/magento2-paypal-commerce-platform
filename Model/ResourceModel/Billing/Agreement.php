<?php

namespace PayPal\CommercePlatform\Model\ResourceModel\Billing;

/**
 * Billing agreement resource model
 */
class Agreement extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{

    protected $_isPkAutoIncrement = false;
    /**
     * Resource initialization
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('paypal_commerce_billing_agreement', 'agreement_id');
    }

}
