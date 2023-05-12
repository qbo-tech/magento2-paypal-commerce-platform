<?php

namespace PayPal\CommercePlatform\Model\Paypal\Agreement;

use PayPalHttp\HttpRequest;

class AgreementCreateRequest extends HttpRequest
{
    function __construct($tokenId)
    {
        parent::__construct(sprintf("/v1/billing-agreements/%s/agreements?", $tokenId), "POST");
        $this->headers["Content-Type"] = "application/json";
    }

    public function prefer($prefer)
    {
        $this->headers["Prefer"] = $prefer;
    }
}
