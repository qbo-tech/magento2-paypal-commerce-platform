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
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_salesOrderFactory;

    /**
     * @var \Magento\Sales\Model\Order\Payment
     */
    protected $_paymentRepository;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $_logger;

    public function __construct(
        \Magento\Sales\Model\Order\Payment\TransactionFactory $salesOrderPaymentTransactionFactory,
        \Magento\Sales\Model\OrderFactory $salesOrderFactory,
        \Magento\Sales\Model\Order\Payment $paymentRepository,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->_salesOrderPaymentTransactionFactory = $salesOrderPaymentTransactionFactory;
        $this->_salesOrderFactory = $salesOrderFactory;
        $this->_paymentRepository = $paymentRepository;
        $this->_logger = $logger;
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

        if (in_array($eventData['event_type'], $this->getAvailableEvents())) {
            $this->_payment = $this->getPaymentByTxnId($eventData['resource']['id']);
        } else {
            $this->_logger->debug(__METHOD__ . ' | ' . __('Event not supported: ') . $eventData['event_type']);

            return;
        }

        if ((!$this->_payment) || (!$this->_payment->getOrder())) {
            $this->_logger->debug(__METHOD__ . ' | ' . __('Problem with payment/order'));

            return;
        }

        switch ($eventData['event_type']) {

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
        $this->_payment
            ->setPreparedMessage($eventData['summary'])
            ->setIsTransactionClosed(0);

        $this->_payment->getOrder()->addStatusHistoryComment(
            __('A refund has been made from PayPal | %1', $eventData['summary'])
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
        $this->_payment
            ->setPreparedMessage($eventDatav)
            ->setIsTransactionClosed(0);

        $this->_payment->getOrder()->addStatusHistoryComment(
            __('Se ha hecho un reembolso desde PayPal | %1', $eventData['summary'])
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
        try {
            $this->_payment->setPreparedMessage($eventData['summary']);
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
