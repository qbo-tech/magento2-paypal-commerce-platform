<?php

namespace PayPal\CommercePlatform\Controller\Vault;

class Remove extends \Magento\Framework\App\Action\Action
{

    const DECIMAL_PRECISION = 2;

    /** @var \PayPal\CommercePlatform\Model\Paypal\Api */
    protected $_paypalApi;

    /** @var \Magento\Framework\Controller\Result\JsonFactory */
    protected $_resultJsonFactory;

    /** @var \PayPal\CommercePlatform\Logger\Handler */
    protected $_loggerHandler;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \PayPal\CommercePlatform\Model\Paypal\Api $paypalApi,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \PayPal\CommercePlatform\Logger\Handler $logger
    ) {
        parent::__construct($context);

        $this->_loggerHandler     = $logger;
        $this->_paypalApi         = $paypalApi;
        $this->_resultJsonFactory = $resultJsonFactory;
    }

    public function execute()
    {
        $requestContent = json_decode($this->getRequest()->getContent(), true);

        $tokenId = $requestContent['id'] ?? null;

        if (!$tokenId) {
            return;
        }

        $resultJson = $this->_resultJsonFactory->create();

        try {

            /** @var \PayPalHttp\HttpResponse $response */
            $response = $this->_paypalApi->execute(new \PayPal\CommercePlatform\Model\Paypal\Vault\DeletePaymentTokensRequest($tokenId));
            $this->_loggerHandler->debug(__METHOD__ . ' RESPONSE : ', array($response));

            $resultJson->setHttpResponseCode($response->statusCode);
        } catch (\Exception $e) {
            $this->_loggerHandler->error($e->getMessage());

            throw $e;
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
        /** @var \Magento\Quote\Model\Quote $quote */
        $quote = $this->_checkoutSession->getQuote();

        $currencyCode   = $quote->getQuoteCurrencyCode();
        $amount         = round($quote->getGrandTotal(), self::DECIMAL_PRECISION);
        $subtotal       = round($quote->getSubtotal(), self::DECIMAL_PRECISION);
        $shippingAmount = round($quote->getShippingAddress()->getShippingAmount(), self::DECIMAL_PRECISION);
        $taxAmount      = round($quote->getTotals()['tax']->getValue(), self::DECIMAL_PRECISION);
        $discountAmount = round($quote->getSubtotal() - $quote->getSubtotalWithDiscount(), self::DECIMAL_PRECISION); //getBaseDiscuotAmount

        $requestBody = [
            'intent' => 'CAPTURE',
            'application_context' => [
                'shipping_preference' => 'NO_SHIPPING'
            ],
            'purchase_units' => [[
                'amount' => [
                    'currency_code' => $currencyCode,
                    'value' => $amount
                ]
            ]]
        ];

        if ($this->_paypalConfig->isSetFLag(\PayPal\CommercePlatform\Model\Config::CONFIG_XML_ENABLE_ITEMS)) {
            $requestBody['purchase_units'][0]['items'] = $this->getPaypalItemsFormatted($quote);
            $requestBody['purchase_units'][0]['amount']['breakdown'] = [
                'item_total' => [
                    'value' => $subtotal,
                    'currency_code' => $currencyCode
                ],
                'shipping' => [
                    'value' => $shippingAmount,
                    'currency_code' => $currencyCode
                ],
                'discount' => [
                    'value' => $discountAmount,
                    'currency_code' => $currencyCode
                ],
                'total_tax' => [
                    'value' => $taxAmount,
                    'currency_code' => $currencyCode
                ]
            ];
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
            $paypalItems[] = [
                'name'        => $item->getName(),
                'sku'         => $item->getSku(),
                'unit_amount' => [
                    'currency_code' => $currencyCode,
                    'value' => round($item->getPrice(), self::DECIMAL_PRECISION)
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
