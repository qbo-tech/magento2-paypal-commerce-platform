<?php

namespace PayPal\CommercePlatform\Plugin\Checkout;

use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use PayPal\CommercePlatform\Exception\AmountMismatchException;
use Psr\Log\LoggerInterface;

class PlaceOrderAmountMismatch
{
    const HTTP_CONFLICT = 409;

    const ERROR_CODE = 'paypal_amount_mismatch';

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * @var MaskedQuoteIdToQuoteIdInterface
     */
    private $maskedQuoteIdToQuoteId;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        CartRepositoryInterface $cartRepository,
        MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
        LoggerInterface $logger
    ) {
        $this->cartRepository = $cartRepository;
        $this->maskedQuoteIdToQuoteId = $maskedQuoteIdToQuoteId;
        $this->logger = $logger;
    }

    /**
     * Return HTTP 409 when the order could not be placed because of a PayPal amount mismatch
     *
     * Magento wraps every place order error in a generic 400 response, so the checkout
     * could not tell this case apart from the rest. The cart totals are recollected and
     * saved first, so the payment step reloads with the current amount.
     *
     * @param \Magento\Checkout\Api\PaymentInformationManagementInterface|\Magento\Checkout\Api\GuestPaymentInformationManagementInterface $subject
     * @param callable $proceed
     * @param mixed ...$arguments
     * @return int
     * @throws \Exception
     */
    public function aroundSavePaymentInformationAndPlaceOrder($subject, callable $proceed, ...$arguments)
    {
        try {
            return $proceed(...$arguments);
        } catch (\Exception $e) {
            $mismatch = $this->findAmountMismatch($e);

            if ($mismatch === null) {
                throw $e;
            }

            $this->recollectCartTotals($arguments[0] ?? null);

            throw new WebapiException(
                __($mismatch->getMessage()),
                0,
                self::HTTP_CONFLICT,
                ['error_code' => self::ERROR_CODE]
            );
        }
    }

    /**
     * @param int|string|null $cartId Numeric id for customers, masked id for guests
     * @return void
     */
    private function recollectCartTotals($cartId)
    {
        if ($cartId === null || $cartId === '') {
            return;
        }

        try {
            $quoteId = is_numeric($cartId) ? (int)$cartId : $this->maskedQuoteIdToQuoteId->execute((string)$cartId);
            $quote = $this->cartRepository->getActive($quoteId);
            $quote->setTotalsCollectedFlag(false)->collectTotals();
            $this->cartRepository->save($quote);
        } catch (\Exception $e) {
            $this->logger->error('[PAYPAL COMMERCE] Unable to recollect cart totals after amount mismatch: ' . $e->getMessage());
        }
    }

    /**
     * @param \Throwable $exception
     * @return AmountMismatchException|null
     */
    private function findAmountMismatch(\Throwable $exception)
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof AmountMismatchException) {
                return $current;
            }
        }

        return null;
    }
}
