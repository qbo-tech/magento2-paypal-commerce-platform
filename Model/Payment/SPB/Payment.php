<?php
/**
 * Paypal SmartPaymentButton and Advanced credit and debit card payments for MX
 * Copyright (C) 2019
 *
 * This file included in Qbo/PaypalCheckout is licensed under OSL 3.0
 *
 * http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * Please see LICENSE.txt for the full text of the OSL 3.0 license
 */

namespace PayPal\CommercePlatform\Model\Payment\SPB;

class Payment extends \PayPal\CommercePlatform\Model\Payment\Advanced\Payment
{
    const CODE = 'paypalspb';

    protected $_code = self::CODE;
}
