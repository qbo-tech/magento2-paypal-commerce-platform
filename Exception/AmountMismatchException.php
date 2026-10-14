<?php

namespace PayPal\CommercePlatform\Exception;

use Magento\Framework\Exception\LocalizedException;

/**
 * Raised when the PayPal order amount differs from the Magento order total before capture
 */
class AmountMismatchException extends LocalizedException
{
}
