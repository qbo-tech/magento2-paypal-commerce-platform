<?php

namespace PayPal\CommercePlatform\Controller\Vault;

class Remove extends \Magento\Framework\App\Action\Action
{

    const DECIMAL_PRECISION = 2;

    /** @var \PayPal\CommercePlatform\Model\Paypal\Api */
    protected $_paypalApi;

    /** @var \Magento\Framework\Controller\Result\JsonFactory */
    protected $_resultJsonFactory;

    /** @var \PayPal\CommercePlatform\Logger\Handler */
    protected $_loggerHandler;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \PayPal\CommercePlatform\Model\Paypal\Api $paypalApi,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \PayPal\CommercePlatform\Logger\Handler $logger
    ) {
        parent::__construct($context);

        $this->_loggerHandler     = $logger;
        $this->_paypalApi         = $paypalApi;
        $this->_resultJsonFactory = $resultJsonFactory;
    }

    public function execute()
    {
        $requestContent = json_decode($this->getRequest()->getContent(), true);

        $tokenId = $requestContent['id'] ?? null;

        if (!$tokenId) {
            return;
        }

        $resultJson = $this->_resultJsonFactory->create();

        try {

            /** @var \PayPalHttp\HttpResponse $response */
            $response = $this->_paypalApi->execute(new \PayPal\CommercePlatform\Model\Paypal\Vault\DeletePaymentTokensRequest($tokenId));

            $resultJson->setHttpResponseCode($response->statusCode);
        } catch (\Exception $e) {
            $this->_loggerHandler->error($e->getMessage());

            throw $e;
        }


        return $resultJson->setData($response);
    }
}
