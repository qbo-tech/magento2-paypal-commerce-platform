<?php

/**
 * @author Alvaro Florez <aflorezd@gmail.com>
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
    const PAYMENT_CAPTURE_DENIED = 'PAYMENT.CAPTURE.DENIED';

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

    public function __construct(
        \Magento\Sales\Model\Order\Payment\TransactionFactory $salesOrderPaymentTransactionFactory,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Sales\Model\Order\Payment $paymentRepository,
        \Magento\Sales\Api\InvoiceRepositoryInterface $invoiceRepository,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->_salesOrderPaymentTransactionFactory = $salesOrderPaymentTransactionFactory;
        $this->_orderRepository   = $orderRepository;
        $this->_paymentRepository = $paymentRepository;
        $this->_invoiceRepository = $invoiceRepository;
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
            $this->_payment = $this->getPaymentByTxnId($eventData['resource']['id']);
        } else {
            $this->_logger->warning(__METHOD__ . ' | ' . __('Event not supported: %1', $event_type));

            return;
        }

        $this->_logger->debug(__METHOD__ . " | event_type: $event_type");


        if (((!$this->_payment) || (!$this->_payment->getOrder())) && ($event_type != self::PAYMENT_CAPTURE_REFUNDED)) {
            $this->_logger->debug(__METHOD__ . ' | ' . __('Problem with payment/order'));

            return;
        }

        switch ($event_type) {

            case self::PAYMENT_CAPTURE_PENDING:

                $this->_paymentPending($eventData);
                break;

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

        return $payment;
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
        $this->_payment->setIsTransactionClosed(0)
            ->registerCaptureNotification($eventData['resource']['amount']['value'], true);

        $this->_payment->getOrder()->update(false)->save();

        // notify customer
        $invoice = $this->_payment->getCreatedInvoice();
        if ($invoice && !$this->_payment->getOrder()->getEmailSent()) {
            $this->_payment->getOrder()->queueNewOrderEmail()
                ->addStatusHistoryComment(
                    __(
                        'Notified customer about invoice #%1.',
                        $invoice->getIncrementId()
                    )
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
        $paymentResource = $eventData['resource'];

        $this->_payment->setIsTransactionClosed(0)
            ->registerCaptureNotification($paymentResource['amount']['value'], true);

        $this->_payment->getOrder()->setState(\Magento\Sales\Model\Order::STATE_PROCESSING)
            ->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING)
            ->save();

        // notify customer
        $invoice = $this->_payment->getCreatedInvoice();
        if ($invoice && !$this->_payment->getOrder()->getEmailSent()) {
            $this->_payment->getOrder()->queueNewOrderEmail()
                ->addStatusHistoryComment(
                    __(
                        'Notified customer about invoice #%1.',
                        $invoice->getIncrementId()
                    )
                )->setIsCustomerNotified(true)->save();
        }
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
                __('A refund has been made from PayPal | %1', $summary)
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
                __('Se ha hecho un reembolso desde PayPal | %1', $summary)
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
        $summary = isset($eventData['summary']) ? $eventData['summary'] : '';

        try {
            $this->_payment->setPreparedMessage($summary);
            $this->_payment->setNotificationResult(true);
            $this->_payment->setIsTransactionClosed(true);
            $this->_payment->deny(false);
            $this->_payment->getOrder()->save();
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            if ($e->getMessage() != __('We cannot cancel this order.')) {
                throw $e;
            }
        }
    }
}
