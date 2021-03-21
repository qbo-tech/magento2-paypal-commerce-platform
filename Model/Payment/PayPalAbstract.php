<?php
namespace PayPal\CommercePlatform\Model\Payment;

abstract class PayPalAbstract extends \Magento\Payment\Model\Method\AbstractMethod
{
    const COMMERCE_PLATFORM_CODE = 'paypalcp';

    protected $_code = "paypalcheckout";
}
