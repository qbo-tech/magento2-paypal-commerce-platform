<?php
namespace PayPal\CommercePlatform\Model\Payment;

abstract class PayPalAbstract extends \Magento\Payment\Model\Method\AbstractMethod
{
    const COMMERCE_PLATFORM_CODE = 'paypalcp';

    /** const xml path */

    const XML_PATH_ACTIVE = 'payment/paypalcp/active';

    protected $_code = self::COMMERCE_PLATFORM_CODE;

}
