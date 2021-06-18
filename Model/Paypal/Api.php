<?php

/**
 * @author Alvaro Florez <aflorezd@gmail.com>
 */

namespace PayPal\CommercePlatform\Model\Paypal;

use stdClass;

class Api
{

    const PAYPAL_PARTNER_ATTRIBUTION_ID = 'MagentoMexico_Cart_PPCP';

    /** @var \Magento\Framework\App\Config\ScopeConfigInterface */
    protected $_scopeConfig;

    /** @var \PayPalCheckoutSdk\Core\PayPalHttpClient */
    protected $_paypalClient;

    /** @var \PayPalCheckoutSdk\Orders\OrdersCreateRequest */
    protected $_orderCreateRequest;

    /** @var \PayPalCheckoutSdk\Orders\OrdersCaptureRequest */
    protected $_ordersCaptureRequest;

    /** @var \Psr\Log\LoggerInterface */
    protected $_logger;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \PayPal\CommercePlatform\Model\Config $paypalConfig,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->_logger       = $logger;
        $this->_scopeConfig  = $scopeConfig;

        $environment = $paypalConfig->isSandbox() ? \PayPalCheckoutSdk\Core\SandboxEnvironment::class : \PayPalCheckoutSdk\Core\ProductionEnvironment::class;

        $this->_paypalClient = new \PayPalCheckoutSdk\Core\PayPalHttpClient(new $environment($paypalConfig->getClientId(), $paypalConfig->getSecretId()));
    }

    /**
     * The method that takes an HTTP request, serializes the request, makes a call to given environment, and deserialize response
     *
     * @param  \PayPalHttp\HttpRequest $httpRequest
     * @return \PayPalHttp\HttpResponse
     *
     */
    public function execute(\PayPalHttp\HttpRequest $httpRequest)
    {
        try {
            $this->_logger->debug(__METHOD__ . ' headers: ' . print_r($httpRequest->headers, true));

            return $this->_paypalClient->execute($httpRequest);
        } catch (\PayPalHttp\HttpException $e) {

            $erroResponse = [
                'requestClass' => get_class($httpRequest),
                'statusCode' => $e->statusCode,
                'message' => $e->getMessage(),
                'headers' => $e->headers
            ];

            $this->_logger->error(__METHOD__ . ' Error: ' . $e->getMessage(), $erroResponse);

            return (object) $erroResponse;
        }
    }

    /**
     * Retrieve instance OrderCreateRequest
     *
     * @return \PayPalCheckoutSdk\Orders\OrdersCreateRequest
     */
    public function getOrderCreateRequest()
    {
        if (!($this->_orderCreateRequest instanceof \PayPalCheckoutSdk\Orders\OrdersCreateRequest)) {
            $this->_orderCreateRequest = new \PayPalCheckoutSdk\Orders\OrdersCreateRequest();
        }

        return $this->_orderCreateRequest;
    }

    /**
     * Retrieve instance OrdersCaptureRequest
     *
     * @return \PayPalCheckoutSdk\Orders\OrdersCaptureRequest
     */
    public function getOrdersCaptureRequest($orderId)
    {
        if (!($this->_ordersCaptureRequest instanceof \PayPalCheckoutSdk\Orders\OrdersCaptureRequest)) {
            $this->_ordersCaptureRequest = new \PayPalCheckoutSdk\Orders\OrdersCaptureRequest($orderId);
        }

        return $this->_ordersCaptureRequest;
    }

    public function getBaseUrl()
    {
        return $this->_paypalClient->environment->baseUrl();
    }

    public function getAuthorizationString()
    {
        return $this->_paypalClient->environment->authorizationString();
    }
}
