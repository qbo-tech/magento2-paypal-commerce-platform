<?php

namespace PayPal\CommercePlatform\Model\Paypal\Webhooks;

class VerifyWebhookSignatureRequest extends \PayPalHttp\HttpRequest
{
    function __construct()
    {
        parent::__construct("/v1/notifications/verify-webhook-signature?", "POST");
        $this->headers["Content-Type"] = "application/json";
    }
}