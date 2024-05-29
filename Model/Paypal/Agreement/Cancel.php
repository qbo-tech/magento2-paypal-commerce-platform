<?php

namespace PayPal\CommercePlatform\Model\Paypal\Agreement;

class Cancel extends \PayPalHttp\HttpRequest
{
    function __construct($agreementId)
    {
        parent::__construct("/v1/billing-agreements/agreements/{agreement_id}/cancel", "POST");

        $this->path = str_replace("{agreement_id}", urlencode($agreementId), $this->path);
        $this->headers["Content-Type"] = "application/json";
    }
}
