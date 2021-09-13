<?php

/**
 * Copyright ©  All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace PayPal\CommercePlatform\Observer\Paypalcp;

class RiskTransactionObserver implements \Magento\Framework\Event\ObserverInterface
{
    /** @var \PayPal\CommercePlatform\Model\Paypal\Api */
    protected $_paypalApi;

    /** @var \PayPal\CommercePlatform\Model\Config  */
    protected $_paypalConfig;

    /** @var \Magento\Framework\Stdlib\DateTime */
    protected $_dateTime;

    /** @var \PayPal\CommercePlatform\Logger\Handler */
    protected $_loggerHandler;

    const EVENT_CREATE_ORDER_BEFORE  = 'paypalcp_create_order_before';
    const EVENT_CAPTURE_ORDER_BEFORE = 'paypalcp_capture_order_before';

    protected $_successCodes = ['200', '201'];

    public function __construct(
        \PayPal\CommercePlatform\Model\Paypal\Api $paypalApi,
        \PayPal\CommercePlatform\Model\Config  $paypalConfig,
        \Magento\Framework\Stdlib\DateTime $dateTime,
        \PayPal\CommercePlatform\Logger\Handler $loggerHandler
    ) {
        $this->_paypalApi     = $paypalApi;
        $this->_paypalConfig  = $paypalConfig;
        $this->_dateTime      = $dateTime;
        $this->_loggerHandler = $loggerHandler;
    }

    /**
     * Execute observer
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return void
     */
    public function execute(
        \Magento\Framework\Event\Observer $observer
    ) {
        $this->_loggerHandler->debug(__METHOD__ . " | eventName: " . $observer->getEvent()->getName());

        if (!$this->_paypalConfig->isEnableStc()) {
            return;
        }

        if ($observer->getEvent()->getName() == self::EVENT_CREATE_ORDER_BEFORE) {

            /** @var \Magento\Checkout\Model\Cart $cart */
            $cart = $observer->getData('cart');

            /** @var \Magento\Customer\Model\Customer $customer */
            $customer = $observer->getData('customer');
            $shippingAddress = $cart->getQuote()->getShippingAddress();
        } elseif ($observer->getEvent()->getName() == self::EVENT_CAPTURE_ORDER_BEFORE) {

            /** @var \Magento\Sales\Model\Order */
            $order = $observer->getData('payment')->getOrder();
            $shippingAddress = $order->getShippingAddress();

            $customer = $order->getCustomer();
        } else {
            return;
        }

        $paypalCMID = $observer->getData('paypalCMID');
        $merchantId = $this->_paypalConfig->getStcMerchantId();

        $additionalData = $this->getAdditionalData($customer, $shippingAddress);

        $this->_loggerHandler->debug(__METHOD__ . ' | observer paypalCMID: ' . $paypalCMID);
        $this->_loggerHandler->debug(__METHOD__ . ' | observer merchantId: ' . $merchantId);

        $riskTxnRequest = new \PayPal\CommercePlatform\Model\Paypal\STC\RiskTransactionContextRequest($merchantId, $paypalCMID);

        $riskTxnRequest->body = ['additional_data' => $additionalData];

        try {
            $response = $this->_paypalApi->execute($riskTxnRequest);
        } catch (\Exception $e) {
            $this->_loggerHandler->error(__METHOD__ .  " | error: " . $e->getMessage());
            //throw new \Magento\Framework\Exception\LocalizedException(__('Gateway error. Reason: %1', $response->message), $e);
        }

        if (!in_array($response->statusCode, $this->_successCodes)) {
            $this->_loggerHandler->error(__METHOD__ .  " | error: " . $e->getMessage());

            //throw new \Magento\Framework\Exception\LocalizedException(__('Gateway error. Reason: %1', $response->message));
        }
    }

    public function getAdditionalData($customer, $shippingAddress)
    {
        $additionalData = [
            [
                'key' => 'sender_acount_id',
                'value' => $shippingAddress->getCustomerId()
            ],
            [
                "key" => "sender_first_name",
                "value" => $shippingAddress->getFirstname()
            ],
            [
                "key" => "sender_last_name",
                "value" => $shippingAddress->getLastname()
            ],
            [
                "key" => "sender_email",
                "value" => $shippingAddress->getEmail()
            ],
            [
                "key" => "sender_phone",
                "value" => $shippingAddress->getTelephone()
            ],
            [
                "key" => "sender_country_code",
                "value" => $shippingAddress->getCountryId()
            ],
            [
                "key" => "sender_create_date",
                "value" => $this->_dateTime->gmDate('Y-m-d', $customer->getCreatedAtTimestamp())
            ],
            [
                "key" => "highrisk_txn_flag",
                "value" => $this->_paypalConfig->getHighriskTxnFlag()
            ],
            [
                "key" => "vertical",
                "value" => $this->_paypalConfig->getVertical()
            ],
            [
                "key" => "cd_string_one",
                "value" => 0
            ]
        ];

        return $additionalData;
    }
}
