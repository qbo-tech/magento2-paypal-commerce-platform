<?php

namespace PayPal\CommercePlatform\Model;

/**
 * Class PaypalCPConfigProvider
 */
class PayPalCPConfigProvider implements \Magento\Checkout\Model\ConfigProviderInterface
{
    const BASE_URL_SDK = 'https://www.paypal.com/sdk/js?';
    const SDK_CONFIG_CLIENT_ID = 'client-id';
    const SDK_CONFIG_CURRENCY = 'currency';
    const SDK_CONFIG_DEBUG = 'debug';
    const SDK_CONFIG_COMPONENTS = 'components';
    const SDK_CONFIG_LOCALE = 'locale';
    const SDK_CONFIG_INTENT = 'intent';
    const SDK_CONFIG_DISABLE_FUNDING = 'disable-funding';
    const LENGTH_IDENTIFIER = 15;

    protected $_payment_code = \PayPal\CommercePlatform\Model\Config::PAYMENT_COMMERCE_PLATFORM_CODE;

    protected $_params = [];

    /** @var \PayPal\CommercePlatform\Model\Config */
    protected $_paypalConfig;

    /** @var \PayPal\CommercePlatform\Model\Paypal\Api */
    protected $_paypalApi;

    /** @var \PayPal\CommercePlatform\Model\Paypal\Credit\CalculatedFinancingOptionsRequest */
    protected $_calculatedFinancialOptionsRequest;

    /** @var \PayPal\CommercePlatform\Model\Billing\Agreement */
    protected $_billingAgreement;

    /** @var \Magento\Customer\Model\Session */
    protected $_customerSession;

    /** @var \Magento\Checkout\Model\Session */
    protected $_checkoutSession;

    /** @var \PayPal\CommercePlatform\Logger\Handler */
    protected $_logger;

    public function __construct(
        \PayPal\CommercePlatform\Model\Config $paypalConfig,
        \PayPal\CommercePlatform\Model\Paypal\Api $paypalApi,
        \PayPal\CommercePlatform\Model\Paypal\Credit\CalculatedFinancingOptionsRequest $calculatedFinancingOptionsRequest,
        \PayPal\CommercePlatform\Model\Billing\Agreement $billingAgreement,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Checkout\Model\Session $checkoutSession,
        \PayPal\CommercePlatform\Logger\Handler $logger
    ) {
        $this->_paypalConfig = $paypalConfig;
        $this->_paypalApi = $paypalApi;
        $this->_calculatedFinancialOptionsRequest = $calculatedFinancingOptionsRequest;
        $this->_billingAgreement = $billingAgreement;
        $this->_customerSession = $customerSession;
        $this->_checkoutSession = $checkoutSession;
        $this->_logger = $logger;
    }

    public function isEnableVaulting()
    {
        return $this->_paypalConfig->isEnableVaulting();
    }

    public function isDebug()
    {
        return $this->_paypalConfig->isSetFlag(\PayPal\CommercePlatform\Model\Config::CONFIG_XML_DEBUG_MODE);
    }

    public function isEnableAcdc()
    {
        return $this->_paypalConfig->isEnableAcdc();
    }

    public function isEnableBcdc()
    {
        return $this->_paypalConfig->isEnableBcdc();
    }

    public function isEnableReferenceTransaction()
    {
        return $this->_paypalConfig->isEnableReferenceTransaction();
    }

    public function isEnableOxxo()
    {
        return $this->_paypalConfig->isEnableOxxo();
    }

    public function getGrandTotal()
    {
        return $this->_checkoutSession->getQuote()->getGrandTotal();
    }

    public function isPaypalActive() {
        return $this->_paypalConfig->isMethodActive($this->_payment_code);
    }

    public function getConfig()
    {
        if (!$this->isPaypalActive()) {
            return [];
        }
        $config = [
            'payment' => [
                $this->_payment_code => [
                    'title' => $this->_paypalConfig->getConfigValue(\PayPal\CommercePlatform\Model\Config::CONFIG_XML_TITLE),
                    'urlSdk' => $this->getUrlSdk(),
                    'style' => [
                        'layout' => $this->_paypalConfig->getConfigValue(\PayPal\CommercePlatform\Model\Config::XML_CONFIG_LAYOUT),
                        'color' => $this->_paypalConfig->getConfigValue(\PayPal\CommercePlatform\Model\Config::XML_CONFIG_COLOR),
                        'shape' => $this->_paypalConfig->getConfigValue(\PayPal\CommercePlatform\Model\Config::XML_CONFIG_SHAPE),
                        'label' => $this->_paypalConfig->getConfigValue(\PayPal\CommercePlatform\Model\Config::XML_CONFIG_LABEL),
                    ],
                    'authorizationBasic' => 'Basic ' . $this->_paypalApi->getAuthorizationString(),
                    'grandTotal' => $this->getGrandTotal(),
                    'customer' => [
                        'id' => $this->validateCustomerId(),
                        'payments' => $this->getCustomerPaymentTokens($this->validateCustomerId()),
                        'agreements' => $this->getCustomerAgreements($this->validateCustomerId())
                    ],
                    'referenceTransaction' => [
                        'enable' => $this->isEnableReferenceTransaction(),
                        'msiMinimum' => $this->_paypalConfig->getMSIMinimum('referenceTransaction'),
                    ],
                    'bcdc' => [
                        'enable' => $this->isEnableBcdc(),
                    ],
                    'oxxo' => [
                        'enable' => (boolean)$this->isEnableOxxo(),
                    ],
                    'acdc' => [
                        'enable' => $this->isEnableAcdc(),
                        'installments_type' => $this->_paypalConfig->getInstallmentsType(),
                        'enable_vaulting' => $this->isEnableVaulting(),
                        'card_fisrt_acdc' => $this->_paypalConfig->isCardFirstAcdc(),
                        'msiMinimum' => $this->_paypalConfig->getMSIMinimum(),
                    ],
                    'splitOptions' => [
                        'title_method_paypal' => $this->_paypalConfig->getConfigValue(\PayPal\CommercePlatform\Model\Config::CONFIG_XML_TITLE_METHOD_PAYPAL),
                        'title_method_card' => $this->_paypalConfig->getConfigValue(\PayPal\CommercePlatform\Model\Config::CONFIG_XML_TITLE_METHOD_CARD),
                        'title_method_oxxo' => $this->_paypalConfig->getConfigValue(\PayPal\CommercePlatform\Model\Config::CONFIG_XML_TITLE_METHOD_OXXO),
                    ],
                    self::SDK_CONFIG_DEBUG => $this->isDebug(),
                    'fraudNet' => [
                        'sourceWebIdentifier' => $this->_paypalConfig->getConfigValue(\PayPal\CommercePlatform\Model\Config::CONFIG_XML_FRAUDNET_SWI),
                        'fncls' => $this->_paypalConfig->getConfigValue(\PayPal\CommercePlatform\Model\Config::CONFIG_XML_FRAUDNET_FNCLS),
                        'sessionIdentifier' => 'M2' . bin2hex(random_bytes(self::LENGTH_IDENTIFIER)),
                    ]
                ]
            ]
        ];

        return $config;
    }

    public function getUrlSdk()
    {
        $this->buildParams();

        return self::BASE_URL_SDK . http_build_query($this->_params);
    }

    private function buildParams()
    {
        $this->_params = [
            self::SDK_CONFIG_CLIENT_ID => $this->_paypalConfig->getClientId(),
            self::SDK_CONFIG_CURRENCY => $this->_paypalConfig->getCurrency(),
            self::SDK_CONFIG_DEBUG => $this->isDebug() ? 'true' : 'false',
            self::SDK_CONFIG_LOCALE => $this->_paypalConfig->getLocale(),
            self::SDK_CONFIG_INTENT => 'capture',
        ];

        if ($this->isEnableAcdc()) {
            $this->_params[self::SDK_CONFIG_COMPONENTS] = 'card-fields,buttons';
        }
    }

    public function canRemember()
    {
        return ($this->isEnableAcdc() && $this->isEnableVaulting());
    }

    public function validateCustomerId()
    {
        if ($this->_customerSession->isLoggedIn()) {
            return $this->_customerSession->getCustomerId();
        }

        return null;
    }

    public function getCustomerAgreements($customerId)
    {
        $agreements = $this->_billingAgreement->getAvailableCustomerBillingAgreements($customerId);
        $agreementsIds = [];
        foreach ($agreements as $agreement) {
            $agreementsIds[] = [
                'id' => $agreement->getAgreementId(),
                'reference' => $agreement->getReferenceId(),
                'email' => $agreement->getPayerEmail(),
                'status' => $agreement->getStatus()
            ];
        }

        return $agreementsIds;
    }

    public function getCustomerPaymentTokens($customerId)
    {
        if (!$this->validateCustomerId()) {
            return [];
        }

        $paymentTokens = [];
        $response = $this->_paypalApi->execute(new \PayPal\CommercePlatform\Model\Paypal\Vault\PaymentTokensRequest($customerId));

        if ($response->statusCode == 200 && isset($response->result->payment_tokens)) {

            foreach ($response->result->payment_tokens as $token) {

                if (property_exists($token->payment_source, 'card')) {
                    $paymentTokens['cards'][] = [
                        'id' => $token->id,
                        'brand' => $token->payment_source->card->brand,
                        'last_digits' => $token->payment_source->card->last_digits
                    ];
                }
            }

            if (isset($paymentTokens['cards'])) {
                $installmentsType = $this->_paypalConfig->getConfigValue(\PayPal\CommercePlatform\Model\Config::CONFIG_XML_INSTALLMENTS_TYPE);
                $this->getCalculatedFinancialOptions($paymentTokens, $installmentsType);
            }

        }

        return $paymentTokens;
    }

    private function getCalculatedFinancialOptions(&$paymentTokens, $installmentsType = null)
    {
        $grandTotal = $this->getGrandTotal();
        $this->_logger->debug(__METHOD__ . ' | GrandTotal: ' . $grandTotal);;
        foreach ($paymentTokens['cards'] as $index => $payment) {
            $body = [
                "financing_country_code" => $this->_paypalConfig->getCountryCode(),
                "transaction_amount" => [
                    "value" => $grandTotal,
                    "currency_code" => $this->_checkoutSession->getQuote()->getQuoteCurrencyCode()
                ],
                "funding_instrument" => [
                    "type" => "TOKEN",
                    "token" => [
                        "type" => "PAYMENT_METHOD_TOKEN",
                        "payment_method_token" => $payment['id']
                    ]
                ]
            ];

            // Validate installments type (MCI)
            if ($installmentsType === 'installments_cost_to_buyer') {
                $body["flow_context"] = [
                    "attributes" => ["FEE_POLICY_CHARGE_CONSUMER"]
                ];
            }

            $this->_calculatedFinancialOptionsRequest->body = $body;

            $response = $this->_paypalApi->execute($this->_calculatedFinancialOptionsRequest);

            $financeOptions = $response->result->financing_options ?? [];

            if ($response->statusCode == 200) {
                $paymentTokens['cards'][$index]['financing_options'] = $financeOptions;
            }
        }
    }


    private function sanitizeFinancingOptions($financeOptions)
    {
        foreach ($financeOptions as &$option) {
            if (!isset($option->qualifying_financing_options)) {
                continue;
            }

            foreach ($option->qualifying_financing_options as &$qualifyingOption) {
                if (
                    isset($qualifyingOption->total_consumer_fee->value) &&
                    (float)$qualifyingOption->total_consumer_fee->value == 0 &&
                    isset($qualifyingOption->fee_reference_id)
                ) {
                    unset($qualifyingOption->fee_reference_id);
                }
            }
            unset($qualifyingOption);
        }
        unset($option);

        return $financeOptions;
    }

}
