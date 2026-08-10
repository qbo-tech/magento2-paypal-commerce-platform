<?php

namespace PayPal\CommercePlatform\Block;

use Magento\Framework\View\Element\Template;
use PayPal\CommercePlatform\Model\Config;

class PromoMessage extends Template
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @param Template\Context $context
     * @param Config $config
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Config $config,
        array $data = []
    ) {
        $this->config = $config;
        parent::__construct($context, $data);
    }

    /**
     * Check if promo messaging is enabled for the current block instance.
     *
     * @return bool
     */
    public function isEnabled()
    {
        $pageType = $this->getData('page_type');
        if ($pageType === 'pdp') {
            return $this->config->isPromoPdpEnabled();
        } elseif ($pageType === 'cart') {
            return $this->config->isPromoCartEnabled();
        }
        return false;
    }

    /**
     * Get the promo message text (PDP / Cart).
     *
     * @return string
     */
    public function getPromoMessageText()
    {
        return $this->config->getPromoMessageText();
    }

    /**
     * Get the promo link text.
     *
     * @return string
     */
    public function getPromoLinkText()
    {
        return $this->config->getPromoLinkText();
    }

    /**
     * Get the redirect URL for the promo link.
     *
     * @return string
     */
    public function getPromoRedirectUrl()
    {
        return $this->config->getPromoRedirectUrl();
    }
}
