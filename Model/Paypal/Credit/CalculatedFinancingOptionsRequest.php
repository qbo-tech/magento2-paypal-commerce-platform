<?php

namespace PayPal\CommercePlatform\Model\Paypal\Credit;

class CalculatedFinancingOptionsRequest extends \PayPalHttp\HttpRequest
{
    function __construct()
    {
        parent::__construct("/v1/credit/calculated-financing-options?", "POST");

        $this->headers["Content-Type"] = "application/json";
    }
}