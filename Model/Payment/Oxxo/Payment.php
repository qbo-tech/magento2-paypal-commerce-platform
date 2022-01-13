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

namespace PayPal\CommercePlatform\Model\Payment\Oxxo;

/**
 * Class Payment
 * @package PayPal\CommercePlatform\Model\Payment\Oxxo
 */
class Payment extends \PayPal\CommercePlatform\Model\Payment\Advanced\Payment
{
    const CODE                         = 'paypaloxxo';
    const SUCCESS_STATE_CODES          = array("PENDING", "PAYER_ACTION_REQUIRED");

    protected $_code = self::CODE;

    /**
     * @var \PayPal\CommercePlatform\Model\Paypal\Oxxo\ConfirmRequest
     */
    private $paypalOrderConfirmRequest;

    /**
     * Payment capturing
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return \PayPal\CommercePlatform\Model\Payment\Advanced\Payment
     * @throws \Magento\Framework\Validator\Exception
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $paypalOrderId = $payment->getAdditionalInformation('order_id');
        /** @var \Magento\Sales\Model\Order */
        $this->_order = $payment->getOrder();
        try {
            $this->paypalOrderConfirmRequest = $this->_paypalApi->getOrdersConfirmRequest($paypalOrderId);
            $paymentSource = json_decode($payment->getAdditionalInformation('payment_source'),1);
            $this->paypalOrderConfirmRequest->body = [
                'payment_source' => $paymentSource,
                'processing_instruction' => 'ORDER_COMPLETE_ON_PAYMENT_APPROVAL',
                'application_context' => [
                    'locale' => 'es-MX'
                ]
            ];

            $this->_eventManager->dispatch('paypaloxxo_order_capture_before', ['payment' => $payment]);
            $this->_response = $this->_paypalApi->execute($this->paypalOrderConfirmRequest);
            $this->_processTransaction($payment);
            $this->checkoutSession->setData("paypal_voucher", $this->_response->result->links[1]);
            $this->_eventManager->dispatch('paypaloxxo_order_capture_after', ['payment' => $payment]);
        } catch (\Exception $e) {
            $this->_logger->error(sprintf('[PAYPAL COMMERCE CONFIRMING ERROR] - %s', $e->getMessage()));
            $this->_logger->error(__METHOD__ . ' | Exception : ' . $e->getMessage());
            $this->_logger->error(__METHOD__ . ' | Exception response : ' . print_r($this->_response, true));
            throw new \Magento\Framework\Exception\LocalizedException(__(self::GATEWAY_ERROR_MESSAGE));
        }
        return $this;
    }

    /**
     * Process Payment Transaction based on response data
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @return \Magento\Payment\Model\InfoInterface $payment
     * @throws \Exception
     */
    protected function _processTransaction(&$payment): \Magento\Payment\Model\InfoInterface
    {
        if (!in_array($this->_response->statusCode, $this->_successCodes)) {
            throw new \Exception(__('Gateway error. Reason: %1', $this->_response->message));
        }

        $state = $this->_response->result->status;

        if (!$state || is_null($state) || !in_array($state, self::SUCCESS_STATE_CODES)) {
            throw new \Exception(__(self::GATEWAY_ERROR_MESSAGE));
        }

        $this->setComments($this->_order, __(self::PENDING_PAYMENT_NOTIFICATION), false);
        $payment->setIsTransactionPending(true)->setIsTransactionClosed(false);
        return $payment;
    }

}
