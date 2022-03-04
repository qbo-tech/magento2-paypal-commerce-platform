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
    const PAYMENT_CAPTURE_DENIED  = 'PAYMENT.CAPTURE.DENIED';


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
            case self::CHECKOUT_ORDER_APPROVED:

                $this->_paymentCompleted($eventData);
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
        $paymentResource = isset($eventData['resource']['amount']) ?  $eventData['resource']['amount'] : $eventData['resource']['purchase_units'][0]['amount'];

        $this->_payment->setIsTransactionClosed(0)
            ->registerCaptureNotification($paymentResource['value'], true);

        $this->_payment->getOrder()->setState(\Magento\Sales\Model\Order::STATE_PROCESSING)
            ->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING)
            ->save();

        // notify customer
        $this->_payment->getOrder()->addStatusHistoryComment(
                __(
                    'Thank you for your payment. Registered notification about captured amount.'
                )
        )->setIsCustomerNotified(true)->save();

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
            //$this->_payment->deny(false);

            $this->_payment->getOrder()            
                ->addCommentToStatusHistory(
                    __('Your order #%1 has been canceled.', $summary)
                )->setIsCustomerNotified(true)
                ->save();

            $this->orderManagement->cancel($this->_payment->getOrder()->getId());

        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->_logger->debug($e->getMessage());
        }
    }
}
