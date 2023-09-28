<?php

namespace PayPal\CommercePlatform\Model\Paypal\Agreement\Financing;

use PayPalHttp\HttpRequest;

class FinancingOptionsCreateRequest extends HttpRequest
{
    function __construct()
    {
        parent::__construct("/v1/credit/calculated-financing-options", "POST");
        $this->headers["Content-Type"] = "application/json";
    }

    public function prefer($prefer)
    {
        $this->headers["Prefer"] = $prefer;
    }
}
