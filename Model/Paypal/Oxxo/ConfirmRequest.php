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

namespace PayPal\CommercePlatform\Model\Paypal\Oxxo;

/**
 * Class ConfirmRequest
 * @package PayPal\CommercePlatform\Model\Paypal\Oxxo
 */
class ConfirmRequest extends \PayPalHttp\HttpRequest
{
    /**
     * Create base curl request to confirm order
     * @param $orderId
     */
    function __construct($orderId)
    {
        parent::__construct("/v2/checkout/orders/{order_id}/confirm-payment-source", "POST");
        $this->path = str_replace("{order_id}", $orderId, $this->path);
        $this->headers["Content-Type"] = "application/json";
    }
}
