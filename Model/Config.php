<?php
namespace PayPal\CommercePlatform\Model;

use Magento\Payment\Helper\Formatter;

/**
* Config model that is aware of all \PayPal\CommercePlatform payment methods
*
* Works with PayPal Commerce Platform-specific system configuration
*/
class Config
{
    const COMMERCE_PLATFORM_CODE = 'paypalcp';

    const CONFIG_XML_IS_SANDBOX           = 'sandbox_flag';
    const CONFIG_XML_EMAIL_ADDRESS        = 'email_address';
    const CONFIG_XML_MERCHANT_ID          = 'merchant_id';
    const CONFIG_XML_CLIENT_ID            = 'client_id';
    const CONFIG_XML_CECRET_ID            = 'secret_id';
    const CONFIG_XML_TITLE                = 'title';
    const CONFIG_XML_INTENT               = 'intent';
    const CONFIG_XML_ENABLE_BCDC          = 'enable_bcdc';
    const CONFIG_XML_ENABLE_ACDC          = 'enable_acdc';
    const CONFIG_XML_ENABLE_INSTALLMENTS  = 'enable_installments';
    const CONFIG_XML_ENABLE_REMEMBER_CARD = 'enable_remember_card';
    const CONFIG_XML_CURRENCY_CODE        = 'currency';
    const CONFIG_XML_ENABLE_DEBUG         = 'enable_debug';
    const CONFIG_XML_TITLE_METHOD_PAYPAL  = 'title_paypal';
    const CONFIG_XML_TITLE_METHOD_CARD    = 'title_card';
    const CONFIG_XML_ENABLE_ITEMS         = 'enable_items';

    /**
     * Button customization style options
     */
    const XML_CONFIG_LAYOUT  = 'checkout_button/layout';
    const XML_CONFIG_COLOR   = 'checkout_button/color';
    const XML_CONFIG_SHAPE   = 'checkout_button/shape';
    const XML_CONFIG_LABEL   = 'checkout_button/label';
    const XML_CONFIG_TAGLINE = 'checkout_button/tagline';

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /** @var \Psr\Log\LoggerInterface */
    protected $_logger;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->_logger      = $logger;
    }

    /**
     * Check whether method active in configuration.
     *
     * @param string $method Method code
     * @return bool
     */
    public function isMethodActive($method)
    {
        $this->_logger->debug(__METHOD__ . ' | method ' . $method);

        $isEnabled = $this->_scopeConfig->isSetFlag(
            'payment/' . $method . '/active',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        return $isEnabled;
    }

    public function getConfigValue($config)
    {
        return $this->_scopeConfig->getValue(
            $this->_preparePathConfig($config),
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Retrieve config flag by path and scope
     * 
     * @param string $flag
     * @return bool
     */
    public function isSetFLag($flag)
    {
        return $this->_scopeConfig->isSetFlag(
            $this->_preparePathConfig($flag),
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    protected function _preparePathConfig($config, $code = self::COMMERCE_PLATFORM_CODE)
    {
        $this->_logger->debug(__METHOD__ . ' | config ' . sprintf("payment/%s/%s", $code, $config));

        return sprintf("payment/%s/%s", $code, $config);
    }

    public function isSandbox()
    {
        return $this->isSetFlag(self::CONFIG_XML_IS_SANDBOX);
    }

    public function getClientId()
    {
        return $this->getConfigValue(self::CONFIG_XML_CLIENT_ID);
    }

    public function getSecretId()
    {
        return $this->getConfigValue(self::CONFIG_XML_CECRET_ID);
    }

    public function getCurrency()
    {
        return $this->getConfigValue(self::CONFIG_XML_CURRENCY_CODE);
    }

    public function isEnableBcdc()
    {
        return $this->isSetFLag(self::CONFIG_XML_ENABLE_BCDC);
    }

    public function isEnableAcdc()
    {
        return $this->isSetFLag(self::CONFIG_XML_ENABLE_ACDC);
    }

    public function isEnableMsi()
    {
        return $this->isSetFLag(self::CONFIG_XML_ENABLE_INSTALLMENTS);
    }

    public function isEnableVaulting()
    {
        return $this->isSetFLag(self::CONFIG_XML_ENABLE_REMEMBER_CARD);
    }
}
