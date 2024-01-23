<?php

namespace PayPal\CommercePlatform\Controller\Agreement;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Filesystem\Driver\File;
use PayPal\CommercePlatform\Logger\Handler;
use PayPal\CommercePlatform\Model\Billing\Agreement;
use PayPal\CommercePlatform\Model\Paypal\Agreement\Request;

class Create extends \Magento\Framework\App\Action\Action
{

    const FRAUDNET_CMI_PARAM = 'fraudNetCMI';
    const BILLING_TOKEN = 'billingToken';

    /** @var \Magento\Framework\Filesystem\DriverInterface */
    protected $_driver;

    /** @var \PayPal\CommercePlatform\Logger\Handler */
    protected $_loggerHandler;

    /** @var \PayPal\CommercePlatform\Model\Paypal\Agreement\Request */
    protected $paypalAgreementRequest;

    /** @var \Magento\Framework\Controller\Result\JsonFactory */
    protected $_resultJsonFactory;
    private \Magento\Customer\Model\Session $customerSession;

    /** @var \PayPal\CommercePlatform\Model\Billing\Agreement */
    protected $_billingAgreement;

    /**
     * @param Context $context
     * @param File $driver
     * @param Request $paypalAgreementRequest
     * @param Handler $logger
     * @param JsonFactory $resultJsonFactory
     * @param Session $customerSession
     * @param Agreement $billingAgreement
     */
    public function __construct(
        Context $context,
        File $driver,
        Request $paypalAgreementRequest,
        Handler $logger,
        JsonFactory $resultJsonFactory,
        \Magento\Customer\Model\Session $customerSession,
        \PayPal\CommercePlatform\Model\Billing\Agreement $billingAgreement
    ) {
        parent::__construct($context);
        $this->_driver = $driver;
        $this->_loggerHandler = $logger;
        $this->paypalAgreementRequest = $paypalAgreementRequest;
        $this->_resultJsonFactory = $resultJsonFactory;
        $this->customerSession = $customerSession;
        $this->_billingAgreement = $billingAgreement;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\Result\Json|\Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $resultJson = $this->_resultJsonFactory->create();
        $billingAgreementsData = [];
        $httpErrorCode = '500';

        try {
            $paramsData = json_decode($this->_driver->fileGetContents('php://input'), true);
            $paypalCMID = $paramsData[self::FRAUDNET_CMI_PARAM] ?? null;
            $billingToken = $paramsData[self::BILLING_TOKEN] ?? null;
            $response = $this->paypalAgreementRequest->createRequest($billingToken, $paypalCMID);

            if (isset($response->result->id) && isset($response->result->payer)) {
                $customerId = $this->customerSession->getCustomerId();
                $payerEmail = $response->result->payer->payer_info->email;

                if($customerId > 0) {
                    if (!$this->validateExistBAByPayer($customerId, $payerEmail)) {
                        $this->saveAgreement($response->result->id, $payerEmail);
                    }

                    $billingAgreementsData = $this->getCustomerAgreements($customerId);
                } else {
                    $billingAgreementsData[] = [
                        'id' => 'guest-'.date('dmYHis'),
                        'reference' => $this->_billingAgreement->encryptReference($response->result->id),
                        'email' => $payerEmail,
                        'status' => 'active'
                    ];
                }

            }

        } catch (\Exception $e) {
            $this->_loggerHandler->error($e->getMessage());
            $resultJson->setData(array('reason' => __('An error has occurred on the server, please try again later')));

            return $resultJson->setHttpResponseCode($httpErrorCode);
        }

        return $resultJson->setData(['paypal' => $response, 'billingAgreements' => $billingAgreementsData]);
    }

    private function validateExistBAByPayer($customerId, $payerEmail)
    {
        $agreements = $this->_billingAgreement->getAvailableBillingAgreementsByPayer($customerId, $payerEmail);
        return count($agreements) > 0;
    }

    private function getCustomerAgreements($customerId)
    {
        $agreements = $this->_billingAgreement->getAvailableCustomerBillingAgreements($customerId);
        $agreementsIds = [];
        foreach ($agreements as $agreement) {
            $agreementsIds[] = [
                'id' => $agreement->getAgreementId(),
                'reference' => $agreement->getReferenceId(),
                'email' => $agreement->getPayerEmail(),
                'status' => $agreement->getStatus()
            ];
        }

        return $agreementsIds;
    }

    private function saveAgreement($id, $email)
    {
        $billingAgreement = $this->_objectManager->create(\PayPal\CommercePlatform\Model\Billing\Agreement::class);
        $billingAgreement->setReferenceId($id);
        $billingAgreement->setPayerEmail($email);
        $billingAgreement->setCustomerId($this->customerSession->getCustomerId());
        $billingAgreement->setStatus(\PayPal\CommercePlatform\Model\Billing\Agreement::STATUS_ACTIVE);
        $resourceModel = $billingAgreement->getResource();
        $resourceModel->save($billingAgreement);

        return $billingAgreement;
    }
}
