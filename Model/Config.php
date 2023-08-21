<?php
/**
 * @author Alvaro Florez
 */
namespace PayPal\CommercePlatform\Model;

use Magento\Payment\Helper\Formatter;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
* Config model that is aware of all \PayPal\CommercePlatform payment methods
*
* Works with PayPal Commerce Platform-specific system configuration
*/
class Config
{
    const PAYMENT_COMMERCE_PLATFORM_CODE = 'paypalcp';

    const CONFIG_XML_IS_SANDBOX           = 'sandbox_flag';
    const CONFIG_XML_EMAIL_ADDRESS        = 'email_address';
    const CONFIG_XML_MERCHANT_ID          = 'merchant_id';
    const CONFIG_XML_CLIENT_ID            = 'client_id';
    const CONFIG_XML_SECRET_ID            = 'secret_id';
    const CONFIG_XML_WEBHOOK_ID           = 'webhook_id';
    const CONFIG_XML_TITLE                = 'title';
    const CONFIG_XML_INTENT               = 'intent';
    const CONFIG_XML_ENABLE_BCDC          = 'enable_bcdc';
    const CONFIG_XML_ENABLE_ACDC          = 'enable_acdc';
    const CONFIG_XML_CARD_FIRST_ACDC      = 'card_fisrt_acdc';
    const CONFIG_XML_ENABLE_OXXO          = 'enable_oxxo';
    const CONFIG_XML_ENABLE_INSTALLMENTS  = 'enable_installments';
    const CONFIG_XML_ENABLE_REMEMBER_CARD = 'enable_remember_card';
    const CONFIG_XML_LOCALE_CODE          = 'locale';
    const CONFIG_XML_COUNTRY_CODE         = 'country_code';
    const CONFIG_XML_ENABLE_DEBUG         = 'enable_debug';
    const CONFIG_XML_TITLE_METHOD_PAYPAL  = 'title_paypal';
    const CONFIG_XML_TITLE_METHOD_CARD    = 'title_card';
    const CONFIG_XML_TITLE_METHOD_OXXO    = 'title_oxxo';
    const CONFIG_XML_ENABLE_ITEMS         = 'enable_items';
    const CONFIG_XML_DEBUG_MODE           = 'debug_mode';
    const CONFIG_XML_FRAUDNET_SWI         = 'source_web_identifier';
    const CONFIG_XML_FRAUDNET_FNCLS       = 'fncls';

    const CONFIG_XML_ENABLE_REFERENCE_TRANSACTION  = 'enable_reference_transaction';

    /** STC CONFIGS */

    const CONFIG_XML_ENABLE_STC                    = 'enable_stc';
    const CONFIG_XML_ENABLE_STC_MERCHANT_ID        = 'stc_merchant_id';
    const CONFIG_XML_ENABLE_STC_HIGHSRISK_TXN_FLAG = 'stc_highrisk_txn_flag';
    const CONFIG_XML_ENABLE_STC_VERTICAL           = 'stc_vertical';
    const CONFIG_XML_MSI_3 = 'msi3';
    const CONFIG_XML_MSI_4 = 'msi4';
    const CONFIG_XML_MSI_6 = 'msi6';
    const CONFIG_XML_MSI_9 = 'msi9';
    const CONFIG_XML_MSI_12 = 'msi12';
    const CONFIG_XML_MSI_18 = 'msi18';
    const CONFIG_XML_MSI_24 = 'msi24';

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
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    private $resolverInterface;
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Psr\Log\LoggerInterface $logger,
        StoreManagerInterface $storeManager,
        ResolverInterface $resolverInterface
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->_logger      = $logger;
        $this->resolverInterface = $resolverInterface;
        $this->storeManager = $storeManager;
    }

    /**
     * Check whether method active in configuration.
     *
     * @param string $method Method code
     * @return bool
     */
    public function isMethodActive($method)
    {
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
    public function isSetFlag($flag)
    {
        return $this->_scopeConfig->isSetFlag(
            $this->_preparePathConfig($flag),
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getMSIMinimum()
    {
        return [
            '3'  =>  $this->getConfigValue(self::CONFIG_XML_MSI_3),
            '4'  =>  $this->getConfigValue(self::CONFIG_XML_MSI_4),
            '6'  =>  $this->getConfigValue(self::CONFIG_XML_MSI_6),
            '9'  =>  $this->getConfigValue(self::CONFIG_XML_MSI_9),
            '12' =>  $this->getConfigValue(self::CONFIG_XML_MSI_12),
            '18' =>  $this->getConfigValue(self::CONFIG_XML_MSI_18),
            '24' =>  $this->getConfigValue(self::CONFIG_XML_MSI_24)
        ];
    }

    protected function _preparePathConfig($config, $code = self::PAYMENT_COMMERCE_PLATFORM_CODE)
    {
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
        return $this->getConfigValue(self::CONFIG_XML_SECRET_ID);
    }

    public function getWebhookId()
    {
        return $this->getConfigValue(self::CONFIG_XML_WEBHOOK_ID);
    }

    public function getCurrency()
    {
        return $this->storeManager->getStore()->getBaseCurrency()->getCode();
    }

    public function getLocale()
    {
        $locale = $this->getConfigValue(self::CONFIG_XML_LOCALE_CODE);
        if (!$locale) {
            $locale = $this->resolverInterface->getLocale();
        }
        return $locale;
    }

    public function getCountryCode()
    {
        return $this->getConfigValue(self::CONFIG_XML_COUNTRY_CODE);
    }

    public function isEnableReferenceTransaction()
    {
        return $this->isSetFLag(self::CONFIG_XML_ENABLE_REFERENCE_TRANSACTION);
    }

    public function isEnableBcdc()
    {
        return $this->isSetFLag(self::CONFIG_XML_ENABLE_BCDC);
    }

    public function isEnableOxxo()
    {
        return $this->getConfigValue(self::CONFIG_XML_ENABLE_OXXO);
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

    public function isEnableStc()
    {
        return ($this->isSetFlag(self::CONFIG_XML_ENABLE_STC) && (!empty($this->getStcMerchantId())));
    }

    public function getStcMerchantId()
    {
        return $this->getConfigValue(self::CONFIG_XML_ENABLE_STC_MERCHANT_ID);
    }

    public function getHighriskTxnFlag()
    {
        return $this->getConfigValue(self::CONFIG_XML_ENABLE_STC_HIGHSRISK_TXN_FLAG);
    }

    public function getVertical()
    {
        return $this->getConfigValue(self::CONFIG_XML_ENABLE_STC_VERTICAL);
    }

    public function isCardFirstAcdc()
    {
        return $this->isSetFLag(self::CONFIG_XML_CARD_FIRST_ACDC);
    }
}
