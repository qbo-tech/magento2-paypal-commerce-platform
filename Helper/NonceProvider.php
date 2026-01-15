<?php

namespace PayPal\CommercePlatform\Helper;

use Magento\Csp\Model\Collector\DynamicCollector;
use Magento\Csp\Model\Policy\FetchPolicy;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\Random;

/**
 * Custom CSP Nonce Provider Helper
 */
class NonceProvider
{
    private const NONCE_LENGTH = 32;

    /**
     * @var string|null
     */
    private ?string $nonce = null;

    /**
     * @var Random
     */
    private Random $random;

    /**
     * @var DynamicCollector
     */
    private DynamicCollector $dynamicCollector;

    public function __construct(
        Random $random,
        DynamicCollector $dynamicCollector
    ) {
        $this->random = $random;
        $this->dynamicCollector = $dynamicCollector;
    }

    /**
     * Generate nonce and add it to the CSP header
     *
     * @return string
     * @throws LocalizedException
     */
    public function generateNonce(): string
    {
        if ($this->nonce === null) {
            $this->nonce = $this->random->getRandomString(
                self::NONCE_LENGTH,
                Random::CHARS_DIGITS . Random::CHARS_LOWERS
            );

            $policy = new FetchPolicy(
                'script-src',
                false,
                [],
                [],
                false,
                false,
                false,
                [$this->nonce],
                []
            );

            $this->dynamicCollector->add($policy);
        }

        return base64_encode($this->nonce);
    }
}
