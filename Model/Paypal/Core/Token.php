<?php

namespace PayPal\CommercePlatform\Model\Paypal\Core;

use PayPal\CommercePlatform\Model\Paypal\Order\DataObject;
use PayPal\CommercePlatform\Model\Paypal\Order\type;
use PayPalHttp\HttpResponse;

class Token
{

    /** @var \Magento\Checkout\Model\Session $checkoutSession */
    protected $_checkoutSession;

    /** @var \PayPal\CommercePlatform\Model\Paypal\Api */
    protected $_paypalApi;

    /** @var AccessTokenRequest */
    protected $_accessTokenRequest;

    /** @var \PayPal\CommercePlatform\Logger\Handler */
    protected $_loggerHandler;

    /** @var \Magento\Framework\Event\ManagerInterface */
    protected $_eventManager;

    /** @var \Magento\Quote\Model\Quote */
    protected $_quote;

    protected $_customer;

    /**
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \PayPal\CommercePlatform\Model\Paypal\Api $paypalApi,
        \PayPal\CommercePlatform\Logger\Handler $logger,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Event\ManagerInterface $eventManager
    ) {
        $this->_loggerHandler  = $logger;
        $this->_paypalApi    = $paypalApi;
        $this->_eventManager = $eventManager;
        $this->_checkoutSession    = $checkoutSession;
        $this->_quote = $checkoutSession->getQuote();
        $this->_customer = $customerSession->getCustomer();
    }

    /**
     * Create and execute request paypal API
     *
     * @return HttpResponse
     * @throws \Exception
     */
    public function createRequest(): HttpResponse
    {
        $this->_accessTokenRequest = $this->_paypalApi->getAccessTokenRequest($this->_paypalApi->getAuthorizationString());
        $requestBody = $this->buildRequestBody();
        $this->_accessTokenRequest->body = $requestBody;

        try {
            $this->_eventManager->dispatch('paypalcp_access_token_before', ['quote' => $this->_quote, 'customer' => $this->_customer]);

            /** @var \PayPalHttp\HttpResponse $response */
            $response = $this->_paypalApi->execute($this->_accessTokenRequest);
            $this->_eventManager->dispatch('paypalcp_access_token_after', ['quote' => $this->_quote, 'paypalResponse' => $response]);
        } catch (\Exception $e) {
            $this->_loggerHandler->error($e->getMessage());
            throw $e;
        }

        return $response;
    }

    /**
     * Create and execute request paypal API
     *
     * @param $accessToken
     * @return HttpResponse
     * @throws \Exception
     */
    public function createGenerateTokenRequest($accessToken): HttpResponse
    {
        $this->_accessTokenRequest = $this->_paypalApi->getGenerateTokenRequest($accessToken, $this->_customer->getId());
        $requestBody = $this->buildRequestBody($this->_customer->getId());

        $this->_accessTokenRequest->body = $requestBody;

        try {
            $this->_eventManager->dispatch('paypalcp_generate_token_before', ['quote' => $this->_quote, 'customer' => $this->_customer]);

            /** @var \PayPalHttp\HttpResponse $response */
            $response = $this->_paypalApi->execute($this->_accessTokenRequest);
            $this->_eventManager->dispatch('paypalcp_generate_token_after', ['quote' => $this->_quote, 'paypalResponse' => $response]);
        } catch (\Exception $e) {
            $this->_loggerHandler->error($e->getMessage());
            throw $e;
        }

        return $response;
    }

    /**
     * Setting up the JSON request body for creating the order with minimum request body. The intent in the
     * request body should be "AUTHORIZE" for authorize intent flow.
     *
     * @param null $customerId
     * @return array
     */
    private function buildRequestBody($customerId = null): array
    {
        if (null === $customerId) {
            return [
                'grant_type' => 'client_credentials',
                'response_type' => 'id_token',
                'ignoreCache' => 'true'
            ];
        } else {
            return [
                'customer_id' => $customerId
            ];
        }

    }
}
