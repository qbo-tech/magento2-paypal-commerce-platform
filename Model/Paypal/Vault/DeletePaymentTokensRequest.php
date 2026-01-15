<?php

namespace PayPal\CommercePlatform\Model\Paypal\Vault;

class DeletePaymentTokensRequest extends \PayPalHttp\HttpRequest
{
    function __construct($tokenId)
    {
        parent::__construct("/v3/vault/payment-tokens/{token_id}", "DELETE");

        $this->path = str_replace("{token_id}", urlencode($tokenId), $this->path);
        $this->headers["Content-Type"] = "application/json";
    }
}
