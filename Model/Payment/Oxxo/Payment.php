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
            $this->checkoutSession->setData("paypal_order_id", $paypalOrderId);
            $this->_eventManager->dispatch('paypaloxxo_order_capture_after', ['payment' => $payment]);

            //$this->sendOxxoEmail($paypalOrderId);
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

    /**
     * @param $paypalOrderId
     * @return void
     * @throws \Exception
     */
    public function sendOxxoEmail($paypalOrderId)
    {
        try {
            $voucherRequest = $this->_paypalApi->getVoucherRequest($paypalOrderId);
            $response = $this->_paypalApi->execute($voucherRequest);
            if (!in_array($response->statusCode, $this->_successCodes)) {
                throw new \Exception(__('Gateway error. Reason: %1', $response->message));
            }
            $this->_logger->debug(__METHOD__ . ' | PAYPAL OXXO data : ' . json_encode($response));
            if (isset($response->result->payment_source->oxxo->document_references[0])) {
                $voucherUrl = $response->result->payment_source->oxxo->document_references[0]->value;
                $this->sendEmail($voucherUrl);
            }
        } catch (\Exception $e) {
            $this->_logger->error(__METHOD__ . ' | PAYPAL OXXO EmailException : ' . $e->getMessage());
        }
    }

    /**
     * @param $voucherUrl
     * @return void
     */
    public function sendEmail($voucherUrl)
    {
        $templateId = 'oxxo_paypment_voucher';
        $order = $this->checkoutSession->getLastRealOrder();
        $toEmail = $order->getCustomerEmail();

        try {
            $this->_logger->debug(__METHOD__ . ' | PAYPAL OXXO url : ' . $voucherUrl);
            $templateVars = [
                'OxxoVoucher' => $voucherUrl
            ];

            $storeId = $this->storeManager->getStore()->getId();
            $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
            $templateOptions = [
                'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                'store' => $storeId
            ];
            $transport = $this->transportBuilder->setTemplateIdentifier($templateId, $storeScope)
                ->setTemplateOptions($templateOptions)
                ->setTemplateVars($templateVars)
                ->setFromByScope('sales', $storeId)
                ->addTo($toEmail)
                ->getTransport();
            $transport->sendMessage();
        } catch (\Exception $e) {
            $this->_logger->error($e->getMessage());
        }
    }
}
