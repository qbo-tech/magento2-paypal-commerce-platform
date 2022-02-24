<?php

namespace PayPal\CommercePlatform\Model\Paypal\STC;

class RiskTransactionContextRequest extends \PayPalHttp\HttpRequest
{
    function __construct($merchantId, $cmid)
    {
        parent::__construct("/v1/risk/transaction-contexts/{merchant_id}/{cmid}", "PUT");

        $this->path = str_replace("{merchant_id}", urlencode($merchantId), $this->path);
        $this->path = str_replace("{cmid}", urlencode($cmid), $this->path);
        
        $this->headers["Content-Type"] = "application/json";
    }
}
