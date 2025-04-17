<?php

namespace PayPal\CommercePlatform\Block;

use Magento\Framework\View\Element\Template;
use PayPal\CommercePlatform\Helper\PaypalToken;
use PayPal\CommercePlatform\Model\PayPalCPConfigProvider;

class PaypalSdk extends Template
{
    protected $paypalConfig;
    protected $paypalTokenHelper;

    public function __construct(
        Template\Context $context,
        PayPalCPConfigProvider $paypalConfig,
        PaypalToken $paypalTokenHelper,
        array $data = []
    ) {
        $this->paypalConfig = $paypalConfig;
        $this->paypalTokenHelper = $paypalTokenHelper;
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

    public function isDebug()
    {
        return $this->paypalConfig->isDebug();
    }


}
