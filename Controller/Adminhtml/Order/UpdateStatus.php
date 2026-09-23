<?php

namespace PayPal\CommercePlatform\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Exception\LocalizedException;

class UpdateStatus extends Action implements HttpGetActionInterface
{
    const ADMIN_RESOURCE = 'Magento_Sales::actions_view';

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var \PayPal\CommercePlatform\Model\Paypal\Oxxo\StatusUpdater
     */
    private $statusUpdater;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    public function __construct(
        Action\Context $context,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \PayPal\CommercePlatform\Model\Paypal\Oxxo\StatusUpdater $statusUpdater,
        \Psr\Log\LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->orderRepository = $orderRepository;
        $this->statusUpdater   = $statusUpdater;
        $this->logger          = $logger;
    }

    /**
     * Query the OXXO Pay transaction status at PayPal and apply it to the order
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $orderId  = (int)$this->getRequest()->getParam('order_id');
        $redirect = $this->resultRedirectFactory->create();

        try {
            $order  = $this->orderRepository->get($orderId);
            $result = $this->statusUpdater->update($order);

            if ($result['status'] === 'COMPLETED') {
                $this->messageManager->addSuccessMessage(
                    __('OXXO Pay payment confirmed. Transaction ID: %1.', $result['transaction_id'])
                );
            } elseif ($result['status']) {
                $this->messageManager->addNoticeMessage(
                    __('OXXO Pay transaction status: %1. Transaction ID: %2.', $result['status'], $result['transaction_id'])
                );
            } else {
                $this->messageManager->addNoticeMessage(
                    __('PayPal has not registered a capture for this order yet (PayPal order status: %1).', $result['paypal_status'])
                );
            }
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->error('[PAYPAL-OXXO] Manual status update failed: ' . $e->getMessage());
            $this->messageManager->addErrorMessage(__('Unable to update the OXXO Pay transaction status.'));
        }

        return $redirect->setPath('sales/order/view', ['order_id' => $orderId]);
    }
}
