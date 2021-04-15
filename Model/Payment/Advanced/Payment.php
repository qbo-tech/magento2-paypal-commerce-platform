<?php
namespace PayPal\CommercePlatform\Model\Payment\Advanced;

use Magento\Payment\Model\InfoInterface;

class Payment extends \Magento\Payment\Model\Method\AbstractMethod //\PayPal\CommercePlatform\Model\Payment\PayPalAbstract
{
    const CODE                         = 'paypalcp';

    const PAYMENT_REVIEW_STATE         = 'pending';
    const PENDING_PAYMENT_NOTIFICATION = 'This order is on hold due to a pending payment. The order will be processed after the payment is approved at the payment gateway.';
    const DECLINE_ERROR_MESSAGE        = 'Declining Pending Payment Transaction as configured in PPPlus module.';
    const GATEWAY_ERROR_MESSAGE        = 'Payement has been declined by Payment Gateway';
    const DENIED_ERROR_MESSAGE         = 'Gateway response error';
    const COMPLETED_SALE_CODE          = 'completed';
    const DENIED_SALE_CODE             = 'denied';
    const REFUNDED_SALE_CODE           = 'refunded';
    const FAILED_STATE_CODE            = 'failed';

    protected $_code = self::CODE;

    protected $_isGateway    = true;

    protected $_canCapture   = true;
    protected $_canAuthorize = true;

    /** @var \Magento\Sales\Model\Order */
    protected $_order        = false;

    protected $_response;

    protected $_successCodes = ['200', '201'];

    /** @var \Psr\Log\LoggerInterface */
    protected $_logger;

    /** @var \PayPalCheckoutSdk\Orders\OrdersCaptureRequest */
    protected $_paypalOrderCaptureRequest;

    /** @var \PayPalCheckoutSdk\Core\PayPalHttpClient */
    protected $_paypalClient;

    /** @var \PayPal\CommercePlatform\Model\Paypal\Api */
    protected $_paypalApi;


    /**
     * Constructor method
     *
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param Logger $logger
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     * @param \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param Api $api
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Payment\Model\Method\Logger $paymentLogger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \PayPal\CommercePlatform\Model\Paypal\Api $paypalApi
        )
        {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $paymentLogger
        );

        $this->_logger    = $context->getLogger();
        $this->_paypalApi = $paypalApi;
    }

    public function isAvailable(
        \Magento\Quote\Api\Data\CartInterface $quote = null
    ) {
        $isAvailable = parent::isAvailable($quote);

        $this->_logger->debug(__METHOD__ . ' | isAvailable ' . $isAvailable);

        return $isAvailable;
    }

    /**
     * Assign corresponding data
     *
     * @param \Magento\Framework\DataObject|mixed $data
     * @return $this
     * @throws LocalizedException
     */
    public function assignData(\Magento\Framework\DataObject $data)
    {
        $this->_logger->debug(__METHOD__);

        parent::assignData($data);

        $additionalData     = $data->getData('additional_data') ?: $data->getData();
        //payment_method

        $this->_logger->debug(__METHOD__ . ' | ', ['additionalData' => $additionalData]);

        $infoInstance = $this->getInfoInstance();

        $infoInstance->setAdditionalInformation('order_id', $additionalData['order_id'] ?? '');

        return $this;
    }
    /**
     * Payment capturing
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param float $amount
     * @return $this
     * @throws \Magento\Framework\Validator\Exception
     */
    public function capture(\Magento\Payment\Model\InfoInterface $payment, $amount)
    {
        $this->_logger->debug(__METHOD__);

        $paypalOrderId = $payment->getAdditionalInformation('order_id');

        $this->_logger->debug(__METHOD__ . ' | paypalOrderId ' . $paypalOrderId);

        $this->_order = $payment->getOrder();

        $this->_paypalOrderCaptureRequest = $this->_paypalApi->getOrdersCaptureRequest($paypalOrderId); //new \PayPalCheckoutSdk\Orders\OrdersCaptureRequest($paypalOrderId);

        try {
            //$this->_processTransaction($payment);
            $this->_logger->debug(__METHOD__ . ' | before _paypalClient->execute ');

            $this->_response = $this->_paypalApi->execute($this->_paypalOrderCaptureRequest);
            $this->_logger->debug(__METHOD__ . ' | response ' . print_r($this->_response->result->purchase_units, true));

            $this->_processTransaction($payment);

        } catch (\Exception $e) {
            $this->_logger->debug(__METHOD__ . ' | Exception : ' . $e->getMessage());

            $this->debugData(['request' => $data, 'exception' => $e->getMessage()]);
            $this->_logger->error(__('Payment capturing error.'));
            throw new \Magento\Framework\Exception\LocalizedException(__(self::GATEWAY_ERROR_MESSAGE));
        }

        //throw new \Exception;
        return $this;
    }

    public function authorize(InfoInterface $payment, $amount)
    {
        $this->_logger->debug(__METHOD__ . $amount);

        throw new \Exception;

    }

    /**
     * Process Payment Transaction based on response data
     *
     * @param  \Magento\Payment\Model\InfoInterface $payment
     * @return \Magento\Payment\Model\InfoInterface $payment
     */
    protected function _processTransaction(&$payment)
    {
        $this->_logger->debug(__METHOD__);

        if (!in_array($this->_response->statusCode, $this->_successCodes)) {
            throw new \Exception(__('Gateway error. Reason: %1', $this->_response->message));
        }
        $status = $this->_response->result->status;

        if ($status == self::FAILED_STATE_CODE) { //TOOD COMPLETED O PENDING
            //throw new \Exception(__(self::GATEWAY_ERROR_MESSAGE));
        }

        $tx_id = $this->_response->result->purchase_units[0]->payments->captures[0]->id;

        if($tx_id){
            $this->setComments($this->_order, __(self::PENDING_PAYMENT_NOTIFICATION), false); // validate this

            $payment->setTransactionId($tx_id)
                ->setIsTransactionPending(true)
                ->setIsTransactionClosed(false);
        } else {
            $payment->setIsTransactionPending(true);
        }

        return $payment;
    }
}
