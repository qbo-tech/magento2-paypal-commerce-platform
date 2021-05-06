<?php

namespace PayPal\CommercePlatform\Model;

/**
 * Class ConfigProvider
 */
class PaypalCPConfigProvider implements \Magento\Checkout\Model\ConfigProviderInterface
{
    const BASE_URL_SDK = 'https://www.paypal.com/sdk/js?';
    const URL_ACCESS_TOKEN = 'https://api.sandbox.paypal.com/v1/oauth2/token';
    const URL_GENERATE_CLIENT_TOKEN = 'https://api.sandbox.paypal.com/v1/identity/generate-token';
/*
    const XML_PATH_CONFIG_PREFIX = 'payment/paypalcp';
*/
    const SDK_CONFIG_CLIENT_ID  = 'client_id';
    const SDK_CONFIG_CURRENCY   = 'currency';
    const SDK_CONFIG_DEBUG      = 'debug';
    const SDK_CONFIG_COMPONENTS = 'components';
    const SDK_CONFIG_LOCALE     = 'locale';
    const SDK_CONFIG_INTENT     = 'intent';


    protected $_payment_code = \PayPal\CommercePlatform\Model\Payment\PayPalAbstract::COMMERCE_PLATFORM_CODE;
    protected $_params = [];

    /** @var \PayPal\CommercePlatform\Model\Config */
    protected $_paypalConfig;

    /** @var \Magento\Customer\Model\Session */
    protected $_customerSession;

    /** @var \Magento\Framework\Session\SessionManagerInterface $session */
    protected $_session;

    /** @var \PayPal\CommercePlatform\Logger\Handler */
    protected $_logger;

    public function __construct(
        \PayPal\CommercePlatform\Model\Config $paypalConfig,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Session\SessionManagerInterface $session,
        \PayPal\CommercePlatform\Logger\Handler $logger
    ) {
        $this->_paypalConfig    = $paypalConfig;
        $this->_customerSession = $customerSession;
        $this->_session         = $session;
        $this->_logger          = $logger;

        $this->_params['currency'] = $this->_paypalConfig->getCurrency();
    }

    public function getConfig()
    {
        $authorizationBasic = 'Basic ' . base64_encode($this->_paypalConfig->getClientId() . ':' . $this->_paypalConfig->getSecretId());

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
                        //'tagline' => $this->_paypalConfig->getConfigValue(\PayPal\CommercePlatform\Model\Config::XML_CONFIG_TAGLINE),
                    ],
                    'urlAccessToken' => self::URL_ACCESS_TOKEN,
                    'urlGenerateClientToken' => self::URL_GENERATE_CLIENT_TOKEN,
                    'authorizationBasic' => $authorizationBasic,
                    'customerId' => $this->_customerSession->getCustomerId() ?? $this->_customerSession->getId(),
                    'bcdc' => [
                        'enable' => $this->_paypalConfig->isEnableBcdc(),
                    ],
                    'acdc' => [
                        'enable' => $this->_paypalConfig->isEnableAcdc(),
                        'enable_installments' => $this->_paypalConfig->isEnableMsi(),
                        'enable_vaulting' => $this->_paypalConfig->isEnableVaulting(),
                    ],
                    'splitOptions' => [
                        'title_method_paypal' => $this->_paypalConfig->getConfigValue(\PayPal\CommercePlatform\Model\Config::CONFIG_XML_TITLE_METHOD_PAYPAL),
                        'title_method_card'   => $this->_paypalConfig->getConfigValue(\PayPal\CommercePlatform\Model\Config::CONFIG_XML_TITLE_METHOD_CARD),
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
        //intent=authorize&currency=MXN&debug=true&components=hosted-fields,buttons&locale=es_MX&client-id=ATKh1gdUgHyPWMy6QRIp0XfGB92ZsX67HEnJeFB_j82p9u3j6w4s4C39Fgg8SkkRpn3MirI_TtVmhdNf

        $this->_params = [
            'client-id'  => $this->_paypalConfig->getClientId(), //(self::SDK_CONFIG_CLIENT_ID) ?? 'AT6lRUtL67ziOZ2BRJ6g_5s0qo1BmKcdqXxjB5n9IRwlfy-i-UXCV1Bf0VeWRAhLPtsFdZDqPXKfzG-o',

            self::SDK_CONFIG_CURRENCY   => $this->_paypalConfig->getCurrency(), //(\PayPal\CommercePlatform\Model\Config:: $this->getPaypalCPConfig(self::XML_PATH_CONFIG_CLIENT_ID) ?? 'MXN',
            self::SDK_CONFIG_DEBUG      => $this->_paypalConfig->isSetFLag(\PayPal\CommercePlatform\Model\Config::CONFIG_XML_ENABLE_DEBUG) ? self::SDK_CONFIG_DEBUG : null,
            self::SDK_CONFIG_COMPONENTS => 'hosted-fields,buttons',
            self::SDK_CONFIG_LOCALE     => 'es_MX',
            self::SDK_CONFIG_INTENT     => 'capture',
        ];
    }
}
