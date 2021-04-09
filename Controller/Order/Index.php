<?php

namespace PayPal\CommercePlatform\Controller\Order;

class Index extends \Magento\Framework\App\Action\Action
{

    const DECIMAL_PRECISION = 2;

    /** @var \Magento\Checkout\Model\Session $checkoutSession */
    protected $_checkoutSession;

    /** @var \PayPal\CommercePlatform\Model\Paypal\Api */
    protected $_paypalApi;

    /** @var \PayPal\CommercePlatform\Model\Config */
    protected $_paypalConfig;

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
        \PayPal\CommercePlatform\Model\Config $paypalConfig,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($context);

        $this->_logger  = $logger;

        $this->_paypalApi    = $paypalApi;
        $this->_paypalConfig = $paypalConfig;

        $this->_orderCreateRequest = $this->_paypalApi->getOrderCreateRequest();
        $this->_resultJsonFactory  = $resultJsonFactory;
        $this->_checkoutSession    = $checkoutSession;
    }

    public function execute()
    {
        $this->_logger->debug(__METHOD__ . ' start');
        $resultJson = $this->_resultJsonFactory->create();

        $this->_orderCreateRequest->prefer('return=representation');

        $requestBody = $this->buildRequestBody();
        $this->_logger->debug(__METHOD__ . ' requestBody | ' . print_r(json_encode($requestBody), true));

        $this->_orderCreateRequest->body = $requestBody;

        $this->_logger->debug(__METHOD__, ['request' => $this->_orderCreateRequest]);

        $httpBadRequestCode = '400';
        $httpErrorCode = '500';


        try {
            /** @var \PayPalHttp\HttpResponse $response */
            $response = $this->_paypalApi->execute($this->_orderCreateRequest);

            $this->_logger->debug(__METHOD__ . '#response ', ['response' => print_r($response, true)]);

        } catch (\Exception $e) {
            $this->_logger->error($e->getMessage());

            $resultJson->setData(array('reason' => $e->getMessage()));

            return $resultJson->setHttpResponseCode($httpErrorCode);
        }


        return $resultJson->setData($response);
    }

    /**
     * Setting up the JSON request body for creating the order with minimum request body. The intent in the
     * request body should be "AUTHORIZE" for authorize intent flow.
     *
     */
    private function buildRequestBody()
    {
        $this->_logger->debug(__METHOD__);

        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->_checkoutSession->getQuote();

        $currencyCode   = $quote->getQuoteCurrencyCode();
        $amount         = round($quote->getGrandTotal(), self::DECIMAL_PRECISION);
        $subtotal       = round($quote->getSubtotal(), self::DECIMAL_PRECISION);
        $shippingAmount = round($quote->getShippingAddress()->getShippingAmount(), self::DECIMAL_PRECISION);
        $taxAmount      = round($quote->getTotals()['tax']->getValue(), self::DECIMAL_PRECISION);
        $discountAmount = round($quote->getSubtotal() - $quote->getSubtotalWithDiscount(), self::DECIMAL_PRECISION);

        $requestBody = [
            'intent' => 'CAPTURE',
            'application_context' => [
                'shipping_preference'=> 'NO_SHIPPING'
            ],
            'purchase_units' => [[
                'amount' => [
                    'currency_code' => $currencyCode,
                    'value' => $amount,
                    'breakdown' => [
                                'item_total' => [
                                    'value' => $subtotal,
                                    'currency_code' => $currencyCode
                                ],
                                'shipping' => [
                                    'value' => $shippingAmount,
                                    'currency_code' => $currencyCode
                                ],
                                'discuont' => [
                                    'value' => $discountAmount,
                                    'currency_code' => $currencyCode
                                ],
                                'total_tax' => [
                                    'value' => $taxAmount,
                                    'currency_code' => $currencyCode
                                ]
                    ]
                ] 
            ]]
        ];

        if ($this->_paypalConfig->isSetFLag(\PayPal\CommercePlatform\Model\Config::CONFIG_XML_ENABLE_ITEMS)) {
            $requestBody['purchase_units'][0]['items'] = $this->getPaypalItemsFormatted($quote);
        }

        return $requestBody;
    }

    /**
     * Get Quote Items formatted paypal request
     *
     * @param \Magento\Quote\Model\Quote $quote
     * @return array
     */
    public function getPaypalItemsFormatted(\Magento\Quote\Model\Quote $quote)
    {
        $paypalItems = [];

        $currencyCode   = $quote->getQuoteCurrencyCode();

        /** @var \Magento\Quote\Model\Quote\Item $item */
        foreach ($quote->getItems() as $item) {
            /* $this->_logger->debug(__METHOD__ . ' | getOriginalCustomPrice ' . print_r($item->getOriginalCustomPrice(), true));
            foreach($item->getProduct()->getPriceInfo()->getPrices() as $priceInfo){
                $this->_logger->debug(__METHOD__ . ' | getPriceInfo getPriceCode ' . print_r($priceInfo->getPriceCode(), true));
                $this->_logger->debug(__METHOD__ . ' | getPriceInfo getValue ' . print_r($priceInfo->getValue(), true));
            } */
            //$this->_logger->debug(__METHOD__ . ' | getProduct()->getPriceInfo() ' . print_r($item->getProduct()->getPriceInfo()->getPrices(), true));

            $paypalItems[] = [
                'name'        => $item->getName(),
                'sku'         => $item->getSku(),
                'unit_amount' => [
                    'currency_code' => $currencyCode,
                    'value' => round($item->getRowTotal(), self::DECIMAL_PRECISION)
                ],
                'tax' => [
                    'currency_code' => $currencyCode,
                    'value' => round($item->getTaxAmount(), self::DECIMAL_PRECISION)
                ],
                'quantity'    => $item->getQty()
            ];
        }

        return $paypalItems;
    }
}
