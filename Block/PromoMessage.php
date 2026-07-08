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
     * Get the promo message text.
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
     * Get the redirect URL for Learn More link.
     *
     * @return string
     */
    public function getPromoRedirectUrl()
    {
        return $this->config->getPromoRedirectUrl();
    }

    /**
     * Get compiled html with link for the promotional message.
     *
     * @return string
     */
    public function getPromoMessageHtml()
    {
        $text = $this->getPromoMessageText();
        $linkText = $this->getPromoLinkText();
        $url = $this->getPromoRedirectUrl();

        if (empty($text)) {
            return '';
        }

        // Add trailing space to message if not present
        if (substr($text, -1) !== ' ') {
            $text .= ' ';
        }

        if (!empty($url) && !empty($linkText)) {
            $text .= '<a href="' . $this->escapeUrl($url) . '" target="_blank" class="paypal-promo-link">' . $this->escapeHtml($linkText) . '</a>';
        }

        return $text;
    }
}
