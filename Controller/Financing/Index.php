<?php

namespace PayPal\CommercePlatform\Controller\Financing;

use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use PayPal\CommercePlatform\Logger\Handler;
use PayPal\CommercePlatform\Model\PayPalCPConfigProvider;

class Index extends \Magento\Framework\App\Action\Action
{

    /** @var \PayPal\CommercePlatform\Logger\Handler */
    protected $_loggerHandler;

    /** @var PayPalCPConfigProvider */
    protected $_payPalCPConfigProvider;

    /** @var \Magento\Framework\Controller\Result\JsonFactory */
    protected $_resultJsonFactory;

    /**
     * @param Context $context
     * @param PayPalCPConfigProvider $payPalCPConfigProvider
     * @param \PayPal\CommercePlatform\Logger\Handler $logger
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     */
    public function __construct(
        Context $context,
        PayPalCPConfigProvider $payPalCPConfigProvider,
        Handler $logger,
        JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
        $this->_loggerHandler = $logger;
        $this->_payPalCPConfigProvider = $payPalCPConfigProvider;
        $this->_resultJsonFactory  = $resultJsonFactory;
    }

    /**
     * @return ResponseInterface|\Magento\Framework\Controller\Result\Json|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $resultJson = $this->_resultJsonFactory->create();

        try {

            $response = [
                'payments' => $this->_payPalCPConfigProvider->getCustomerPaymentTokens($this->_payPalCPConfigProvider->validateCustomerId()),
                'agreements' => $this->_payPalCPConfigProvider->getCustomerAgreements($this->_payPalCPConfigProvider->validateCustomerId())
            ];

        } catch (\Exception $e) {
            $this->_loggerHandler->error($e->getMessage());
            $resultJson->setData(array('reason' => $e->getMessage()));
            return $resultJson->setHttpResponseCode(500);
        }

        return $resultJson->setData($response);
    }
}
