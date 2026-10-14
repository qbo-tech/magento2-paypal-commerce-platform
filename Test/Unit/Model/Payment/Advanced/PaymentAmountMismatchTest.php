<?php

namespace PayPal\CommercePlatform\Test\Unit\Model\Payment\Advanced;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use PayPal\CommercePlatform\Exception\AmountMismatchException;
use PayPal\CommercePlatform\Model\Payment\Advanced\Payment;
use PayPal\CommercePlatform\Model\Paypal\Api;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PaymentAmountMismatchTest extends TestCase
{
    /**
     * @param float $paypalAmount
     * @param float $orderTotal
     * @param bool $stopFlow
     * @return void
     */
    private function validate($paypalAmount, $orderTotal, $stopFlow)
    {
        $method = $this->getMockBuilder(Payment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getConfigValue'])
            ->getMock();
        $method->method('getConfigValue')->with('stop_on_amount_mismatch')->willReturn($stopFlow ? '1' : '0');

        $api = $this->createMock(Api::class);
        $api->method('getOrdersGetRequest')->willReturn(new \PayPalHttp\HttpRequest('/v2/checkout/orders/PAYPALORDER', 'GET'));
        $api->method('execute')->willReturn((object)[
            'result' => (object)['purchase_units' => [(object)['amount' => (object)['value' => (string)$paypalAmount]]]],
        ]);

        $order = $this->createMock(Order::class);
        $order->method('getGrandTotal')->willReturn($orderTotal);
        $order->method('getIncrementId')->willReturn('000000001');

        $payment = $this->createMock(OrderPayment::class);
        $payment->method('getOrder')->willReturn($order);

        $this->setProperty($method, '_paypalApi', $api);
        $this->setProperty($method, '_logger', $this->createMock(LoggerInterface::class));

        $validate = new \ReflectionMethod(Payment::class, 'validatePayPalOrderAmountBeforeCapture');
        $validate->setAccessible(true);
        $validate->invoke($method, $payment, 'PAYPALORDER');
    }

    private function setProperty($object, $name, $value)
    {
        $property = new \ReflectionProperty(Payment::class, $name);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }

    public function testThrowsCustomerMessageWhenAmountsDifferAndFlowMustStop()
    {
        $this->expectException(AmountMismatchException::class);
        $this->expectExceptionMessage('We detected a change in your cart amount. Please try your payment again.');

        $this->validate(805.00, 1610.00, true);
    }

    public function testOnlyLogsWhenAmountsDifferAndFlowMustNotStop()
    {
        $this->validate(805.00, 1610.00, false);
        $this->addToAssertionCount(1);
    }

    public function testPassesWhenAmountsMatchToTheCent()
    {
        $this->validate(1610.004, 1610.00, true);
        $this->addToAssertionCount(1);
    }
}
