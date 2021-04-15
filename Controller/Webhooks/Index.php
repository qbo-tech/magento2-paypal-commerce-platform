<?php
namespace PayPal\CommercePlatform\Controller\Webhooks;


class Index extends \Magento\Framework\App\Action\Action  implements \Magento\Framework\App\CsrfAwareActionInterface
{
    /**
     * @var \Magento\Framework\Filesystem\DriverInterface
     */
    protected $_driver;


    /**
     * @var \PayPal\CommercePlatform\Model\Paypal\Webhooks\Event
     */
    protected $_webhookEvent;

    /**
     * Class constructor
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\Filesystem\DriverInterface $driver
     * @param \Iways\PayPalPlus\Model\Webhook\EventFactory $webhookEvent
     * @param \Iways\PayPalPlus\Model\ApiFactory $apiFactory
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\Filesystem\Driver\File $driver,
        \PayPal\CommercePlatform\Model\Paypal\Webhooks\Event $webhookEvent,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->_logger = $logger;
        $this->_driver = $driver;
        $this->_webhookEvent = $webhookEvent;

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
        $this->_logger->debug(__METHOD__ . ' | ');
        $eventData = $this->_driver->fileGetContents('php://input');

        $this->_logger->debug(__METHOD__ . ' | $data ' . $eventData);

        if (!$this->getRequest()->isPost()) {
            return;
        }

        try {
            
            $eventData = json_decode($eventData);

            $this->_webhookEvent->processWebhook($eventData);

        } catch (\Exception $e) {
            $this->_logger->critical($e);
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
}