<?php

namespace PayPal\CommercePlatform\Block;

use Magento\Framework\View\Element\Template;
use PayPal\CommercePlatform\Helper\NonceProvider;
use PayPal\CommercePlatform\Helper\PaypalToken;
use PayPal\CommercePlatform\Model\PayPalCPConfigProvider;

class PaypalSdk extends Template
{
    protected $paypalConfig;
    protected $paypalTokenHelper;
    protected $nonceProvider;

    public function __construct(
        Template\Context $context,
        PayPalCPConfigProvider $paypalConfig,
        PaypalToken $paypalTokenHelper,
        NonceProvider $nonceProvider,
        array $data = []
    ) {
        $this->paypalConfig = $paypalConfig;
        $this->paypalTokenHelper = $paypalTokenHelper;
        $this->nonceProvider = $nonceProvider;
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

    public function isDebug()
    {
        return $this->paypalConfig->isDebug();
    }

    public function getNonce()
    {
        return $this->nonceProvider->generateNonce();
    }

}
