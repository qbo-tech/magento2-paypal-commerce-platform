<?php

namespace PayPal\CommercePlatform\Controller\Agreement;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Filesystem\Driver\File;
use PayPal\CommercePlatform\Logger\Handler;
use PayPal\CommercePlatform\Model\Paypal\Agreement\Financing\Request;

class Financing extends \Magento\Framework\App\Action\Action
{

    const FRAUDNET_CMI_PARAM = 'fraudNetCMI';
    const AGREEMENT_REFERENCE = 'agreementReference';

    /** @var \Magento\Framework\Filesystem\DriverInterface */
    protected $_driver;

    /** @var \PayPal\CommercePlatform\Logger\Handler */
    protected $_loggerHandler;

    /** @var \PayPal\CommercePlatform\Model\Paypal\Agreement\Financing\Request */
    protected $paypalFinancingRequest;

    /** @var \Magento\Framework\Controller\Result\JsonFactory */
    protected $_resultJsonFactory;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\Filesystem\Driver\File $driver
     * @param \PayPal\CommercePlatform\Model\Paypal\Agreement\Financing\Request $paypalAgreementTokenRequest
     * @param \PayPal\CommercePlatform\Logger\Handler $logger
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     */
    public function __construct(
        Context $context,
        File $driver,
        Request $paypalFinancingRequest,
        Handler $logger,
        JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
        $this->_driver        = $driver;
        $this->_loggerHandler = $logger;
        $this->paypalFinancingRequest = $paypalFinancingRequest;
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
            $agreementReference = $paramsData[self::AGREEMENT_REFERENCE] ?? null;

            $response = $this->paypalFinancingRequest->createRequest($agreementReference, $paypalCMID);

        } catch (\Exception $e) {
            $this->_loggerHandler->error($e->getMessage());
//            $resultJson->setData(array('reason' => __('An error has occurred on the server, please try again later')));
            $resultJson->setData(array('reason' => $e->getMessage()) );

            return $resultJson->setHttpResponseCode($httpErrorCode);
        }

        return $resultJson->setData($response);
    }
}
