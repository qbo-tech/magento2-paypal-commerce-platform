<?php

namespace PayPal\CommercePlatform\Model\Paypal\Agreement\Token;

use PayPalHttp\HttpRequest;

class AgreementTokenCreateRequest extends HttpRequest
{
    function __construct()
    {
        parent::__construct("/v1/billing-agreements/agreement-tokens?", "POST");
        $this->headers["Content-Type"] = "application/json";
    }
    public function prefer($prefer)
    {
        $this->headers["Prefer"] = $prefer;
    }
}
