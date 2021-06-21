<?php

namespace PayPal\CommercePlatform\Controller\Order;

class Index extends \Magento\Framework\App\Action\Action
{

    const FRAUDNET_CMI_PARAM = 'fraudNetCMI';

    /** @var \Magento\Framework\Filesystem\DriverInterface */
    protected $_driver;

    /** @var \PayPal\CommercePlatform\Logger\Handler */
    protected $_loggerHandler;

    /** @var \PayPal\CommercePlatform\Model\Paypal\Order\Order */
    protected $_paypalOrder;

    /** @var \Magento\Framework\Controller\Result\JsonFactory */
    protected $_resultJsonFactory;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Filesystem\Driver\File $driver,
        \PayPal\CommercePlatform\Model\Paypal\Order\Order $paypalOrder,
        \PayPal\CommercePlatform\Logger\Handler $logger,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory

    ) {
        parent::__construct($context);

        $this->_driver        = $driver;
        $this->_loggerHandler = $logger;
        $this->_paypalOrder   = $paypalOrder;
        $this->_resultJsonFactory  = $resultJsonFactory;
    }

    public function execute()
    {
        $resultJson = $this->_resultJsonFactory->create();

        $httpBadRequestCode = '400';
        $httpErrorCode = '500';

        try {
            $paramsData = json_decode($this->_driver->fileGetContents('php://input'), true);

            /** @var \PayPalHttp\HttpResponse $response */
            $response = $this->_paypalOrder->createRequest($paramsData[self::FRAUDNET_CMI_PARAM]);
        } catch (\Exception $e) {
            $this->_loggerHandler->error($e->getMessage());

            $resultJson->setData(array('reason' => $e->getMessage()));

            return $resultJson->setHttpResponseCode($httpErrorCode);
        }

        return $resultJson->setData($response);
    }
}
