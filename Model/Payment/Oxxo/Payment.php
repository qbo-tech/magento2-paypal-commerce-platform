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

use Magento\Sales\Api\Data\TransactionInterface;

/**
 * Class Payment
 * @package PayPal\CommercePlatform\Model\Payment\Oxxo
 */
class Payment extends \PayPal\CommercePlatform\Model\Payment\Advanced\Payment
{
    const CODE                         = 'paypaloxxo';
    const SUCCESS_STATE_CODES          = array("PENDING", "PAYER_ACTION_REQUIRED");
    const OXXO_ERROR_MESSAGE           = 'There was an error while the oxxo voucher creation';
    protected $_code = self::CODE;

    /**
     * @var \PayPal\CommercePlatform\Model\Paypal\Oxxo\ConfirmRequest
     */
    private $paypalOrderConfirmRequest;

    private $paypalOrderId;

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
        try {
            $this->paypalOrderId = $payment->getAdditionalInformation('order_id');
            /** @var \Magento\Sales\Model\Order */
            $this->_order = $payment->getOrder();
            $this->_processTransaction($payment);

            if(!$this->paypalConfig->isSandbox()) {
                $this->sendOxxoEmail($this->paypalOrderId);
            }
        } catch (\Exception $e) {
            $this->_logger->error(sprintf('[PAYPAL COMMERCE CONFIRMING ERROR] - %s', $e->getMessage()));
            $this->_logger->error(__METHOD__ . ' | Exception : ' . $e->getMessage());
            $this->_logger->error(__METHOD__ . ' | Exception response : ' . print_r($this->_response, true));
            throw new \Magento\Framework\Exception\LocalizedException(__(self::GATEWAY_ERROR_MESSAGE));
        }
        return $this;
    }

    /**
     * Call oxxo to create voucher
     * @param $paymentSource
     * @param $paypalOrderId
     * @return $this
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function createOxxoVoucher($paymentSource, $paypalOrderId)
    {
        try {
            $this->paypalOrderConfirmRequest = $this->_paypalApi->getOrdersConfirmRequest($paypalOrderId);
            $this->paypalOrderConfirmRequest->body = [
                'payment_source' => [
                    'oxxo' => $paymentSource
                ],
                'processing_instruction' => 'ORDER_COMPLETE_ON_PAYMENT_APPROVAL',
                'application_context' => [
                    'locale' => 'es-MX'
                ]
            ];

            $this->_eventManager->dispatch('paypaloxxo_create_voucher_before');
            $this->_response = $this->_paypalApi->execute($this->paypalOrderConfirmRequest);
            $this->checkoutSession->setData("paypal_voucher", $this->_response->result->links[1]);
            $this->checkoutSession->setData("paypal_order_id", $paypalOrderId);
            $this->_eventManager->dispatch('paypaloxxo_create_voucher_after');
        } catch (\Exception $e) {
            $this->_logger->error(sprintf('[PAYPAL COMMERCE CONFIRMING ERROR] - %s', $e->getMessage()));
            $this->_logger->error(__METHOD__ . ' | Exception : ' . $e->getMessage());
            $this->_logger->error(__METHOD__ . ' | Exception response : ' . print_r($this->_response, true));
            throw new \Magento\Framework\Exception\LocalizedException(__(self::OXXO_ERROR_MESSAGE));
        }
        return $this->_response;
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
        $oxxoData = [
            'paypal_order_id' => $this->paypalOrderId
        ];
        $payment->setLastTransId($this->paypalOrderId);
        $payment->setTransactionId($this->paypalOrderId);
        $payment->setAdditionalInformation(
            ['paypal_order_id' => $this->paypalOrderId]
        );

        $message = __('Order created with Paypal OxxoPay');
        $trans = $this->transactionBuilderInterface;
        $transaction = $trans->setPayment($payment)
            ->setOrder($this->_order)
            ->setTransactionId($this->paypalOrderId)
            ->setAdditionalInformation(
                [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => $oxxoData]
            )
            ->setFailSafe(true)
            ->build(TransactionInterface::TYPE_CAPTURE);

        $payment->addTransactionCommentsToOrder(
            $transaction,
            $message
        );
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
            } else {
                throw new \Exception(self::OXXO_ERROR_MESSAGE);
            }
        } catch (\Exception $e) {
            $this->_logger->error(__METHOD__ . ' | PAYPAL OXXO EmailException : ' . $e->getMessage());
            throw new \Magento\Framework\Exception\LocalizedException(__(self::OXXO_ERROR_MESSAGE));
        }
    }

    /**
     * @param $voucherUrl
     * @return void
     */
    public function sendEmail($voucherUrl)
    {
        $templateId = 'oxxo_paypment_voucher';
        $toEmail = $this->_order->getCustomerEmail();

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
