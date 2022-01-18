<?php

namespace PayPal\CommercePlatform\Controller\Order;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use PayPal\CommercePlatform\Model\Payment\Oxxo\Payment;

/**
 * Class Email
 * @package PayPal\CommercePlatform\Controller\Order
 */
class Email extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \PayPal\CommercePlatform\Model\Payment\Oxxo\Payment
     */
    private Payment $payment;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    private JsonFactory $_resultJsonFactory;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \PayPal\CommercePlatform\Model\Payment\Oxxo\Payment $payment
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     */
    public function __construct(
        Context $context,
        Payment $payment,
        JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
        $this->payment = $payment;
        $this->_resultJsonFactory  = $resultJsonFactory;
    }

    public function execute()
    {
        $resultJson = $this->_resultJsonFactory->create();
        try {
            $paypalOrderId = $this->_request->getParam('order_id');
            $this->payment->sendOxxoEmail($paypalOrderId);
        } catch (\Exception $e) {
            $this->_loggerHandler->error($e->getMessage());
        }

        $response = ['success' => true];
        return $resultJson->setData($response);
    }
}
