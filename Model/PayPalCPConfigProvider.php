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

    const XML_PATH_CONFIG_PREFIX = 'payment/paypalcp';

    const XML_PATH_CONFIG_CLIENT_ID  = 'client_id';
    const XML_PATH_CONFIG_CURRENCY   = 'currency';
    const XML_PATH_CONFIG_DEBUG      = 'debug';
    const XML_PATH_CONFIG_COMPONENTS = 'components';
    const XML_PATH_CONFIG_LOCALE     = 'locale';
    const XML_PATH_CONFIG_INTENT     = 'intent';

    /**
     * Button customization style options
     */
    const XML_PATH_CONFIG_LAYOUT  = 'payment/paypalcp/checkout_button/layout';
    const XML_PATH_CONFIG_COLOR   = 'payment/paypalcp/checkout_button/color';
    const XML_PATH_CONFIG_SHAPE   = 'payment/paypalcp/checkout_button/shape';
    const XML_PATH_CONFIG_LABEL   = 'payment/paypalcp/checkout_button/label';
    const XML_PATH_CONFIG_TAGLINE = 'payment/paypalcp/checkout_button/tagline';

    protected $_payment_code = \PayPal\CommercePlatform\Model\Payment\PayPalAbstract::COMMERCE_PLATFORM_CODE;
    protected $_params = [
        'currency' => 'MXN',
    ];

    /** @var \Magento\Framework\App\Config\ScopeConfigInterface */
    protected $_scopeConfig;

    /** @var \Magento\Customer\Model\Session */
    protected $_customerSession;

    /** @var \Magento\Framework\Session\SessionManagerInterface $session */
    protected $_session;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\Session\SessionManagerInterface $session
    ) {
        $this->_scopeConfig     = $scopeConfig;
        $this->_customerSession = $customerSession;
        $this->_session         = $session;
    }

    public function getConfig()
    {

        $clientId = 'AT6lRUtL67ziOZ2BRJ6g_5s0qo1BmKcdqXxjB5n9IRwlfy-i-UXCV1Bf0VeWRAhLPtsFdZDqPXKfzG-o';
        $clientSecret = 'EF4ELYtqJKj9ixVpvQIAugwaqaZwqDZ-erCEYkbWnDrkeTjhI2o2j7y1IDXzs01WOcRCAiACTQrY7TZJ';

        $authorizationBasic = 'Basic ' . base64_encode($clientId . ':' . $clientSecret);

        return [
            'payment' => [
                $this->_payment_code => [
                    'urlSdk' => $this->getUrlSdk(),
                    'style'  => [
                        'layout'  => $this->getStoreConfig(self::XML_PATH_CONFIG_LAYOUT),
                        'color'   => $this->getStoreConfig(self::XML_PATH_CONFIG_COLOR),
                        'shape'   => $this->getStoreConfig(self::XML_PATH_CONFIG_SHAPE),
                        'label'   => $this->getStoreConfig(self::XML_PATH_CONFIG_LABEL),
                        //'tagline' => $this->getStoreConfig(self::XML_PATH_CONFIG_TAGLINE),
                    ],
                    'urlAccessToken' => self::URL_ACCESS_TOKEN,
                    'urlGenerateClientToken' => self::URL_GENERATE_CLIENT_TOKEN,
                    'authorizationBasic' => $authorizationBasic,
                    'customerId' => $this->_customerSession->getCustomerId() ?? $this->_customerSession->getId()
                ]
            ]
        ];
    }

    public function getUrlSdk()
    {
        $this->buildParams();

        return self::BASE_URL_SDK . http_build_query($this->_params);
    }

    /**
     * Get payment store config
     * @return string
     */
    public function getStoreConfig($configPath)
    {
        $value =  $this->_scopeConfig->getValue(
            $configPath,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        return $value;
    }

    public function getPaypalCPConfig($configPath)
    {
        return $this->getStoreConfig(self::XML_PATH_CONFIG_PREFIX . '/' . $configPath);
    }

    private function buildParams()
    {
        //intent=authorize&currency=MXN&debug=true&components=hosted-fields,buttons&locale=es_MX&client-id=ATKh1gdUgHyPWMy6QRIp0XfGB92ZsX67HEnJeFB_j82p9u3j6w4s4C39Fgg8SkkRpn3MirI_TtVmhdNf

        $this->_params = [
            'client-id'  => $this->getPaypalCPConfig(self::XML_PATH_CONFIG_CLIENT_ID) ?? 'AT6lRUtL67ziOZ2BRJ6g_5s0qo1BmKcdqXxjB5n9IRwlfy-i-UXCV1Bf0VeWRAhLPtsFdZDqPXKfzG-o',

            self::XML_PATH_CONFIG_CURRENCY   => $this->getPaypalCPConfig(self::XML_PATH_CONFIG_CLIENT_ID) ?? 'MXN',
            self::XML_PATH_CONFIG_DEBUG      => $this->getPaypalCPConfig(self::XML_PATH_CONFIG_CLIENT_ID),
            self::XML_PATH_CONFIG_COMPONENTS => $this->getPaypalCPConfig(self::XML_PATH_CONFIG_CLIENT_ID) ?? 'hosted-fields,buttons',
            self::XML_PATH_CONFIG_LOCALE     => $this->getPaypalCPConfig(self::XML_PATH_CONFIG_CLIENT_ID) ?? 'es_MX',
            self::XML_PATH_CONFIG_INTENT     => $this->getPaypalCPConfig(self::XML_PATH_CONFIG_CLIENT_ID) ?? 'capture',
        ];
    }
}
