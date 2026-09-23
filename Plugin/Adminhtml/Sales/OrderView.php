<?php

namespace PayPal\CommercePlatform\Plugin\Adminhtml\Sales;

use Magento\Sales\Model\Order;
use PayPal\CommercePlatform\Model\Payment\Oxxo\Payment as OxxoPayment;

class OrderView
{
    /**
     * Add the "Update Status" action to OXXO Pay orders that are still pending payment
     *
     * @param \Magento\Sales\Block\Adminhtml\Order\View $subject
     * @return void
     */
    public function beforeSetLayout(\Magento\Sales\Block\Adminhtml\Order\View $subject)
    {
        $order = $subject->getOrder();

        if (!$order
            || !$order->getPayment()
            || $order->getPayment()->getMethod() !== OxxoPayment::CODE
            || $order->getState() !== Order::STATE_PENDING_PAYMENT
        ) {
            return;
        }

        $url = $subject->getUrl('paypalcp/order/updateStatus', ['order_id' => $order->getId()]);

        $subject->addButton(
            'paypalcp_oxxo_update_status',
            [
                'label'   => __('Update Status'),
                'class'   => 'update-status',
                'onclick' => 'setLocation(\'' . $url . '\')',
            ]
        );
    }
}
