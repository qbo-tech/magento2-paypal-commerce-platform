<?php

namespace PayPal\CommercePlatform\Model\Paypal\Agreement;

class Delete extends \PayPalHttp\HttpRequest
{
    function __construct($tokenId)
    {
        parent::__construct("/v1/billing-agreements/agreements/{token_id}/cancel", "DELETE");

        $this->path = str_replace("{token_id}", urlencode($tokenId), $this->path);
        $this->headers["Content-Type"] = "application/json";
    }
}
