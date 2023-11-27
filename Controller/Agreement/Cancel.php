<?php

namespace PayPal\CommercePlatform\Controller\Agreement;

class Cancel extends \Magento\Framework\App\Action\Action
{
    const ID = 'id';
    const REFERENCE = 'reference';

    /** @var \PayPal\CommercePlatform\Model\Paypal\Api */
    protected $_paypalApi;

    /** @var \Magento\Framework\Controller\Result\JsonFactory */
    protected $_resultJsonFactory;

    /** @var \PayPal\CommercePlatform\Logger\Handler */
    protected $_loggerHandler;

    /** @var \PayPal\CommercePlatform\Model\Billing\Agreement $billingAgreement */
    protected $billingAgreement;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \PayPal\CommercePlatform\Model\Paypal\Api $paypalApi,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \PayPal\CommercePlatform\Logger\Handler $logger,
        \PayPal\CommercePlatform\Model\Billing\Agreement $billingAgreement
    ) {
        parent::__construct($context);

        $this->_loggerHandler     = $logger;
        $this->_paypalApi         = $paypalApi;
        $this->_resultJsonFactory = $resultJsonFactory;
        $this->billingAgreement = $billingAgreement;
    }

    public function execute()
    {
        $requestContent = json_decode($this->getRequest()->getContent(), true);

        $id = $requestContent[self::ID] ?? null;
        $reference = $requestContent[self::REFERENCE] ?? null;
        $billingAgreement =  $this->billingAgreement->decryptReference($reference);

        if (!$id) {
            return false;
        }

        $this->removeBillingAgreement($id);

        try {

            $resultJson = $this->_resultJsonFactory->create();
            /** @var \PayPalHttp\HttpResponse $response */
            $response = $this->_paypalApi->execute(new \PayPal\CommercePlatform\Model\Paypal\Agreement\Cancel($billingAgreement));
            $resultJson->setHttpResponseCode($response->statusCode);

        } catch (\Exception $e) {
            $this->_loggerHandler->error($e->getMessage());
        }

        return $resultJson->setData($response);
    }

    private function removeBillingAgreement($billingAgreementId) {
        $billingAgreementModel = $this->billingAgreement->load($billingAgreementId);

        if (!$billingAgreementModel->getId()) {
            return;
        }

        $billingAgreementModel->delete();
    }

}

