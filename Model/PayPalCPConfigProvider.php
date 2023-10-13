<?php

namespace PayPal\CommercePlatform\Model;

/**
 * Class PaypalCPConfigProvider
 */
class PaypalCPConfigProvider implements \Magento\Checkout\Model\ConfigProviderInterface
{
    const BASE_URL_SDK = 'https://www.paypal.com/sdk/js?';
    const ENDPOINT_ACCESS_TOKEN = '/v1/oauth2/token';
    const ENDPOINT_GENERATE_CLIENT_TOKEN = '/v1/identity/generate-token';
    const SDK_CONFIG_CLIENT_ID  = 'client-id';
    const SDK_CONFIG_CURRENCY   = 'currency';
    const SDK_CONFIG_DEBUG      = 'debug';
    const SDK_CONFIG_COMPONENTS = 'components';
    const SDK_CONFIG_LOCALE     = 'locale';
    const SDK_CONFIG_INTENT     = 'intent';
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
        $this->_paypalConfig    = $paypalConfig;
        $this->_paypalApi       = $paypalApi;
        $this->_calculatedFinancialOptionsRequest = $calculatedFinancingOptionsRequest;
        $this->_billingAgreement = $billingAgreement;
        $this->_customerSession = $customerSession;
        $this->_checkoutSession = $checkoutSession;
        $this->_logger          = $logger;
    }

    public function getConfig()
    {
        if (!$this->_paypalConfig->isMethodActive($this->_payment_code)) {
            return [];
        }
        $config = [
            'payment' => [
                $this->_payment_code => [
                    'title' => $this->_paypalConfig->getConfigValue(\PayPal\CommercePlatform\Model\Config::CONFIG_XML_TITLE),
                    'urlSdk' => $this->getUrlSdk(),
                    'style'  => [
                        'layout'  => $this->_paypalConfig->getConfigValue(\PayPal\CommercePlatform\Model\Config::XML_CONFIG_LAYOUT),
                        'color'   => $this->_paypalConfig->getConfigValue(\PayPal\CommercePlatform\Model\Config::XML_CONFIG_COLOR),
                        'shape'   => $this->_paypalConfig->getConfigValue(\PayPal\CommercePlatform\Model\Config::XML_CONFIG_SHAPE),
                        'label'   => $this->_paypalConfig->getConfigValue(\PayPal\CommercePlatform\Model\Config::XML_CONFIG_LABEL),
                    ],
                    'urlAccessToken' => $this->_paypalApi->getBaseUrl() . self::ENDPOINT_ACCESS_TOKEN,
                    'urlGenerateClientToken' => $this->_paypalApi->getBaseUrl() . self::ENDPOINT_GENERATE_CLIENT_TOKEN,
                    'authorizationBasic' => 'Basic ' . $this->_paypalApi->getAuthorizationString(),
                    'customer' => [
                        'id' => $this->validateCustomerId(),
                        'payments' => $this->getCustomerPaymentTokens($this->validateCustomerId()),
                        'agreements' => $this->getCustomerAgreements($this->validateCustomerId())
                    ],
                    'referenceTransaction' => [
                        'enable' => $this->_paypalConfig->isEnableReferenceTransaction(),
                        'msiMinimum' => $this->_paypalConfig->getMSIMinimum()
                    ],
                    'bcdc' => [
                        'enable' => $this->_paypalConfig->isEnableBcdc(),
                    ],
                    'oxxo' => [
                        'enable' => (boolean)$this->_paypalConfig->isEnableOxxo(),
                    ],
                    'acdc' => [
                        'enable' => $this->_paypalConfig->isEnableAcdc(),
                        'enable_installments' => $this->_paypalConfig->isEnableMsi(),
                        'enable_vaulting' => $this->_paypalConfig->isEnableVaulting(),
                        'card_fisrt_acdc' => $this->_paypalConfig->isCardFirstAcdc()
                    ],
                    'splitOptions' => [
                        'title_method_paypal' => $this->_paypalConfig->getConfigValue(\PayPal\CommercePlatform\Model\Config::CONFIG_XML_TITLE_METHOD_PAYPAL),
                        'title_method_card'   => $this->_paypalConfig->getConfigValue(\PayPal\CommercePlatform\Model\Config::CONFIG_XML_TITLE_METHOD_CARD),
                        'title_method_oxxo'   => $this->_paypalConfig->getConfigValue(\PayPal\CommercePlatform\Model\Config::CONFIG_XML_TITLE_METHOD_OXXO),
                    ],
                    self::SDK_CONFIG_DEBUG => $this->_paypalConfig->isSetFLag(\PayPal\CommercePlatform\Model\Config::CONFIG_XML_DEBUG_MODE),
                    'fraudNet' => [
                        'sourceWebIdentifier' => $this->_paypalConfig->getConfigValue(\PayPal\CommercePlatform\Model\Config::CONFIG_XML_FRAUDNET_SWI),
                        'fncls' => $this->_paypalConfig->getConfigValue(\PayPal\CommercePlatform\Model\Config::CONFIG_XML_FRAUDNET_FNCLS),
                        'sessionIdentifier' => 'M2' . bin2hex(random_bytes(self::LENGTH_IDENTIFIER)),
                    ]
                ]
            ]
        ];

        $this->_logger->debug(__METHOD__ . ' | CONFIG ' . print_r($config, true));

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
            self::SDK_CONFIG_CLIENT_ID  => $this->_paypalConfig->getClientId(),
            self::SDK_CONFIG_CURRENCY   => $this->_paypalConfig->getCurrency(),
            self::SDK_CONFIG_DEBUG      => $this->_paypalConfig->isSetFLag(\PayPal\CommercePlatform\Model\Config::CONFIG_XML_DEBUG_MODE) ? 'true' : 'false',
            self::SDK_CONFIG_LOCALE     => $this->_paypalConfig->getLocale(),
            self::SDK_CONFIG_INTENT     => 'capture',
        ];

        if($this->_paypalConfig->isEnableAcdc()){
            $this->_params[self::SDK_CONFIG_COMPONENTS] = 'hosted-fields,buttons';
        }
    }

    public function canRemember()
    {
        return ($this->_paypalConfig->isEnableAcdc() && $this->_paypalConfig->isEnableVaulting());
    }

    private function validateCustomerId()
    {
        if ($this->_customerSession->isLoggedIn()) {
            return $this->_customerSession->getCustomerId();
        }

        return null;
    }

    private function getCustomerAgreements($customerId){
        $agreements = $this->_billingAgreement->getAvailableCustomerBillingAgreements($customerId);
        $agreementsIds = [];
        foreach ($agreements as $agreement){
            $agreementsIds[] = [
                'id' => $agreement->getAgreementId(),
                'reference' => $agreement->getReferenceId(),
                'email' => $agreement->getPayerEmail(),
                'status' => $agreement->getStatus()
            ];
        }

        return $agreementsIds;
    }

    private function getCustomerPaymentTokens($customerId)
    {
        if(!$this->validateCustomerId()){
            return [];
        }

        $paymentTokens = [];

        $response = $this->_paypalApi->execute(new \PayPal\CommercePlatform\Model\Paypal\Vault\PaymentTokensRequest($customerId));

        if ($response->statusCode == 200) {

            foreach ($response->result->payment_tokens as $token) {

                if (property_exists($token->source, 'card')) {
                    $paymentTokens['cards'][] = [
                        'id' => $token->id,
                        'brand' => $token->source->card->brand,
                        'last_digits' => $token->source->card->last_digits
                    ];
                }
            }
            if(isset($paymentTokens['cards'])) {
                $this->getCalculatedFinancialOptions($paymentTokens);
            }

        }
        return $paymentTokens;
    }

    private function getCalculatedFinancialOptions(&$paymentTokens)
    {

        foreach ($paymentTokens['cards'] as $index => $payment) {
            $this->_calculatedFinancialOptionsRequest->body = [
                "financing_country_code" => $this->_paypalConfig->getCountryCode(),
                "transaction_amount" => [
                    "value" => $this->_checkoutSession->getQuote()->getGrandTotal(),
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

            $response = $this->_paypalApi->execute($this->_calculatedFinancialOptionsRequest);

            if ($response->statusCode == 200) {
                $paymentTokens['cards'][$index]['financing_options'] = $response->result->financing_options;
            }
        }
    }
}
