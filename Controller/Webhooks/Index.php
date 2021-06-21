<?php

namespace PayPal\CommercePlatform\Controller\Webhooks;


class Index extends \Magento\Framework\App\Action\Action  implements \Magento\Framework\App\CsrfAwareActionInterface
{

    const HEADER_PAYPAL_AUTH_ALGO         = 'Paypal-Auth-Algo';
    const HEADER_PAYPAL_CERT_URL          = 'Paypal-Cert-Url';
    const HEADER_PAYPAL_TRANSMISSION_ID   = 'Paypal-Transmission-Id';
    const HEADER_PAYPAL_TRANSMISSION_SIG  = 'Paypal-Transmission-Sig';
    const HEADER_PAYPAL_TRANSMISSION_TIME = 'Paypal-Transmission-Time';

    /** @var \Magento\Framework\Filesystem\DriverInterface */
    protected $_driver;

    /** @var \PayPal\CommercePlatform\Model\Config */
    protected $_paypalConfig;

    /** @var \PayPal\CommercePlatform\Model\Paypal\Api */
    protected $_paypalApi;

    /** @var \PayPal\CommercePlatform\Model\Paypal\Webhooks\VerifyWebhookSignatureRequest */
    protected $_verifyWebhookSignature;

    /** @var \PayPal\CommercePlatform\Model\Paypal\Webhooks\Event */
    protected $_webhookEvent;

    /**
     * Class constructor
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\Filesystem\DriverInterface $driver
     * @param \PayPal\CommercePlatform\Logger\Handler $logger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Filesystem\Driver\File $driver,
        \PayPal\CommercePlatform\Model\Config $paypalConfig,
        \PayPal\CommercePlatform\Model\Paypal\Api $paypalApi,
        \PayPal\CommercePlatform\Model\Paypal\Webhooks\VerifyWebhookSignatureRequest $verifyWebhookSignature,
        \PayPal\CommercePlatform\Model\Paypal\Webhooks\Event $webhookEvent,
        \PayPal\CommercePlatform\Logger\Handler $logger
    ) {
        $this->_logger       = $logger;
        $this->_driver       = $driver;
        $this->_paypalConfig = $paypalConfig;
        $this->_paypalApi    = $paypalApi;

        $this->_verifyWebhookSignature = $verifyWebhookSignature;
        $this->_webhookEvent           = $webhookEvent;

        parent::__construct($context);
    }

    /**
     * Instantiate Event model and pass Webhook request to it
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.ExitExpression)
     */
    public function execute()
    {
        $eventData = json_decode($this->_driver->fileGetContents('php://input'), true);

        if ((!$this->getRequest()->isPost()) || (!$this->isValidWebhookSignature($eventData))) {
            $this->_logger->debug(__METHOD__ . ' | INVALID INCOMING REQUEST: ' . print_r($eventData, true));
            return;
        }

        try {
            $this->_webhookEvent->processWebhook($eventData);
        } catch (\Exception $e) {
            $this->_logger->error($e);
            $this->getResponse()->setStatusHeader(503, '1.1', 'Service Unavailable')->sendResponse();
        }
    }

    public function createCsrfValidationException(\Magento\Framework\App\RequestInterface $request): ?\Magento\Framework\App\Request\InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(\Magento\Framework\App\RequestInterface $request): ?bool
    {
        return true;
    }

    public function isValidWebhookSignature($eventData)
    {
        $request = $this->getRequest();

        $this->_verifyWebhookSignature->body = [
            'auth_algo'         => $request->getHeader(self::HEADER_PAYPAL_AUTH_ALGO),
            'cert_url'          => $request->getHeader(self::HEADER_PAYPAL_CERT_URL),
            'transmission_id'   => $request->getHeader(self::HEADER_PAYPAL_TRANSMISSION_ID),
            'transmission_sig'  => $request->getHeader(self::HEADER_PAYPAL_TRANSMISSION_SIG),
            'transmission_time' => $request->getHeader(self::HEADER_PAYPAL_TRANSMISSION_TIME),
            'webhook_id'        => $this->_paypalConfig->getWebhookId(),
            'webhook_event'     => $eventData
        ];

        $response = $this->_paypalApi->execute($this->_verifyWebhookSignature);

        return $response->result->verification_status == 'SUCCESS' ? true : false;
    }
}