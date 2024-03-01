<?php

namespace PayPal\CommercePlatform\Model\Paypal\Core;

use PayPalHttp\HttpRequest;

class GenerateTokenRequest extends HttpRequest
{
    public function __construct($accessToken, $customerId)
    {
        parent::__construct("/v1/identity/generate-token", "POST");
        $this->headers["Authorization"] = "Bearer " . $accessToken;
        $this->headers["Content-Type"] = "application/json";
        $body = [
            "customer_id" => $customerId
        ];

        $this->body = $body;
    }
}
