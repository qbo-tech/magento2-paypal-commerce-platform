<?php

namespace PayPal\CommercePlatform\Model\Paypal\Vault;

class PaymentTokensRequest extends \PayPalHttp\HttpRequest
{
    function __construct($customerId)
    {
        parent::__construct("/v2/vault/payment-tokens?customer_id={customer_id}", "GET");

        $this->path = str_replace("{customer_id}", urlencode($customerId), $this->path);
        $this->headers["Content-Type"] = "application/json";
    }
}