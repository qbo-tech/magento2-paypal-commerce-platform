<?php

namespace PayPal\CommercePlatform\Model\Paypal\Agreement\Financing;

use PayPal\CommercePlatform\Model\Paypal\Order\DataObject;
use PayPal\CommercePlatform\Model\Paypal\Order\type;

class Request
{

    const DEFAULT_COUNTRY_CODE = 'MX';

    /** @var \Magento\Checkout\Model\Session $checkoutSession */
    protected $_checkoutSession;

    /** @var \PayPal\CommercePlatform\Model\Paypal\Api */
    protected $_paypalApi;

    /** @var \PayPal\CommercePlatform\Model\Config */
    protected $_paypalConfig;

    /** @var FinancingOptionsCreateRequest */
    protected $_financingOptionsCreateRequest;

    /** @var \Magento\Framework\Controller\Result\JsonFactory */
    protected $_resultJsonFactory;

    /** @var \PayPal\CommercePlatform\Logger\Handler */
    protected $_loggerHandler;

    /** @var \Magento\Framework\Event\ManagerInterface */
    protected $_eventManager;

    /**
     *
     * @var DataObject
     */
    protected $_data;
    /**
     *
     * @var \Magento\Quote\Model\Quote
     */
    protected $_quote;

    /**
     * Request's order model
     *
     * @var \Magento\Sales\Model\Order
     */
    protected $_order;
    /**
     * Customer address
     *
     * @var \Magento\Customer\Helper\Address
     */
    protected $_addressHelper   = null;
    /**
     *
     * @var \Magento\Quote\Model\Quote\Address
     */
    protected $_customerAddress = null;
    /**
     *
     * @var \Magento\Quote\Model\Quote\Address
     */
    protected $_customerBillingAddress = null;

    /**
     * Locale Resolver
     *
     * @var \Magento\Framework\Locale\Resolver
     */
    protected $localeResolver;
    /**
     *
     * @var type
     */
    protected $_totals;
    /**
     *
     * @var type
     */
    protected $_storeManager;

    /**
     *
     * @var type
     */
    protected $_logger;
    /**
     *
     * @var type
     */
    protected $_cartFactory;
    /**
     *
     * @var type
     */

    /** @var \Magento\Payment\Model\Cart\SalesModel\SalesModelInterface */
    protected $_cartPayment;

    protected $_customer;
    /**
     *
     * @var string
     */
    public static $_cancelUrl;
    public static $_returnUrl;
    public static $_notifyUrl;
    /**
     * @var string
     */
    const PAYMENT_METHOD = 'paypal';

    const ALLOWED_PAYMENT_METHOD = 'IMMEDIATE_PAY';

    const DISCOUNT_ITEM_NAME = 'Discount Item';

    const PAYPAL_CLIENT_METADATA_ID_HEADER = 'PayPal-Client-Metadata-Id';

    /**
     * @var \Magento\Quote\Model\QuoteRepository
     */
    private $quoteRepository;

    /**
     * @var \Magento\Framework\DataObject
     */
    private $_adressData;
    /**
     * @var \Magento\Quote\Api\ShippingMethodManagementInterface
     */
    private $shippingMethodManager;

    /**
     * @var \PayPal\CommercePlatform\Model\Billing\Agreement
     */
    private $agreementModel;

    /**
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \PayPal\CommercePlatform\Model\Paypal\Api $paypalApi,
        \PayPal\CommercePlatform\Model\Config $paypalConfig,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \PayPal\CommercePlatform\Logger\Handler $logger,
        \Magento\Framework\DataObject $data,
        \Magento\Framework\Locale\Resolver $localeResolver,
        \Magento\Framework\DataObject $address,
        \Magento\Customer\Helper\Address $addressHelper,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Quote\Api\ShippingMethodManagementInterface $shippingMethodManager,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Payment\Model\Cart\SalesModel\Factory $cartFactory,
        \Magento\Framework\DataObject $dataObject,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        \Magento\Quote\Model\QuoteRepository $quoteRepository,
        \PayPal\CommercePlatform\Model\Billing\Agreement $agreementModel
    ) {
        $this->_loggerHandler  = $logger;

        $this->_paypalApi    = $paypalApi;
        $this->_paypalConfig = $paypalConfig;
        $this->_eventManager = $eventManager;

        $this->_resultJsonFactory  = $resultJsonFactory;
        $this->_checkoutSession    = $checkoutSession;

        $this->_data = $data;
        $this->_adressData = $address;
        $this->_quote = $checkoutSession->getQuote();
        $this->_cartFactory = $cartFactory;
        $this->_cartPayment = $this->_cartFactory->create($this->_quote);
        $this->_customer = $customerSession->getCustomer();
        $this->_logger = $logger;
        $this->_financingOptionsCreateRequest = $this->_paypalApi->getFinancingOptionsCreateRequest();

        $this->_customerBillingAddress = $cart->getQuote()->getBillingAddress();
        $this->_customerAddress = $cart->getQuote()->getShippingAddress();

        if (empty($this->_customerBillingAddress)) {
            $this->_customerBillingAddress = $dataObject;
        }
        if (empty($this->_customerAddress)) {
            $this->_customerAddress = $dataObject;
        }

        $this->_addressHelper = $addressHelper;
        $this->localeResolver = $localeResolver;
        $this->shippingMethodManager = $shippingMethodManager;
        $this->_storeManager = $storeManager;
        $this->quoteRepository = $quoteRepository;
        $this->agreementModel = $agreementModel;
        self::$_cancelUrl = $this->_storeManager->getStore()->getUrl('checkout/cart');
        self::$_returnUrl = $this->_storeManager->getStore()->getUrl('checkout/cart');
        self::$_notifyUrl = $this->_storeManager->getStore()->getUrl('paypal/ipn');
    }

    /**
     * Create and execute request paypal API
     *
     * @param string $paypalCMID
     * @return \PayPalHttp\HttpResponse
     */
    public function createRequest($agreementReference, $paypalCMID)
    {
        $this->_financingOptionsCreateRequest->prefer('return=representation');

        $requestBody = $this->buildRequestBody($agreementReference);

        if ($paypalCMID) {
            $this->_financingOptionsCreateRequest->headers[self::PAYPAL_CLIENT_METADATA_ID_HEADER] = $paypalCMID;
        }

        $this->_financingOptionsCreateRequest->body = $requestBody;

        try {
            $this->_eventManager->dispatch('paypalcp_financing_before', ['paypalCMID' => $paypalCMID, 'quote' => $this->_quote, 'customer' => $this->_customer]);

            /** @var \PayPalHttp\HttpResponse $response */
            $response = $this->_paypalApi->execute($this->_financingOptionsCreateRequest);

            $this->_eventManager->dispatch('paypalcp_financing_after', ['quote' => $this->_quote, 'paypalResponse' => $response]);
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
     * @return array
     */
    private function buildRequestBody($agreementReference)
    {
        $currencyCode  = $this->_quote->getBaseCurrencyCode();
        $total = $this->_formatPrice($this->_quote->getGrandTotal());

        if(!$this->_quote->getReserveOrderId()) {
            $this->_quote->reserveOrderId();
            $this->quoteRepository->save($this->_quote);
        }

        return [
            'financing_country_code' => $this->_paypalConfig->getCountryCode() ?? self::DEFAULT_COUNTRY_CODE,
            'transaction_amount' => [
                'value' => $total,
                'currency_code' => $currencyCode,

            ],
            "funding_instrument" => [
                "type" => "BILLING_AGREEMENT",
                "billing_agreement" => [
                    "billing_agreement_id" => $this->getBillingAgreement($agreementReference)
                ]
            ]
        ];
    }

    protected function getBillingAgreement($agreementReference){
        return $this->agreementModel->decryptReference($agreementReference);
    }

    /**
     * Format price string
     *
     * @param mixed $string
     * @return string
     */
    protected function _formatPrice($string)
    {
        return sprintf('%.2F', $string);
    }
}
