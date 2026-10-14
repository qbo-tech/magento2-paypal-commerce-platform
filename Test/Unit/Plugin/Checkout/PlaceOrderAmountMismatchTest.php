<?php

namespace PayPal\CommercePlatform\Test\Unit\Plugin\Checkout;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Webapi\Exception as WebapiException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Magento\Quote\Model\Quote;
use PayPal\CommercePlatform\Exception\AmountMismatchException;
use PayPal\CommercePlatform\Plugin\Checkout\PlaceOrderAmountMismatch;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PlaceOrderAmountMismatchTest extends TestCase
{
    /**
     * @var CartRepositoryInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $cartRepository;

    /**
     * @var MaskedQuoteIdToQuoteIdInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $maskedQuoteIdToQuoteId;

    /**
     * @var PlaceOrderAmountMismatch
     */
    private $plugin;

    protected function setUp(): void
    {
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->maskedQuoteIdToQuoteId = $this->createMock(MaskedQuoteIdToQuoteIdInterface::class);
        $this->plugin = new PlaceOrderAmountMismatch(
            $this->cartRepository,
            $this->maskedQuoteIdToQuoteId,
            $this->createMock(LoggerInterface::class)
        );
    }

    private function createQuoteMock()
    {
        return $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['collectTotals'])
            ->addMethods(['setTotalsCollectedFlag'])
            ->getMock();
    }

    private function mismatchProceed()
    {
        $mismatch = new AmountMismatchException(__('We detected a change in your cart amount. Please try your payment again.'));

        return function () use ($mismatch) {
            throw new CouldNotSaveException(__($mismatch->getMessage()), $mismatch);
        };
    }

    public function testReturnsOrderIdWhenPlaceOrderSucceeds()
    {
        $proceed = function ($cartId, $paymentMethod) {
            return 123;
        };

        $this->cartRepository->expects($this->never())->method('save');
        $this->assertSame(123, $this->plugin->aroundSavePaymentInformationAndPlaceOrder(new \stdClass(), $proceed, 5, 'method'));
    }

    public function testPassesGuestArgumentsThrough()
    {
        $received = null;
        $proceed = function (...$args) use (&$received) {
            $received = $args;
            return 7;
        };

        $this->plugin->aroundSavePaymentInformationAndPlaceOrder(new \stdClass(), $proceed, 'masked', 'guest@example.com', 'method', null);

        $this->assertSame(['masked', 'guest@example.com', 'method', null], $received);
    }

    public function testMapsMismatchToConflictAndRecollectsGuestCart()
    {
        $quote = $this->createQuoteMock();
        $quote->expects($this->once())->method('setTotalsCollectedFlag')->with(false)->willReturnSelf();
        $quote->expects($this->once())->method('collectTotals')->willReturnSelf();

        $this->maskedQuoteIdToQuoteId->method('execute')->with('maskedId')->willReturn(42);
        $this->cartRepository->method('getActive')->with(42)->willReturn($quote);
        $this->cartRepository->expects($this->once())->method('save')->with($quote);

        try {
            $this->plugin->aroundSavePaymentInformationAndPlaceOrder(new \stdClass(), $this->mismatchProceed(), 'maskedId', 'guest@example.com', 'method');
            $this->fail('A WebapiException was expected');
        } catch (WebapiException $e) {
            $this->assertSame(409, $e->getHttpCode());
            $this->assertSame('paypal_amount_mismatch', $e->getDetails()['error_code']);
        }
    }

    public function testRecollectsCustomerCartById()
    {
        $quote = $this->createQuoteMock();
        $quote->method('setTotalsCollectedFlag')->willReturnSelf();
        $quote->method('collectTotals')->willReturnSelf();

        $this->maskedQuoteIdToQuoteId->expects($this->never())->method('execute');
        $this->cartRepository->method('getActive')->with(15)->willReturn($quote);
        $this->cartRepository->expects($this->once())->method('save')->with($quote);

        $this->expectException(WebapiException::class);
        $this->plugin->aroundSavePaymentInformationAndPlaceOrder(new \stdClass(), $this->mismatchProceed(), 15, 'method');
    }

    public function testStillReturnsConflictWhenRecollectFails()
    {
        $this->maskedQuoteIdToQuoteId->method('execute')->willThrowException(new \Exception('not found'));

        $this->expectException(WebapiException::class);
        $this->plugin->aroundSavePaymentInformationAndPlaceOrder(new \stdClass(), $this->mismatchProceed(), 'maskedId', 'guest@example.com', 'method');
    }

    public function testRethrowsOtherErrorsUntouched()
    {
        $original = new CouldNotSaveException(__('Payment has been declined by Payment Gateway'), new LocalizedException(__('declined')));
        $proceed = function () use ($original) {
            throw $original;
        };

        $this->cartRepository->expects($this->never())->method('save');

        try {
            $this->plugin->aroundSavePaymentInformationAndPlaceOrder(new \stdClass(), $proceed, 'cart', 'method');
            $this->fail('The original exception was expected');
        } catch (CouldNotSaveException $e) {
            $this->assertSame($original, $e);
        }
    }
}
