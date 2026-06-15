<?php

namespace PayPal\CommercePlatform\Model\Payment\Advanced;

use Magento\Checkout\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\InfoInterface;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use PayPal\CommercePlatform\Model\Config;

class Payment extends \Magento\Payment\Model\Method\AbstractMethod
{
    const CODE                         = 'paypalcp';

    const PAYMENT_REVIEW_STATE         = 'PENDING';
    const PENDING_PAYMENT_NOTIFICATION = 'This order is on hold due to a pending payment. The order will be processed after the payment is approved at the payment gateway.';
    const DECLINE_ERROR_MESSAGE        = 'Declining Pending Payment Transaction';
    const GATEWAY_ERROR_MESSAGE        = 'Payment has been declined by Payment Gateway';
    const BA_ERROR_MESSAGE             = 'It is not possible to use this payment agreement, please try another';
    const GATEWAY_NOT_TXN_ID_PRESENT   = 'The transaction id is not present';
    const DENIED_ERROR_MESSAGE         = 'Gateway response error';
    const COMPLETED_SALE_CODE          = 'COMPLETED';
    const DENIED_SALE_CODE             = 'DENIED';
    const REFUNDED_SALE_CODE           = 'REFUNDED';
    const FAILED_STATE_CODE            = 'FAILED';
    const SUCCESS_STATE_CODES          = array("PENDING", "COMPLETED");

    const PAYPAL_CLIENT_METADATA_ID_HEADER = 'PayPal-Client-Metadata-Id';
    const FRAUDNET_CMI_PARAM = 'fraudNetCMI';
    const THREE_D_SECURE_CARD_FIELDS_PARAM = 'three_d_secure_card_fields';
    const THREE_D_SECURE_LIABILITY_SHIFT_PARAM = 'three_d_secure_liability_shift';
    const THREE_D_SECURE_PARAM = 'three_d_secure';
    const THREE_D_SECURE_YES = 'Yes';
    const THREE_D_SECURE_SUCCESS_STATUS = 'POSSIBLE';
    const THREE_D_SECURE_FAILED_STATUS = 'NO';
    const THREE_D_SECURE_UNKNOWN_STATUS = 'UNKNOWN';

    protected $_code = self::CODE;

    protected $_infoBlockType = 'PayPal\CommercePlatform\Block\Info';

    protected $_isGateway    = true;

    protected $_canRefund    = true;
    protected $_canRefundInvoicePartial    = true;
    protected $_canCapture   = true;
    protected $_canAuthorize = true;

    /** @var \Magento\Sales\Model\Order */
    protected $_order        = false;

    protected $_response;

    protected $_successCodes = ['200', '201'];

    protected $_canHandlePendingStatus = true;

    /** @var \PayPal\CommercePlatform\Logger\Handler */
    protected $_logger;

    /** @var \PayPalCheckoutSdk\Orders\OrdersCaptureRequest */
    protected $_paypalOrderCaptureRequest;

    /** @var \PayPalCheckoutSdk\Core\PayPalHttpClient */
    protected $_paypalClient;

    /** @var \PayPal\CommercePlatform\Model\Paypal\Api */
    protected $_paypalApi;

    /** @var \Magento\Framework\Event\ManagerInterface */
    protected $_eventManager;

    /** @var \Magento\Framework\App\Config\ScopeConfigInterface */
    protected $_scopeConfig;

    private $paymentSource;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * @var \Magento\Framework\Mail\Template\TransportBuilder
     */
    protected $transportBuilder;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;
    /**
     * @var \PayPal\CommercePlatform\Model\Config
     */
    protected $paypalConfig;
    /**
     * @var \PayPal\CommercePlatform\Model\Billing\Agreement
     */
    protected $billingAgreement;

    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory
     * @param \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory
     * @param \Magento\Payment\Helper\Data $paymentData
     * @param \Magento\Payment\Model\Method\Logger $paymentLogger
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \PayPal\CommercePlatform\Model\Paypal\Api $paypalApi
     * @param \PayPal\CommercePlatform\Logger\Handler $logger
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param \Magento\Framework\Event\ManagerInterface $eventManager
     * @param \PayPal\CommercePlatform\Model\Config $paypalConfig
     * @param \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Payment\Helper\Data $paymentData,
        \Magento\Payment\Model\Method\Logger $paymentLogger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \PayPal\CommercePlatform\Model\Paypal\Api $paypalApi,
        \PayPal\CommercePlatform\Logger\Handler $logger,
        \Magento\Framework\Event\ManagerInterface $eventManager,
        Config $paypalConfig,
        TransportBuilder $transportBuilder,
        StoreManagerInterface $storeManager,
        Session $checkoutSession,
        OrderRepositoryInterface $orderRepository,
        \Magento\Customer\Model\Session $customerSession,
        \PayPal\CommercePlatform\Model\Billing\Agreement $billingAgreement,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $paymentLogger,
            $resource,
            $resourceCollection,
            $data
        );

        $this->_logger       = $logger;
        $this->_paypalApi    = $paypalApi;
        $this->_scopeConfig  = $scopeConfig;
        $this->_eventManager = $eventManager;
        $this->checkoutSession = $checkoutSession;
        $this->paypalConfig = $paypalConfig;
        $this->transportBuilder = $transportBuilder;
        $this->storeManager = $storeManager;
        $this->paymentSource = null;
        $this->orderRepository = $orderRepository;
        $this->billingAgreement = $billingAgreement;
    }

    public function refund(InfoInterface $payment, $amount)
    {
        /** @var \Magento\Sales\Model\Order\Payment $payment */
        $paypalOrderId = $payment->getAdditionalInformation('payment_id');
        $creditMemoIndex = (int)$payment->getAdditionalInformation('credit_memo_count') + 1;
        $order = $payment->getOrder();
        $invoice = $order->getInvoiceCollection()->getFirstItem();

        $paypalRefundRequest = new \PayPalCheckoutSdk\Payments\CapturesRefundRequest($paypalOrderId);

        $creditmemo = $payment->getCreditmemo();
        $invoiceId = $creditmemo->getInvoiceId() || empty($invoice) ? $creditmemo->getInvoiceId() : $invoice->getId();

        $memoCurrencyCode = $creditmemo->getBaseCurrencyCode();

        $paypalRefundRequest->body = [
            'amount' => [
                'value'         => $amount,
                'currency_code' => $memoCurrencyCode
            ],
            'invoice_id'    => $invoiceId . '-' . $creditMemoIndex,
            'note_to_payer' => $creditmemo->getCustomerNote()
        ];

        $this->_paypalApi->execute($paypalRefundRequest);
        $payment->setAdditionalInformation('credit_memo_count', $creditMemoIndex);
        return $this;
    }

    public function isAvailable(
        \Magento\Quote\Api\Data\CartInterface $quote = null
    ) {
        $isAvailable = parent::isAvailable($quote);

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
        parent::assignData($data);

        $infoInstance   = $this->getInfoInstance();
        $infoInstance->setAdditionalInformation('payment_source');
        $additionalData = $data->getData('additional_data') ?: $data->getData();
        foreach ($additionalData as $key => $value) {
            #In some cases, additonal data may include extension_attribites which is an object. Skip setting objects to additional data as it will throw an exception in @Magento\Payment\Model\Info
            if(!is_object($value)) {
                $infoInstance->setAdditionalInformation($key, $value);
            }
        }

        // Set any additional info here if required

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
        $paypalOrderId = $payment->getAdditionalInformation('order_id');

        /** @var \Magento\Sales\Model\Order */
        $this->_order = $payment->getOrder();

        try {
            $this->validatePayPalOrderAmountBeforeCapture($payment, $paypalOrderId);
            $this->validateThreeDSBeforeCapture($payment, $paypalOrderId);
            $this->_paypalOrderCaptureRequest = $this->_paypalApi->getOrdersCaptureRequest($paypalOrderId);

            //TODO move function.
            if ($payment->getAdditionalInformation('payment_source')) {
                $this->paymentSource = json_decode($payment->getAdditionalInformation('payment_source'), true);

                if(!isset($this->paymentSource['card'])) {
                    $this->_paypalOrderCaptureRequest->body = ['payment_source' => $this->paymentSource];
                }

            }

            $this->_logger->debug(__METHOD__ . ' | PaymentBodyRequest : ', $this->_paypalOrderCaptureRequest->body ?? []);

            $paypalCMID = $payment->getAdditionalInformation(self::FRAUDNET_CMI_PARAM);
            if ($paypalCMID) {
                $this->_paypalOrderCaptureRequest->headers[self::PAYPAL_CLIENT_METADATA_ID_HEADER] = $paypalCMID;
            }

            $this->_logger->error('Request Payment Advanced : ' . print_r($this->_paypalOrderCaptureRequest, true));

            $this->_eventManager->dispatch('paypalcp_order_capture_before', ['payment' => $payment, 'paypalCMID' => $paypalCMID]);
            $this->_response = $this->_paypalApi->execute($this->_paypalOrderCaptureRequest);

            $this->_processTransaction($payment);
            $this->_eventManager->dispatch('paypalcp_order_capture_after', ['payment' => $payment]);

        } catch (\Exception $e) {

            if ($e instanceof LocalizedException) {
                throw $e;
            }

            $this->_logger->error(sprintf('[PAYPAL COMMERCE CAPTURING ERROR] - %s', $e->getMessage()));

            $this->_logger->error(__METHOD__ . ' | Exception : ' . $e->getMessage());
            $this->_logger->error(__METHOD__ . ' | Exception response : ' . print_r($this->_response, true));

            $errorMessage = self::GATEWAY_ERROR_MESSAGE;

            $this->_processStoredPaymentTokenErrors($payment, $errorMessage);

            throw new \Magento\Framework\Exception\LocalizedException(__($errorMessage));
        }
        return $this;
    }

    /**
     * Validate PayPal order amount (GET order) against Magento order total before capture.
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param string $paypalOrderId
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function validatePayPalOrderAmountBeforeCapture(InfoInterface $payment, $paypalOrderId)
    {
        $paypalOrderAmount = $this->getPayPalOrderAmount($paypalOrderId);
        if ($paypalOrderAmount === null) {
            $this->_logger->debug('[PAYPAL COMMERCE CAPTURE] PayPal order amount not found before capture', [
                'paypal_order_id' => $paypalOrderId
            ]);
            return;
        }

        /** @var \Magento\Sales\Model\Order $order */
        $order = $payment->getOrder();
        $orderTotal = round((float)$order->getGrandTotal(), 2);

        $paypalAmountInCents = (int)round($paypalOrderAmount * 100);
        $orderTotalInCents = (int)round($orderTotal * 100);

        if ($paypalAmountInCents === $orderTotalInCents) {
            return;
        }

        $message = sprintf(
            'Unable to process order. Amount mismatch: PayPal order amount: $%s, Order total: $%s',
            number_format($paypalOrderAmount, 2),
            number_format($orderTotal, 2)
        );

        $this->_logger->debug('[PAYPAL COMMERCE CAPTURE] Amount mismatch detected before capture', [
            'order_id' => $order->getIncrementId(),
            'paypal_amount' => $paypalOrderAmount,
            'order_total' => $orderTotal,
            'paypal_order_id' => $paypalOrderId
        ]);

        if ((bool)$this->getConfigValue('stop_on_amount_mismatch')) {
            throw new LocalizedException(__($message));
        }
    }

    /**
     * Retrieve PayPal order amount from GET order endpoint.
     *
     * @param string $paypalOrderId
     * @return float|null
     */
    private function getPayPalOrderAmount($paypalOrderId)
    {
        $orderGetRequest = $this->_paypalApi->getOrdersGetRequest($paypalOrderId);
        $orderResponse = $this->_paypalApi->execute($orderGetRequest);

        if (empty($orderResponse) || !isset($orderResponse->result)) {
            return null;
        }

        if (!isset($orderResponse->result->purchase_units[0]->amount->value)) {
            return null;
        }

        return round((float)$orderResponse->result->purchase_units[0]->amount->value, 2);
    }

    /**
     * Validate 3DS liability shift before capture for CardFields flows.
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @param string $paypalOrderId
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function validateThreeDSBeforeCapture(InfoInterface $payment, $paypalOrderId)
    {
        if (!$this->shouldValidateThreeDS($payment)) {
            $this->_logger->debug('[PAYPAL COMMERCE 3DS] Validation skipped', [
                'order_id' => $payment->getOrder()->getIncrementId(),
                'paypal_order_id' => $paypalOrderId,
                'mode' => $this->paypalConfig->getAcdcThreeDSMode(),
                'is_card_fields' => (bool)$payment->getAdditionalInformation(self::THREE_D_SECURE_CARD_FIELDS_PARAM),
                'minimum_amount' => $this->paypalConfig->getAcdcThreeDSMinimumAmount(),
                'order_total' => round((float)$payment->getOrder()->getGrandTotal(), 2),
            ]);
            return;
        }

        $this->_logger->debug('[PAYPAL COMMERCE 3DS] Validation started', [
            'order_id' => $payment->getOrder()->getIncrementId(),
            'paypal_order_id' => $paypalOrderId,
            'mode' => $this->paypalConfig->getAcdcThreeDSMode(),
            'client_liability_shift' => $payment->getAdditionalInformation(self::THREE_D_SECURE_LIABILITY_SHIFT_PARAM),
        ]);

        $threeDSResult = $this->getPayPalOrderThreeDSResult($paypalOrderId);
        $isValid = $this->isThreeDSCaptureAllowed($threeDSResult);

        $this->_logger->debug('[PAYPAL COMMERCE 3DS] Validation decision', [
            'order_id' => $payment->getOrder()->getIncrementId(),
            'paypal_order_id' => $paypalOrderId,
            'mode' => $this->paypalConfig->getAcdcThreeDSMode(),
            'is_valid' => $isValid,
            'three_ds_result' => $threeDSResult,
        ]);

        if (!$isValid) {
            $this->logThreeDSFailure($payment, $paypalOrderId, $threeDSResult);
            throw new LocalizedException(__(self::GATEWAY_ERROR_MESSAGE));
        }

        if (($threeDSResult['liability_shift'] ?? null) === self::THREE_D_SECURE_SUCCESS_STATUS) {
            $payment->setAdditionalInformation(self::THREE_D_SECURE_PARAM, self::THREE_D_SECURE_YES);
        }
    }

    /**
     * Determine whether the current payment requires server-side 3DS validation.
     *
     * @param \Magento\Payment\Model\InfoInterface $payment
     * @return bool
     */
    private function shouldValidateThreeDS(InfoInterface $payment)
    {
        if (!$payment->getAdditionalInformation(self::THREE_D_SECURE_CARD_FIELDS_PARAM)) {
            return false;
        }

        if ($this->paypalConfig->isAcdcThreeDSMerchantInitiated()) {
            $minimumAmount = $this->paypalConfig->getAcdcThreeDSMinimumAmount();
            $orderTotal = round((float)$payment->getOrder()->getGrandTotal(), 2);

            return $orderTotal >= $minimumAmount;
        }

        return $this->paypalConfig->isAcdcThreeDSRiskInitiated();
    }

    private function isThreeDSCaptureAllowed(array $threeDSResult)
    {
        $liabilityShift = $threeDSResult['liability_shift'] ?? null;
        /**
         * PayPal 3DS / liability shift reference kept here for future changes.
         *
         * Current business rule:
         * - Allow capture when liability_shift is POSSIBLE
         * - Allow capture when liability_shift is null
         * - Allow capture when liability_shift is missing
         * - Reject any other value
         *
         * Historical reference from PayPal documentation / prior analysis:
         * - POSSIBLE: authentication succeeded and liability shift is possible
         * - NO: liability shift did not occur
         * - UNKNOWN: result is unavailable / could not be determined
         *
         * If future requirements need finer handling by enrollment_status or
         * authentication_status, this is the method to extend again.
         */
        if ($liabilityShift === null) {
            return true;
        }

        return $liabilityShift === self::THREE_D_SECURE_SUCCESS_STATUS;
    }

    private function logThreeDSFailure(InfoInterface $payment, $paypalOrderId, array $threeDSResult)
    {
        $this->_logger->error('[PAYPAL COMMERCE 3DS] Liability shift validation failed', [
            'order_id' => $payment->getOrder()->getIncrementId(),
            'paypal_order_id' => $paypalOrderId,
            'mode' => $this->paypalConfig->getAcdcThreeDSMode(),
            'liability_shift' => $threeDSResult['liability_shift'] ?? null,
            'enrollment_status' => $threeDSResult['enrollment_status'] ?? null,
            'authentication_status' => $threeDSResult['authentication_status'] ?? null,
            'client_liability_shift' => $payment->getAdditionalInformation(self::THREE_D_SECURE_LIABILITY_SHIFT_PARAM),
        ]);
    }

    /**
     * Retrieve the 3DS result from PayPal order details.
     *
     * @param string $paypalOrderId
     * @return array<string, string|null>
     */
    private function getPayPalOrderThreeDSResult($paypalOrderId)
    {
        $orderGetRequest = $this->_paypalApi->getOrdersGetRequest($paypalOrderId);
        $orderGetRequest->path = rtrim($orderGetRequest->path, '?') . '?fields=payment_source';
        $orderResponse = $this->_paypalApi->execute($orderGetRequest);

        if (empty($orderResponse) || !isset($orderResponse->result->payment_source->card)) {
            $this->_logger->debug('[PAYPAL COMMERCE 3DS] GET order missing card/authentication payload', [
                'paypal_order_id' => $paypalOrderId,
                'status_code' => $orderResponse->statusCode ?? null,
                'has_result' => !empty($orderResponse) && isset($orderResponse->result),
            ]);
            return [
                'liability_shift' => null,
                'enrollment_status' => null,
                'authentication_status' => null,
            ];
        }

        $authenticationResult = $orderResponse->result->payment_source->card->authentication_result ?? null;

        $result = [
            'liability_shift' => isset($authenticationResult->liability_shift)
                ? (string)$authenticationResult->liability_shift
                : null,
            'enrollment_status' => isset($authenticationResult->three_d_secure->enrollment_status)
                ? (string)$authenticationResult->three_d_secure->enrollment_status
                : null,
            'authentication_status' => isset($authenticationResult->three_d_secure->authentication_status)
                ? (string)$authenticationResult->three_d_secure->authentication_status
                : null,
        ];

        $this->_logger->debug('[PAYPAL COMMERCE 3DS] GET order authentication result', [
            'paypal_order_id' => $paypalOrderId,
            'three_ds_result' => $result,
            'raw_authentication_result' => $authenticationResult ? json_decode(json_encode($authenticationResult), true) : null,
        ]);

        return $result;
    }

    /**
     * @param $payment
     * @return bool
     */
    private function isBillingAgreements($payment) {
        $paymentSource = $payment->getAdditionalInformation('payment_source') != null ?
            json_decode($payment->getAdditionalInformation('payment_source'))
            : null;
        return isset($paymentSource->token->type) && $paymentSource->token->type == 'BILLING_AGREEMENT';
    }

    /**
     * Handle Billing Agreement's Errors
     *
     * @return string
     */
    private function _processStoredPaymentTokenErrors($payment, &$errorMessage)
    {
        $paymentSource = $payment->getAdditionalInformation('payment_source') != null ?
            json_decode($payment->getAdditionalInformation('payment_source'))
            : null;

        if (!$paymentSource || !isset($paymentSource->token->type)) {
            return $errorMessage;
        }

        if ($paymentSource->token->type == 'BILLING_AGREEMENT') {
            $agreementId = $paymentSource->token->id ?? null;
            $this->_cancelBillingAgreementOnPayPal($agreementId);
            $this->removeBillingAgreement($agreementId);
            $errorMessage = self::BA_ERROR_MESSAGE;
        }

        if ($paymentSource->token->type == 'PAYMENT_METHOD_TOKEN') {
            $this->_deleteVaultTokenOnPayPal($paymentSource->token->id ?? null);
            $errorMessage = self::GATEWAY_ERROR_MESSAGE;
        }

        return $errorMessage;
    }

    /**
     * Cancel Billing Agreement on PayPal side via API.
     *
     * @param string|null $agreementId
     * @return void
     */
    private function _cancelBillingAgreementOnPayPal($agreementId = null)
    {
        if (!$agreementId) {
            $encryptedReference = $this->checkoutSession->getData('current_ba_reference');
            if (!$encryptedReference) {
                $this->_logger->error('PayPal Commerce: Billing Agreement reference not found in session for cancellation');
                return;
            }

            try {
                $agreementId = $this->billingAgreement->decryptReference($encryptedReference);
            } catch (\Exception $e) {
                $this->_logger->error('PayPal Commerce: Error decrypting Billing Agreement reference: ' . $e->getMessage());
                return;
            }
        }

        try {
            $cancelRequest = new \PayPal\CommercePlatform\Model\Paypal\Agreement\Cancel($agreementId);
            $this->_paypalApi->execute($cancelRequest);
            $this->_logger->info('PayPal Commerce: Billing Agreement cancelled on PayPal: ' . $agreementId);
        } catch (\Exception $e) {
            $this->_logger->error('PayPal Commerce: Error cancelling Billing Agreement on PayPal: ' . $e->getMessage());
        }
    }

    /**
     * Delete ACDC vault token on PayPal side.
     *
     * @param string|null $tokenId
     * @return void
     */
    private function _deleteVaultTokenOnPayPal($tokenId)
    {
        if (!$tokenId) {
            $this->_logger->error('PayPal Commerce: Vault token ID not found in payment source for deletion');
            return;
        }

        try {
            $deleteRequest = new \PayPal\CommercePlatform\Model\Paypal\Vault\DeletePaymentTokensRequest($tokenId);
            $this->_paypalApi->execute($deleteRequest);
            $this->_logger->info('PayPal Commerce: Vault token deleted on PayPal: ' . $tokenId);
        } catch (\Exception $e) {
            $this->_logger->error('PayPal Commerce: Error deleting vault token on PayPal: ' . $e->getMessage());
        }
    }

    /**
     * Remove Billing Agreement from DB
     *
     * @param string|null $agreementId
     * @return void
     */
    private function removeBillingAgreement($agreementId = null)
    {
        $billingAgreementModel = null;

        if ($agreementId) {
            $customerId = $this->_order ? $this->_order->getCustomerId() : null;
            if ($customerId) {
                $agreements = $this->billingAgreement->getAvailableCustomerBillingAgreements($customerId);
                foreach ($agreements as $agreement) {
                    try {
                        $decryptedRef = $this->billingAgreement->decryptReference($agreement->getReferenceId());
                        if ($decryptedRef === $agreementId) {
                            $billingAgreementModel = $agreement;
                            break;
                        }
                    } catch (\Exception $e) {
                        // decrypt failed, continue
                    }
                }
            }
        }

        if (!$billingAgreementModel) {
            $currentBAId = $this->checkoutSession->getData('current_ba_id');
            if ($currentBAId) {
                $billingAgreementModel = $this->billingAgreement->load($currentBAId);
            }
        }

        if ($billingAgreementModel && $billingAgreementModel->getId()) {
            try {
                $billingAgreementModel->delete();
                $this->checkoutSession->unsetData('current_ba_id');
                $this->checkoutSession->unsetData('current_ba_reference');
                $this->_logger->info('PayPal Commerce: Billing Agreement removed locally: ' . $billingAgreementModel->getId());
            } catch (\Exception $e) {
                $this->_logger->error('PayPal Commerce: Error deleting local Billing Agreement: ' . $e->getMessage());
            }
        } else {
            $this->_logger->error("PayPal Commerce: Billing Agreement not found for local removal");
        }
    }

    /**
     * Process Payment Transaction based on response data
     *
     * @param  \Magento\Payment\Model\InfoInterface $payment
     * @return \Magento\Payment\Model\InfoInterface $payment
     */
    protected function _processTransaction(&$payment)
    {
        if (!in_array($this->_response->statusCode, $this->_successCodes)) {
            throw new \Exception(__('Gateway error. Reason: %1', $this->_response->message));
        }

        $state = isset($this->_response->result->purchase_units[0]->payments->captures[0]->status) ? $this->_response->result->purchase_units[0]->payments->captures[0]->status : false;

        if (!$state || is_null($state) || !in_array($state, self::SUCCESS_STATE_CODES)) {
            throw new \Exception(__(self::GATEWAY_ERROR_MESSAGE));
        }

        $_txnId = isset($this->_response->result->purchase_units[0]->payments->captures[0]->id) ? $this->_response->result->purchase_units[0]->payments->captures[0]->id : null;

        if (!$_txnId) {
            throw new \Exception(__(self::GATEWAY_NOT_TXN_ID_PRESENT));
        }

        $infoInstance = $this->getInfoInstance();
        $infoInstance->setAdditionalInformation('payment_id', $_txnId);

        $this->_canHandlePendingStatus = (bool)$this->getConfigValue('handle_pending_payments');

        switch ($state) {
            case self::PAYMENT_REVIEW_STATE:
                if (!$this->_canHandlePendingStatus) {
                    throw new \Exception(__(self::DECLINE_ERROR_MESSAGE));
                }
                $this->setComments($this->_order, __(self::PENDING_PAYMENT_NOTIFICATION), false);
                $payment->setTransactionId($_txnId)
                    ->setIsTransactionPending(true)
                    ->setIsTransactionClosed(false);
                break;
            case self::COMPLETED_SALE_CODE:
                $payment->setTransactionId($_txnId)
                    ->setIsTransactionClosed(true);
                break;
            default:
                $payment->setIsTransactionPending(true);
                break;
        }

        if (property_exists($this->_response->result, 'payment_source')) {
            $paymentSource = $this->_response->result->payment_source;
            $storeId = $this->getStoreId();
            $paypalButtonTittle =  $this->_scopeConfig->getValue('payment/paypalcp/title_paypal', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
            $paypalCardTitle = $this->_scopeConfig->getValue('payment/paypalcp/title_card', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);

            if ($paymentSource) {
                if (property_exists($paymentSource, 'card')) {
                    $infoInstance->setAdditionalInformation('method_title', $paypalCardTitle);
                    if (property_exists($paymentSource->card, 'last_digits'))
                        $infoInstance->setAdditionalInformation('card_last_digits', $paymentSource->card->last_digits);
                    if (property_exists($paymentSource->card, 'brand'))
                        $infoInstance->setAdditionalInformation('card_brand', $paymentSource->card->brand);
                    if (property_exists($paymentSource->card, 'type'))
                        $infoInstance->setAdditionalInformation('card_type', $paymentSource->card->type);
                } else {
                    $infoInstance->setAdditionalInformation('method_title', $paypalButtonTittle);
                    if (property_exists($paymentSource->paypal, 'email_address')) {
                        $infoInstance->setAdditionalInformation('Paypal Email Address', $paymentSource->paypal->email_address);
                    }
                    if (property_exists($paymentSource->paypal, 'account_id')) {
                        $infoInstance->setAdditionalInformation('Paypal Account Id', $paymentSource->paypal->account_id);
                    }
                }
            }
        }

        if (property_exists($this->_response->result, 'credit_financing_offer')) {
            $creditFinance = $this->_response->result->credit_financing_offer;

            if ($creditFinance) {

                if (property_exists($creditFinance, 'consumer_fee_amount')) {
                    $infoInstance->setAdditionalInformation('installments_type', 'MCI');
                } else {
                    $infoInstance->setAdditionalInformation('installments_type', 'MSI');
                }

                if (property_exists($creditFinance, 'term')) {
                    $infoInstance->setAdditionalInformation('term', $creditFinance->term);
                }

                if (property_exists($creditFinance, 'consumer_fee_amount')) {
                    $infoInstance->setAdditionalInformation('consumer_fee_amount', $creditFinance->consumer_fee_amount->value);
                }
            }
        }

        return $payment;
    }

    /**
     * Set order comments
     *
     * @param type $order
     * @param type $comment
     * @param type $isCustomerNotified
     * @return type
     */
    public function setComments(&$order, $comment, $isCustomerNotified)
    {
        $history = $order->addStatusHistoryComment($comment, false);
        $history->setIsCustomerNotified($isCustomerNotified);

        return $order;
    }

    /**
     * Get payment store config
     *
     * @return string
     */
    public function getConfigValue($field)
    {
        $value =  $this->_scopeConfig->getValue(
            $this->_preparePathConfig($field),
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        return $value;
    }

    protected function _preparePathConfig($field)
    {
        return sprintf('payment/%s/%s', self::CODE, $field);
    }

    /**
     * Retrieve information from payment configuration
     *
     * @param string $field
     * @param int|string|null|\Magento\Store\Model\Store $storeId
     *
     * @return mixed
     */
    public function getConfigData($field, $storeId = null)
    {
        if ('order_place_redirect_url' === $field) {
            return $this->getOrderPlaceRedirectUrl();
        }
        if (null === $storeId) {
            $storeId = $this->getStore();
        }

        if ('sort_order' === $field) {
            $path = 'payment/paypalcp/' . $field;
        } else {
            $path = 'payment/' . $this->_code . '/' . $field;
        }
        return $this->_scopeConfig->getValue($path, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId);
    }
}
