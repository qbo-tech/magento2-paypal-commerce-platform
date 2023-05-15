<?php

namespace PayPal\CommercePlatform\Controller\Agreement;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Filesystem\Driver\File;
use PayPal\CommercePlatform\Logger\Handler;
use PayPal\CommercePlatform\Model\Billing\Agreement;

class Reference extends \Magento\Framework\App\Action\Action
{

    const ID = 'id';
    const REFERENCE = 'reference';

    /** @var \Magento\Framework\Filesystem\DriverInterface */
    protected $_driver;

    /** @var \PayPal\CommercePlatform\Logger\Handler */
    protected $_loggerHandler;

    /** @var \Magento\Framework\Controller\Result\JsonFactory */
    protected $_resultJsonFactory;

    /** @var \PayPal\CommercePlatform\Model\Billing\Agreement */
    protected $_billingAgreement;

    /** @var Session */
    private $checkoutSession;

    /**
     * @param Context $context
     * @param File $driver
     * @param Handler $logger
     * @param JsonFactory $resultJsonFactory
     * @param Agreement $billingAgreement
     * @param Session $customerSession
     */
    public function __construct(
        Context $context,
        File $driver,
        Handler $logger,
        JsonFactory $resultJsonFactory,
        \PayPal\CommercePlatform\Model\Billing\Agreement $billingAgreement,
        Session $checkoutSession
    ) {
        parent::__construct($context);
        $this->_driver        = $driver;
        $this->_loggerHandler = $logger;
        $this->_resultJsonFactory  = $resultJsonFactory;
        $this->_billingAgreement = $billingAgreement;
        $this->checkoutSession  = $checkoutSession;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Json|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $resultJson = $this->_resultJsonFactory->create();
        $billingAgreement = null;
        $httpErrorCode = '500';

        try {
            $paramsData = json_decode($this->_driver->fileGetContents('php://input'), true);
			$id = $paramsData[self::ID] ?? null;
			$reference = $paramsData[self::REFERENCE] ?? null;
            $this->checkoutSession->setData('current_ba_id', $id);
            $this->checkoutSession->setData('current_ba_reference', $reference);
            $billingAgreement =  $this->_billingAgreement->decryptReference($reference);
        } catch (\Exception $e) {
            $this->_loggerHandler->error($e->getMessage());
            $resultJson->setData(array('reason' => __('An error has occurred on the server, please try again later')));
            return $resultJson->setHttpResponseCode($httpErrorCode);
        }

        return $resultJson->setData(['ba' => $billingAgreement ]);
    }

}
