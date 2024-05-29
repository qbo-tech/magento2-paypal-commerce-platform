<?php

namespace PayPal\CommercePlatform\Controller\Token;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Filesystem\Driver\File;
use PayPal\CommercePlatform\Logger\Handler;
use PayPal\CommercePlatform\Model\Paypal\Core\Token;

class Index extends \Magento\Framework\App\Action\Action
{

    /** @var \Magento\Framework\Filesystem\DriverInterface */
    protected $_driver;

    /** @var \PayPal\CommercePlatform\Logger\Handler */
    protected $_loggerHandler;

    /** @var Token */
    protected $paypalAccessTokenRequest;

    /** @var \Magento\Framework\Controller\Result\JsonFactory */
    protected $_resultJsonFactory;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\Filesystem\Driver\File $driver
     * @param Token $paypalAccessTokenRequest
     * @param \PayPal\CommercePlatform\Logger\Handler $logger
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     */
    public function __construct(
        Context $context,
        File $driver,
        Token $paypalAccessTokenRequest,
        Handler $logger,
        JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
        $this->_driver        = $driver;
        $this->_loggerHandler = $logger;
        $this->paypalAccessTokenRequest = $paypalAccessTokenRequest;
        $this->_resultJsonFactory  = $resultJsonFactory;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Json|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $resultJson = $this->_resultJsonFactory->create();
        $httpErrorCode = '500';

        try {
            $accessToken = $this->paypalAccessTokenRequest->createRequest();

            if(isset($accessToken->result) && isset($accessToken->result->access_token)){
                $tokenGenerated = $this->paypalAccessTokenRequest->createGenerateTokenRequest($accessToken->result->access_token);
            } else {
                throw new \Exception(__('An error has occurred on the server, please try again later'));
            }

            if(isset($tokenGenerated->result) && isset($tokenGenerated->result->client_token)){
                $response = ['token' => $tokenGenerated->result->client_token];
            } else {
                throw new \Exception(__('An error has occurred on the server, please try again later'));
            }

        } catch (\Exception $e) {
            $this->_loggerHandler->error($e->getMessage());
            $resultJson->setData(array('reason' => $e->getMessage()));
            return $resultJson->setHttpResponseCode($httpErrorCode);
        }

        return $resultJson->setData($response);
    }
}
