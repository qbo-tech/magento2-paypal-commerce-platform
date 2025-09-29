<?php

namespace PayPal\CommercePlatform\Model\Paypal\Core;

use PayPalHttp\HttpRequest;

class AccessTokenRequest extends HttpRequest
{
    public function __construct($authorizationString, $refreshToken = null)
    {
        parent::__construct("/v1/oauth2/token", "POST");
        $this->headers["Authorization"] = "Basic " . $authorizationString;
        $body = [
            "grant_type" => 'client_credentials',
            "response_type" => 'id_token',
            "ignoreCache" => 'true'
        ];

        if (!is_null($refreshToken))
        {
            $body["grant_type"] = "refresh_token";
            $body["refresh_token"] = $refreshToken;
        }

        $this->body = $body;

        $this->headers["Content-Type"] = "application/x-www-form-urlencoded";
    }
}
