<?php
namespace PayPal\CommercePlatform\Controller\Order;

class Index extends \Magento\Framework\App\Action\Action
{


    /** @var \Magento\Checkout\Model\Session $checkoutSession */
    protected $_checkoutSession;

    /** @var \PayPal\CommercePlatform\Model\Paypal\Api */
    protected $_paypalApi;

    /** @var \PayPalCheckoutSdk\Orders\OrdersCreateRequest */
    protected $_orderCreateRequest;

    /** @var \Magento\Framework\Controller\Result\JsonFactory */
    protected $_resultJsonFactory;

    /** @var \Psr\Log\LoggerInterface */
    protected $_logger;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \PayPal\CommercePlatform\Model\Paypal\Api $paypalApi,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($context);

        $this->_logger  = $logger;


        $this->_paypalApi = $paypalApi;

        $this->_orderCreateRequest = $this->_paypalApi->getOrderCreateRequest();
        $this->_resultJsonFactory  = $resultJsonFactory;
        $this->_checkoutSession    = $checkoutSession;
    }

    public function execute()
    {
        $this->_logger->debug(__METHOD__ . ' start');
        $resultJson = $this->_resultJsonFactory->create();

        $this->_orderCreateRequest->prefer('return=representation');
        $this->_orderCreateRequest->body = $this->buildRequestBody();

        $this->_logger->debug(__METHOD__, ['request' => $this->_orderCreateRequest]);


        /** @var \PayPalHttp\HttpResponse $response */
        $response = $this->_paypalApi->execute($this->_orderCreateRequest);

        $this->_logger->debug(__METHOD__ . '#response ', ['response' => print_r($response, true)]);

        return $resultJson->setData($response);
    }

    /**
     * Setting up the JSON request body for creating the order with minimum request body. The intent in the
     * request body should be "AUTHORIZE" for authorize intent flow.
     *
     */
    private function buildRequestBody()
    {
        $this->_logger->debug(__METHOD__ );

        $amount = round($this->_checkoutSession->getQuote()->getGrandTotal(), 2);

        $this->_logger->debug(__METHOD__ . '#amount ', ['amount' => print_r($amount, true)]);


        return array(
            'intent' => 'CAPTURE',
            'application_context' =>
            array(
                'return_url' => 'https://example.com/return',
                'cancel_url' => 'https://example.com/cancel'
            ),
            'purchase_units' =>
            array(
                0 =>
                array(
                    'amount' =>
                    array(
                        'currency_code' => 'MXN',
                        'value' => $amount
                    )
                )
            )
        );
    }
}
