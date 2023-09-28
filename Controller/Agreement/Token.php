<?php

namespace PayPal\CommercePlatform\Controller\Agreement;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Filesystem\Driver\File;
use PayPal\CommercePlatform\Logger\Handler;
use PayPal\CommercePlatform\Model\Paypal\Agreement\Token\Request;

class Token extends \Magento\Framework\App\Action\Action
{

    const FRAUDNET_CMI_PARAM = 'fraudNetCMI';
    const CUSTOMER_ID_PARAM = 'customer_email';

    /** @var \Magento\Framework\Filesystem\DriverInterface */
    protected $_driver;

    /** @var \PayPal\CommercePlatform\Logger\Handler */
    protected $_loggerHandler;

    /** @var \PayPal\CommercePlatform\Model\Paypal\Agreement\Token\Request */
    protected $paypalAgreementTokenRequest;

    /** @var \Magento\Framework\Controller\Result\JsonFactory */
    protected $_resultJsonFactory;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\Filesystem\Driver\File $driver
     * @param \PayPal\CommercePlatform\Model\Paypal\Agreement\Token\Request $paypalAgreementTokenRequest
     * @param \PayPal\CommercePlatform\Logger\Handler $logger
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     */
    public function __construct(
        Context $context,
        File $driver,
        Request $paypalAgreementTokenRequest,
        Handler $logger,
        JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
        $this->_driver        = $driver;
        $this->_loggerHandler = $logger;
        $this->paypalAgreementTokenRequest = $paypalAgreementTokenRequest;
        $this->_resultJsonFactory  = $resultJsonFactory;
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
            $customerEmail = $paramsData[self::CUSTOMER_ID_PARAM] ?? null;

            $response = $this->paypalAgreementTokenRequest->createRequest($customerEmail, $paypalCMID);

        } catch (\Exception $e) {
            $this->_loggerHandler->error($e->getMessage());
            $resultJson->setData(array('reason' => __('An error has occurred on the server, please try again later')));

            return $resultJson->setHttpResponseCode($httpErrorCode);
        }

        return $resultJson->setData($response);
    }
}
