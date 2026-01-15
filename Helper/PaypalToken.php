<?php

namespace PayPal\CommercePlatform\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use PayPal\CommercePlatform\Model\Paypal\Core\Token;

class PaypalToken extends AbstractHelper
{

    protected $paypalAccessTokenRequest;

    /**
     * @param Context $context
     * @param Token $paypalAccessTokenRequest
     */
    public function __construct(
        Context $context,
        Token $paypalAccessTokenRequest
    ) {
        $this->paypalAccessTokenRequest = $paypalAccessTokenRequest;
        parent::__construct($context);
    }

    public function getClientToken()
    {
        $accessToken = $this->paypalAccessTokenRequest->createRequest();

        if(isset($accessToken->result) && isset($accessToken->result->id_token)){
            return $accessToken->result->id_token;
        } else {
            throw new \Exception(__('An error has occurred on the server, please try again later'));
        }
    }
}
