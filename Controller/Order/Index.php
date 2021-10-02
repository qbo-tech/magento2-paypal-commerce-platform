<?php

namespace PayPal\CommercePlatform\Controller\Order;

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

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Filesystem\Driver\File $driver,
        \PayPal\CommercePlatform\Model\Paypal\Order\Request $paypalOrderRequest,
        \PayPal\CommercePlatform\Logger\Handler $logger,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);

        $this->_driver        = $driver;
        $this->_loggerHandler = $logger;
        $this->_paypalOrderRequest = $paypalOrderRequest;
        $this->_resultJsonFactory  = $resultJsonFactory;
    }

    public function execute()
    {
        $resultJson = $this->_resultJsonFactory->create();

        $httpBadRequestCode = '400';
        $httpErrorCode = '500';

        try {
            $paramsData = json_decode($this->_driver->fileGetContents('php://input'), true);
            $paypalCMID = $paramsData[self::FRAUDNET_CMI_PARAM] ?? null;

            $this->_loggerHandler->debug(__METHOD__ . ' | paypalCMID: ' . $paypalCMID);

            /** @var \PayPalHttp\HttpResponse $response */
            $response = $this->_paypalOrderRequest->createRequest($paypalCMID);
        } catch (\Exception $e) {
            $this->_loggerHandler->error($e->getMessage());

            $resultJson->setData(array('reason' => $e->getMessage()));

            return $resultJson->setHttpResponseCode($httpErrorCode);
        }

        return $resultJson->setData($response);
    }
}
