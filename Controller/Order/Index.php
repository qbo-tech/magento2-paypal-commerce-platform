<?php

namespace PayPal\CommercePlatform\Controller\Order;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Filesystem\Driver\File;
use PayPal\CommercePlatform\Logger\Handler;
use PayPal\CommercePlatform\Model\Payment\Oxxo\Payment as OxxoPayment;
use PayPal\CommercePlatform\Model\Paypal\Order\Request;

class Index extends \Magento\Framework\App\Action\Action
{

    const FRAUDNET_CMI_PARAM = 'fraudNetCMI';

    /** @var \Magento\Framework\Filesystem\DriverInterface */
    protected $_driver;

    /** @var \PayPal\CommercePlatform\Logger\Handler */
    protected $_loggerHandler;

    /** @var \PayPal\CommercePlatform\Model\Paypal\Order\Request */
    protected $_paypalOrderRequest;

    /** @var \Magento\Framework\Controller\Result\JsonFactory */
    protected $_resultJsonFactory;
    /**
     * @var \PayPal\CommercePlatform\Model\Payment\Oxxo\Payment
     */
    private $oxxoPayment;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\Filesystem\Driver\File $driver
     * @param \PayPal\CommercePlatform\Model\Paypal\Order\Request $paypalOrderRequest
     * @param \PayPal\CommercePlatform\Logger\Handler $logger
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     * @param \PayPal\CommercePlatform\Model\Payment\Oxxo\Payment $oxxoPayment
     */
    public function __construct(
        Context $context,
        File $driver,
        Request $paypalOrderRequest,
        Handler $logger,
        JsonFactory $resultJsonFactory,
        OxxoPayment $oxxoPayment
    ) {
        parent::__construct($context);
        $this->_driver        = $driver;
        $this->_loggerHandler = $logger;
        $this->_paypalOrderRequest = $paypalOrderRequest;
        $this->_resultJsonFactory  = $resultJsonFactory;
        $this->oxxoPayment  = $oxxoPayment;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Json|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $resultJson = $this->_resultJsonFactory->create();
        $httpBadRequestCode = '400';
        $httpErrorCode = '500';
        try {
            $paramsData = json_decode($this->_driver->fileGetContents('php://input'), true);
            $paypalCMID = $paramsData[self::FRAUDNET_CMI_PARAM] ?? null;
            $this->_loggerHandler->debug(__METHOD__ . ' | paypalCMID: ' . $paypalCMID);
            $response = $this->_paypalOrderRequest->createRequest($paypalCMID);

            if(isset($paramsData['payment_method']) && $paramsData['payment_method'] == 'paypaloxxo') {
                $response = $this->oxxoPayment->createOxxoVoucher($paramsData['payment_source'], $response->result->id);
            }
        } catch (\Exception $e) {
            $this->_loggerHandler->error($e->getMessage());

            $resultJson->setData(array('reason' => $e->getMessage()));

            return $resultJson->setHttpResponseCode($httpErrorCode);
        }

        return $resultJson->setData($response);
    }
}
