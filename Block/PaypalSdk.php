<?php

namespace PayPal\CommercePlatform\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\App\ProductMetadataInterface;
use PayPal\CommercePlatform\Helper\NonceProvider;
use PayPal\CommercePlatform\Helper\PaypalToken;
use PayPal\CommercePlatform\Model\PayPalCPConfigProvider;

class PaypalSdk extends Template
{
    protected $paypalConfig;
    protected $paypalTokenHelper;
    protected $nonceProvider;
    protected $productMetadata;

    public function __construct(
        Template\Context $context,
        PayPalCPConfigProvider $paypalConfig,
        PaypalToken $paypalTokenHelper,
        NonceProvider $nonceProvider,
        ProductMetadataInterface $productMetadata,
        array $data = []
    ) {
        $this->paypalConfig = $paypalConfig;
        $this->paypalTokenHelper = $paypalTokenHelper;
        $this->nonceProvider = $nonceProvider;
        $this->productMetadata = $productMetadata;
        parent::__construct($context, $data);
    }

    public function getPaypalUrl()
    {
        return $this->paypalConfig->getUrlSdk();
    }

    public function getClientToken()
    {
        return $this->paypalTokenHelper->getClientToken();
    }

    public function isEnableVaulting()
    {
        return $this->paypalConfig->isEnableVaulting();
    }

    public function isEnableAcdc()
    {
        return $this->paypalConfig->isEnableAcdc();
    }

    public function isEnableBcdc()
    {
        return $this->paypalConfig->isEnableBcdc();
    }

    public function isEnableReferenceTransaction()
    {
        return $this->paypalConfig->isEnableReferenceTransaction();
    }

    public function isEnableOxxo()
    {
        return $this->paypalConfig->isEnableOxxo();
    }

    public function isPaypalActive()
    {
        return $this->paypalConfig->isPaypalActive();
    }

    public function isDebug()
    {
        return $this->paypalConfig->isDebug();
    }

    public function getNonce()
    {
        return $this->nonceProvider->generateNonce();
    }

    public function getMagentoVersion()
    {
        return $this->productMetadata->getVersion();
    }

    public function getModuleVersion()
    {
        $composerFile = dirname(__DIR__) . '/composer.json';
        if (!is_readable($composerFile)) {
            return 'unknown';
        }

        $composer = json_decode((string)file_get_contents($composerFile), true);

        return $composer['version'] ?? 'unknown';
    }


}
