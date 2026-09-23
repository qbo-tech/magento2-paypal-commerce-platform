<?php

namespace PayPal\CommercePlatform\Model\Paypal\Oxxo;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use PayPal\CommercePlatform\Model\Payment\Oxxo\Payment as OxxoPayment;

class StatusUpdater
{
    /**
     * @var \PayPal\CommercePlatform\Model\Paypal\Api
     */
    private $api;

    /**
     * @var \PayPal\CommercePlatform\Model\Paypal\Webhooks\Event
     */
    private $event;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    public function __construct(
        \PayPal\CommercePlatform\Model\Paypal\Api $api,
        \PayPal\CommercePlatform\Model\Paypal\Webhooks\Event $event,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->api    = $api;
        $this->event  = $event;
        $this->logger = $logger;
    }

    /**
     * Fetch the PayPal order and apply the status of its latest capture to the Magento order
     *
     * @param Order $order
     * @return array ['status' => string|null, 'transaction_id' => string|null, 'paypal_status' => string|null]
     * @throws LocalizedException
     */
    public function update(Order $order)
    {
        $payment = $order->getPayment();

        if (!$payment || $payment->getMethod() !== OxxoPayment::CODE) {
            throw new LocalizedException(__('This order was not paid with OXXO Pay.'));
        }

        if ($order->getState() !== Order::STATE_PENDING_PAYMENT) {
            throw new LocalizedException(__('The order is not pending payment.'));
        }

        $paypalOrderId = $payment->getAdditionalInformation('paypal_order_id') ?: $payment->getLastTransId();

        if (!$paypalOrderId) {
            throw new LocalizedException(__('The PayPal order id is missing on this payment.'));
        }

        $response = $this->api->execute($this->api->getOrdersGetRequest($paypalOrderId));

        if (!isset($response->result) || (int)($response->statusCode ?? 0) !== 200) {
            $this->logger->error('[PAYPAL-OXXO] Unable to fetch order status', [
                'paypal_order_id' => $paypalOrderId,
                'status_code'     => $response->statusCode ?? null,
                'message'         => $response->message ?? null,
            ]);

            throw new LocalizedException(
                __('PayPal did not return the order %1 (HTTP %2).', $paypalOrderId, $response->statusCode ?? 'n/a')
            );
        }

        $paypalOrder = json_decode(json_encode($response->result), true);
        $capture     = $this->getLatestCapture($paypalOrder);

        if (!$capture) {
            return [
                'status'         => null,
                'transaction_id' => null,
                'paypal_status'  => $paypalOrder['status'] ?? null,
            ];
        }

        $capture['supplementary_data']['related_ids']['order_id'] = $paypalOrderId;

        $status = $this->event->processCapture($payment, $capture);

        return [
            'status'         => $status,
            'transaction_id' => $capture['id'] ?? null,
            'paypal_status'  => $paypalOrder['status'] ?? null,
        ];
    }

    /**
     * @param array $paypalOrder
     * @return array|null
     */
    private function getLatestCapture(array $paypalOrder)
    {
        $captures = [];

        foreach ($paypalOrder['purchase_units'] ?? [] as $unit) {
            foreach ($unit['payments']['captures'] ?? [] as $capture) {
                $captures[] = $capture;
            }
        }

        if (!$captures) {
            return null;
        }

        usort($captures, function ($a, $b) {
            return strcmp($a['update_time'] ?? $a['create_time'] ?? '', $b['update_time'] ?? $b['create_time'] ?? '');
        });

        return end($captures);
    }
}
