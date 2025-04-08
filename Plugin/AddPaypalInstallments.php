<?php

namespace Paypal\CommercePlatform\Plugin;

use Magento\Sales\Block\Adminhtml\Order\View\Tab\Info;
use Magento\Framework\Phrase;

class AddPaypalInstallments
{
    public function afterGetPaymentHtml(Info $subject, $result)
    {
        $order = $subject->getOrder();
        $payment = $order->getPayment();

        if ($payment && $payment->getMethod() === 'paypalspb') {
            $additionalInfo = $payment->getAdditionalInformation();

            $installments = null;
            $installmentTypeLabel = __('MSI');; // Default to MSI
            $installmentsCost = null; // Default to MSI

            if (!empty($additionalInfo['payment_source'])) {
                $paymentSource = json_decode($additionalInfo['payment_source'], true);

                if (isset($paymentSource['token']['attributes']['installments']['term'])) {
                    $installments = $paymentSource['token']['attributes']['installments']['term'];
                }

                if (!empty($paymentSource['token']['attributes']['installments']['fee_reference_id'])) {
                    $installmentTypeLabel = __('MCI');
                }

                if (!empty($paymentSource['token']['attributes']['installments']['total_consumer_fee'])) {
                    $installmentsCost = $paymentSource['token']['attributes']['installments']['total_consumer_fee'];
                }
            }

            if (!empty($additionalInfo['term'])) {
                $installments = $additionalInfo['term'];
            }

            if (!empty($additionalInfo['consumer_fee_amount'])) {
                $installmentsCost = $additionalInfo['consumer_fee_amount'];
                $installmentTypeLabel = __('MCI');
            }

            if($installments) {
                $installmentInfo = "<p><strong>" . __('PayPal Installments:') . "</strong> $installments ($installmentTypeLabel)";

                if ($installmentsCost) {
                    $installmentInfo .= " " . __('with total fee:') . " $installmentsCost";
                }

                $installmentInfo.="</p>";

                $result .= $installmentInfo;
            }
        }

        return $result;
    }
}
