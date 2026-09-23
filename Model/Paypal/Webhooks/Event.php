<?php

/**
 * @author Alvaro Florez <info@qbo.tech>
 */

namespace PayPal\CommercePlatform\Model\Paypal\Webhooks;

class Event
{

    /**
     * Payment capture completed event type code
     */
    const PAYMENT_CAPTURE_COMPLETED = 'PAYMENT.CAPTURE.COMPLETED';
    /**
     * Payment capture pending  event type code
     */
    const PAYMENT_CAPTURE_PENDING = 'PAYMENT.CAPTURE.PENDING';
    /**
     * Payment capture refunded event type
     */
    const PAYMENT_CAPTURE_REFUNDED = 'PAYMENT.CAPTURE.REFUNDED';
    /**
     * Payment capture reversed event type code
     */
    const PAYMENT_CAPTURE_REVERSED = 'PAYMENT.CAPTURE.REVERSED';
    /**
     * Payment capture denied event type code
     */
    const PAYMENT_CAPTURE_DENIED  = 'PAYMENT.CAPTURE.DENIED';
    const CHECKOUT_ORDER_APPROVED = 'CHECKOUT.ORDER.APPROVED';

    /**
     * Payment additional information key holding the PayPal capture id
     */
    const TRANSACTION_ID_KEY = 'paypal_transaction_id';


    /** @var \Magento\Sales\Model\Order\Payment */
    protected $_payment;

    /**
     * @var \Magento\Sales\Model\Order\Payment\TransactionFactory
     */
    protected $_salesOrderPaymentTransactionFactory;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    protected $_orderRepository;

    /**
     * @var \Magento\Sales\Model\Order\Payment
     */
    protected $_paymentRepository;

    /**
     * @var  \Magento\Sales\Api\InvoiceRepositoryInterface
     */
    protected $_invoiceRepository;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    /**
    * @var \Magento\Sales\Api\OrderManagementInterface
    */
    protected $orderManagement;


    public function __construct(
        \Magento\Sales\Model\Order\Payment\TransactionFactory $salesOrderPaymentTransactionFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\Order\Payment $paymentRepository,
        \Magento\Sales\Api\InvoiceRepositoryInterface $invoiceRepository,
        \Magento\Sales\Api\OrderManagementInterface $orderManagement,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->_salesOrderPaymentTransactionFactory = $salesOrderPaymentTransactionFactory;
        $this->_orderRepository   = $orderRepository;
        $this->_paymentRepository = $paymentRepository;
        $this->_invoiceRepository = $invoiceRepository;
        $this->orderManagement = $orderManagement;
        $this->_logger  = $logger;
    }

    /**
     * Process the given $eventData
     *
     * @param mixed
     *
     * @throws \Exception
     */
    public function processWebhook($eventData)
    {
        $event_type = isset($eventData['event_type']) ? $eventData['event_type'] : '';

        if (in_array($event_type, $this->getAvailableEvents())) {
            $txnId = $eventData['resource']['id'];
            $relatedTxnId = isset($eventData['resource']['supplementary_data']['related_ids']['order_id']) ? $eventData['resource']['supplementary_data']['related_ids']['order_id'] : null;
            $this->_payment = $this->getPaymentByTxnId($eventData['resource']['id']) ? : $this->getPaymentByTxnId($relatedTxnId);
        } else {
            $this->_logger->warning(__('Event not supported: %1', $event_type));
            return;
        }

        if (((!$this->_payment) || (!$this->_payment->getOrder())) && ($event_type != self::PAYMENT_CAPTURE_REFUNDED)) {
            $this->_logger->debug(__('Order nor found by TXN ID'));
            return;
        }

        $this->_logger->debug("[WEBHOOK EVENT TYPE: {$event_type} TNX ID: {$eventData['resource']['id']}]");

        switch ($event_type) {

            case self::PAYMENT_CAPTURE_PENDING:

                $this->_paymentPending($eventData);
                break;

            case self::CHECKOUT_ORDER_APPROVED:
            case self::PAYMENT_CAPTURE_COMPLETED:

                $this->_paymentCompleted($eventData);
                break;

            case self::PAYMENT_CAPTURE_REFUNDED:

                $this->_paymentRefunded($eventData);
                break;

            case self::PAYMENT_CAPTURE_REVERSED:

                $this->_paymentReversed($eventData);
                break;

            case self::PAYMENT_CAPTURE_DENIED:

                $this->_paymentDenied($eventData);
                break;

            default:
                break;
        }
    }

    /**
     * Get supported webhook events
     *
     * @return array
     */
    public function getAvailableEvents()
    {
        return [
            self::PAYMENT_CAPTURE_COMPLETED,
            self::PAYMENT_CAPTURE_PENDING,
            self::PAYMENT_CAPTURE_REFUNDED,
            self::PAYMENT_CAPTURE_REVERSED,
            self::PAYMENT_CAPTURE_DENIED
        ];
    }

    protected function getPaymentByTxnId($txnId)
    {
        $payment = $this->_paymentRepository->load($txnId, 'last_trans_id');

        return $payment->getId() ? $payment : false;
    }

    /**
     * Set transaction as pending
     *
     * @param mixed
     *
     * @throws \Exception
     */
    protected function _paymentPending($eventData)
    {
        $this->_registerTransactionId($eventData);

        $this->_payment->setIsTransactionClosed(0)
            ->registerCaptureNotification($eventData['resource']['amount']['value'], true);

        // notify customer
        if (!$this->_payment->getOrder()->getEmailSent()) {
            $this->_payment->getOrder()->addStatusHistoryComment(
                    __('This order is on hold due to a pending payment. The order will be processed after the payment is approved at the payment gateway.')
                )->setIsCustomerNotified(true)->save();
        }
    }

    /**
     * Set transaction as completed
     *
     * @param mixed
     *
     * @throws \Exception
     */
    protected function _paymentCompleted($eventData)
    {
        $this->_logger->info('[PAYPAL-WEBHOOK] Start payment completed');
        try {
            $order = $this->_payment->getOrder();

            $paymentResource = isset($eventData['resource']['amount']) ? $eventData['resource']['amount'] : ($eventData['resource']['purchase_units'][0]['amount'] ?? null);

            if (!$paymentResource || !isset($paymentResource['value'])) {
                $this->_logger->critical('[PAYPAL-WEBHOOK] Amount not found in webhook payload', [
                    'eventData' => $eventData
                ]);
                return;
            }

            $capturedAmount = round((float)$paymentResource['value'], 2);

            $orderTotal = round((float)$order->getGrandTotal(), 2);

            if ($capturedAmount !== $orderTotal) {

                $message = sprintf(
                    'Unable to process order. Amount mismatch: Captured amount: $%s, Order total: $%s',
                    number_format($capturedAmount, 2),
                    number_format($orderTotal, 2)
                );

                $order->addCommentToStatusHistory(
                    $this->_appendTransactionId($message, $eventData['resource']['id'] ?? null)
                );
                $this->_orderRepository->save($order);

                $this->_logger->critical('[PAYPAL-WEBHOOK] Amount mismatch detected', [
                    'order_id'        => $order->getIncrementId(),
                    'paypal_amount'   => $capturedAmount,
                    'order_total'     => $orderTotal,
                    'event_id'        => $eventData['resource']['id'] ?? null
                ]);

                return;
            }

            $transactionId = $this->_registerTransactionId($eventData);

            $this->_payment->setIsTransactionClosed(0)
                ->registerCaptureNotification($capturedAmount, true);

            $order->setState(\Magento\Sales\Model\Order::STATE_PROCESSING)
                ->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);

            $order->addStatusHistoryComment(
                $this->_appendTransactionId(
                    __('Thank you for your payment. Registered notification about captured amount.'),
                    $transactionId
                )
            )->setIsCustomerNotified(true);

            $this->_orderRepository->save($order);

        } catch (\Exception $e) {
            $this->_logger->critical('[PAYPAL-WEBHOOK] Error processing completed payment', [
                'exception' => $e->getMessage(),
                'eventData' => $eventData
            ]);
        }
    }

    /**
     * Process a capture fetched on demand with the same handling as the PAYMENT.CAPTURE.* webhooks
     *
     * @param \Magento\Sales\Model\Order\Payment $payment
     * @param array $capture Capture resource as returned by the PayPal Orders API
     * @return string Capture status
     */
    public function processCapture(\Magento\Sales\Model\Order\Payment $payment, array $capture)
    {
        $status = strtoupper((string)($capture['status'] ?? ''));
        $this->_payment = $payment;

        $eventData = [
            'event_type'    => 'PAYMENT.CAPTURE.' . $status,
            'resource_type' => 'capture',
            'resource'      => $capture,
        ];

        switch ($status) {
            case 'COMPLETED':
                $this->_paymentCompleted($eventData);
                break;

            case 'PENDING':
                $this->_capturePending($eventData);
                break;

            case 'DECLINED':
            case 'FAILED':
                $this->_paymentDenied($eventData);
                break;

            default:
                $transactionId = $this->_registerTransactionId($eventData);
                $order = $this->_payment->getOrder();
                $order->addCommentToStatusHistory(
                    $this->_appendTransactionId(__('OXXO Pay transaction status: %1.', $status), $transactionId)
                );
                $this->_orderRepository->save($order);
        }

        return $status;
    }

    /**
     * Manual status check while the capture is still pending: keep the transaction id and log the check
     *
     * @param array $eventData
     * @return void
     */
    protected function _capturePending($eventData)
    {
        $transactionId = $this->_registerTransactionId($eventData);
        $order = $this->_payment->getOrder();

        $order->addCommentToStatusHistory(
            $this->_appendTransactionId(__('OXXO Pay transaction status checked: still pending.'), $transactionId)
        );
        $this->_orderRepository->save($order);
    }

    /**
     * Keep the PayPal capture id on the OXXO payment so it can be shown in the order detail
     *
     * @param array $eventData
     * @return string|null
     */
    protected function _registerTransactionId($eventData)
    {
        $eventType = $eventData['event_type'] ?? '';
        $transactionId = $eventData['resource']['id'] ?? null;

        if (!$transactionId
            || strpos($eventType, 'PAYMENT.CAPTURE.') !== 0
            || $this->_payment->getMethod() !== \PayPal\CommercePlatform\Model\Payment\Oxxo\Payment::CODE
        ) {
            return null;
        }

        $this->_payment->setAdditionalInformation(self::TRANSACTION_ID_KEY, $transactionId);

        // The order keeps its own payment instance, which is the one persisted on order save
        $orderPayment = $this->_payment->getOrder() ? $this->_payment->getOrder()->getPayment() : null;
        if ($orderPayment && $orderPayment !== $this->_payment) {
            $orderPayment->setAdditionalInformation(self::TRANSACTION_ID_KEY, $transactionId);
        }

        return $transactionId;
    }

    /**
     * Append the transaction id to a status history message
     *
     * @param string|\Magento\Framework\Phrase $message
     * @param string|null $transactionId
     * @return string
     */
    protected function _appendTransactionId($message, $transactionId)
    {
        if (!$transactionId) {
            return (string)$message;
        }

        return (string)$message . ' ' . __('Transaction ID: "%1"', $transactionId);
    }

    /**
     * Process a refund
     *
     * @return void
     */
    protected function _paymentRefunded($eventData)
    {
        $summary = isset($eventData['summary']) ? $eventData['summary'] : '';

        if (!$this->_payment) {
            $invoice_id = isset($eventData['resource']['invoice_id']) ? $eventData['resource']['invoice_id'] : null;

            if ($invoice_id) {
                $invoice = $this->_invoiceRepository->get($invoice_id);
                $order   = $this->_orderRepository->get($invoice->getOrderId());

                $this->_payment = $order->getPayment();
            } else {
                return;
            }
        }

        $this->_payment
            ->setPreparedMessage($summary)
            ->setIsTransactionClosed(0);

        $order
            ->addCommentToStatusHistory(
                __('A refund has been registered from PayPal | %1', $summary)
            )
            ->setIsCustomerNotified(true)
            ->save();
    }

    /**
     * Process payment reversal
     *
     * @return void
     */
    protected function _paymentReversed($eventData)
    {
        $summary = isset($eventData['summary']) ? $eventData['summary'] : '';

        $this->_payment
            ->setPreparedMessage($summary)
            ->setIsTransactionClosed(0);

        $this->_payment->getOrder()
            ->addCommentToStatusHistory(
                __('A reversal has been registered from PayPal | %1', $summary)
            )
            ->setIsCustomerNotified(true)
            ->save();
    }

    /**
     * Process payment reversal
     *
     * @return void
     */
    protected function _paymentDenied($eventData)
    {
        $summary = isset($eventData['summary']) ? $eventData['summary'] : 'Pago en OXXO';

        try {
            $this->_payment->setPreparedMessage($summary);
            $this->_payment->setNotificationResult(true);
            $this->_payment->setIsTransactionClosed(true);

            $this->orderManagement->cancel($this->_payment->getOrder()->getId());

            $this->_payment->getOrder()
                ->addCommentToStatusHistory(
                    __('Your order %1 has been canceled.', $summary)
                )->setIsCustomerNotified(true)
                ->save();

        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->_logger->debug(sprintf('[PAYPAL-Webhook] Error Canceled: %s', $e->getMessage()));
        }
    }
}
