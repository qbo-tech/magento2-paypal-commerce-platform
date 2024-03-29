<?php

/**
 * @author Alvaro Florez
 */

namespace PayPal\CommercePlatform\Model\Paypal;

use PayPal\CommercePlatform\Model\Paypal\Agreement\AgreementCreateRequest;
use PayPal\CommercePlatform\Model\Paypal\Agreement\Financing\FinancingOptionsCreateRequest;
use PayPal\CommercePlatform\Model\Paypal\Agreement\Token\AgreementTokenCreateRequest;
use PayPal\CommercePlatform\Model\Paypal\Core\AccessTokenRequest;
use PayPal\CommercePlatform\Model\Paypal\Core\GenerateTokenRequest;
use PayPal\CommercePlatform\Model\Paypal\Oxxo\ConfirmRequest;
use PayPal\CommercePlatform\Model\Paypal\Oxxo\GetVoucher;

class Api
{
    const PAYPAL_PARTNER_ATTRIBUTION_ID_HEADER = 'PayPal-Partner-Attribution-Id';
    const PAYPAL_PARTNER_ATTRIBUTION_ID_VALUE  = 'MagentoMexico_Cart_PPCP';

    /** @var \Magento\Framework\App\Config\ScopeConfigInterface */
    protected $_scopeConfig;

    /** @var \PayPalCheckoutSdk\Core\PayPalHttpClient */
    protected $_paypalClient;

    /** @var AccessTokenRequest */
    protected $_accessTokenRequest;

    /** @var GenerateTokenRequest */
    protected $_generateTokenRequest;

    /** @var AgreementTokenCreateRequest */
    protected $_agreementTokenCreateRequest;

    /** @var FinancingOptionsCreateRequest */
    protected $_financingOptionsCreateRequest;

    /** @var AgreementCreateRequest */
    protected $_AgreementCreateRequest;

    /** @var \PayPalCheckoutSdk\Orders\OrdersCreateRequest */
    protected $_orderCreateRequest;

    /** @var \PayPalCheckoutSdk\Orders\OrdersCaptureRequest */
    protected $_ordersCaptureRequest;

    /** @var \PayPal\CommercePlatform\Logger\Handler */
    protected $_logger;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \PayPal\CommercePlatform\Model\Config $paypalConfig,
        \PayPal\CommercePlatform\Logger\Handler $logger
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
        $httpRequest->headers[self::PAYPAL_PARTNER_ATTRIBUTION_ID_HEADER] = self::PAYPAL_PARTNER_ATTRIBUTION_ID_VALUE;

        try {
            $this->_logger->debug(__METHOD__ . ' | REQUEST ' . print_r([
                'requestType' => get_class($httpRequest),
                'headers' => $httpRequest->headers,
                'body' => $httpRequest->body,
                'path' => $httpRequest->path
            ], true));

            $response = $this->_paypalClient->execute($httpRequest);

            $this->_logger->debug(__METHOD__ . ' | RESPONSE ' . print_r($response, true));

            return $response;
        } catch (\PayPalHttp\HttpException $e) {

            $errorResponse = [
                'requestType' => get_class($httpRequest),
                'statusCode' => $e->statusCode,
                'message' => $e->getMessage(),
                'headers' => $e->headers
            ];

            $this->_logger->error(__METHOD__ . ' Error: [' . $e->getMessage() . "]\n", ['errorResponse' => print_r($errorResponse, true)]);

            return (object) $errorResponse;
        }
    }

    /**
     * Retrieve instance OrderCreateRequest
     *
     * @return FinancingOptionsCreateRequest
     */
    public function getFinancingOptionsCreateRequest()
    {
        if (!($this->_financingOptionsCreateRequest instanceof FinancingOptionsCreateRequest)) {
            $this->_financingOptionsCreateRequest = new FinancingOptionsCreateRequest();
        }

        return $this->_financingOptionsCreateRequest;
    }

    /**
     * Retrieve instance OrderCreateRequest
     *
     * @return AgreementTokenCreateRequest
     */
    public function getAgreementTokenCreateRequest()
    {
        if (!($this->_agreementTokenCreateRequest instanceof AgreementTokenCreateRequest)) {
            $this->_agreementTokenCreateRequest = new AgreementTokenCreateRequest();
        }

        return $this->_agreementTokenCreateRequest;
    }

    /**
     * Retrieve instance AccessTokenRequest
     *
     * @return AccessTokenRequest
     */
    public function getAccessTokenRequest($authorizationString, $refreshToken = null)
    {
        if (!($this->_accessTokenRequest instanceof AccessTokenRequest)) {
            $this->_accessTokenRequest = new AccessTokenRequest($authorizationString, $refreshToken);
        }

        return $this->_accessTokenRequest;
    }

    /**
     * Retrieve instance GenerateTokenRequest
     *
     * @return GenerateTokenRequest
     */
    public function getGenerateTokenRequest($accessToken, $customerId)
    {
        if (!($this->_generateTokenRequest instanceof GenerateTokenRequest)) {
            $this->_generateTokenRequest = new GenerateTokenRequest($accessToken, $customerId);
        }

        return $this->_generateTokenRequest;
    }

    /**
     * Retrieve instance OrderCreateRequest
     *
     * @return AgreementCreateRequest
     */
    public function getAgreementCreateRequest($billingToken)
    {
        if (!($this->_AgreementCreateRequest instanceof AgreementCreateRequest)) {
            $this->_AgreementCreateRequest = new AgreementCreateRequest($billingToken);
        }

        return $this->_AgreementCreateRequest;
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

    /**
     * Create confirm request
     * @param $orderId
     * @return \PayPal\CommercePlatform\Model\Paypal\Oxxo\ConfirmRequest
     */
    public function getOrdersConfirmRequest($orderId)
    {
        return new ConfirmRequest($orderId);
    }

    /**
     * Create confirm request
     * @param $orderId
     * @return \PayPal\CommercePlatform\Model\Paypal\Oxxo\GetVoucher
     */
    public function getVoucherRequest($orderId)
    {
        return new GetVoucher($orderId);
    }

}
