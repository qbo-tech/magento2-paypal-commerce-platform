<?php
/**
 * Paypal SmartPaymentButton and Advanced credit and debit card payments for MX
 * Copyright (C) 2019
 *
 * This file included in Qbo/PaypalCheckout is licensed under OSL 3.0
 *
 * http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * Please see LICENSE.txt for the full text of the OSL 3.0 license
 */
namespace Paypal\CommercePlatform\Plugin\Model\Order\Payment\State;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Model\Order;

/**
 * Class OrderCommand
 * @package Paypal\CommercePlatform\Plugin\Model\Order\Payment\State
 */
class OrderCommand
{
	/**
	 * @param \Magento\Sales\Model\Order\Payment\State\OrderCommand $subject
	 * @param callable $proceed
	 * @param \Magento\Sales\Api\Data\OrderPaymentInterface $payment
	 * @param $amount
	 * @param \Magento\Sales\Api\Data\OrderInterface $order
	 * @return mixed
	 * @throws \Exception
	 */
	public function aroundExecute(
		\Magento\Sales\Model\Order\Payment\State\OrderCommand $subject,
		callable $proceed,
		OrderPaymentInterface $payment,
		$amount,
		OrderInterface $order
	) {
		if($payment->getMethod() != 'paypaloxxo') {
			return $proceed();
		} else {
			$state = Order::STATE_PENDING_PAYMENT;
			$message = 'Ordered amount of %1';
			$order->setState($state);
			return __($message, $order->getBaseCurrency()->formatTxt($amount));
		}
	}
}
