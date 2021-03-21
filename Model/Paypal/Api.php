<?php
namespace PayPal\CommercePlatform\Model\Paypal;

class Api
{

    const PATH_SANDBOX_FLAG = 'payment/paypal_advanced/sandbox_flag';

    /** @var \Magento\Framework\App\Config\ScopeConfigInterface */
    protected $_scopeConfig;

    /** @var \PayPalCheckoutSdk\Core\PayPalHttpClient */
    protected $_paypalClient;

    /** @var \PayPalCheckoutSdk\Orders\OrdersCreateRequest */
    protected $_orderCreateRequest;

    /** @var \PayPalCheckoutSdk\Orders\OrdersCaptureRequest */
    protected $_ordersCaptureRequest;

    /** @var \Magento\Framework\Controller\Result\JsonFactory */
    protected $_resultJsonFactory;

    /** @var \Psr\Log\LoggerInterface */
    protected $_logger;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Checkout\Model\Session $checkoutSession,
        \PayPalCheckoutSdk\Orders\OrdersCreateRequest $orderCreateRequest,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->_logger      = $logger;
        $this->_scopeConfig = $scopeConfig;


        $clientId     = 'AT6lRUtL67ziOZ2BRJ6g_5s0qo1BmKcdqXxjB5n9IRwlfy-i-UXCV1Bf0VeWRAhLPtsFdZDqPXKfzG-o';
        $clientSecret = 'EF4ELYtqJKj9ixVpvQIAugwaqaZwqDZ-erCEYkbWnDrkeTjhI2o2j7y1IDXzs01WOcRCAiACTQrY7TZJ';

        $isSandbox   = $this->_scopeConfig->getValue(self::PATH_SANDBOX_FLAG);
        $environment = $isSandbox ? \PayPalCheckoutSdk\Core\SandboxEnvironment::class : \PayPalCheckoutSdk\Core\ProductionEnvironment::class;

        $this->_paypalClient = new \PayPalCheckoutSdk\Core\PayPalHttpClient(new $environment($clientId, $clientSecret));
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
        $this->_logger->info(__METHOD__ . ' | httpRequest class ' . get_class($httpRequest), array());

        return $this->_paypalClient->execute($httpRequest);
    }

    /**
     * Retrieve instance OrderCreateRequest
     *
     * @return \PayPalCheckoutSdk\Orders\OrdersCreateRequest
     */
    public function getOrderCreateRequest()
    {
        if(!($this->_orderCreateRequest instanceof \PayPalCheckoutSdk\Orders\OrdersCreateRequest)){
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
        if(!($this->_ordersCaptureRequest instanceof \PayPalCheckoutSdk\Orders\OrdersCaptureRequest)){
            $this->_ordersCaptureRequest = new \PayPalCheckoutSdk\Orders\OrdersCaptureRequest($orderId);
        }

        return $this->_ordersCaptureRequest;
    }

}
