<?php
/**
 * MMDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDMMM
 * MDDDDDDDDDDDDDNNDDDDDDDDDDDDDDDDD=.DDDDDDDDDDDDDDDDDDDDDDDMM
 * MDDDDDDDDDDDD===8NDDDDDDDDDDDDDDD=.NDDDDDDDDDDDDDDDDDDDDDDMM
 * DDDDDDDDDN===+N====NDDDDDDDDDDDDD=.DDDDDDDDDDDDDDDDDDDDDDDDM
 * DDDDDDD$DN=8DDDDDD=~~~DDDDDDDDDND=.NDDDDDNDNDDDDDDDDDDDDDDDM
 * DDDDDDD+===NDDDDDDDDN~~N........8$........D ........DDDDDDDM
 * DDDDDDD+=D+===NDDDDDN~~N.?DDDDDDDDDDDDDD:.D .DDDDD .DDDDDDDN
 * DDDDDDD++DDDN===DDDDD~~N.?DDDDDDDDDDDDDD:.D .DDDDD .DDDDDDDD
 * DDDDDDD++DDDDD==DDDDN~~N.?DDDDDDDDDDDDDD:.D .DDDDD .DDDDDDDN
 * DDDDDDD++DDDDD==DDDDD~~N.... ...8$........D ........DDDDDDDM
 * DDDDDDD$===8DD==DD~~~~DDDDDDDDN.IDDDDDDDDDDDNDDDDDDNDDDDDDDM
 * NDDDDDDDDD===D====~NDDDDDD?DNNN.IDNODDDDDDDDN?DNNDDDDDDDDDDM
 * MDDDDDDDDDDDDD==8DDDDDDDDDDDDDN.IDDDNDDDDDDDDNDDNDDDDDDDDDMM
 * MDDDDDDDDDDDDDDDDDDDDDDDDDDDDDN.IDDDDDDDDDDDDDDDDDDDDDDDDDMM
 * MMDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDMMM
 *
 * @author Eduardo Garcia <eduardo@eterlabs.com>
 * @category qbo
 *
 * @copyright   qbo (http://www.qbo.tech)
 * @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * © 2021 QBO DIGITAL SOLUTIONS.
 *
 */

namespace PayPal\CommercePlatform\Block;

use Magento\Checkout\Model\Session;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Payment\Model\Config;
use Magento\Sales\Api\OrderRepositoryInterface;
use PayPal\CommercePlatform\Logger\Handler;
use PayPal\CommercePlatform\Model\Paypal\Api;

/**
 * Class Success
 * @package PayPal\CommercePlatform\Block
 */
class OrderAccount extends Template
{
    protected $_successCodes = ['200', '201'];

    /**
     * @var \PayPal\CommercePlatform\Model\Paypal\Api
     */
    private $paypalApi;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var \PayPal\CommercePlatform\Logger\Handler
     */
    private $logger;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \PayPal\CommercePlatform\Model\Paypal\Api $paypalApi
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \PayPal\CommercePlatform\Logger\Handler $logger
     * @param array $data
     */
    public function __construct(
        Context $context,
        Api $paypalApi,
        Session $checkoutSession,
        Handler $logger,
        OrderRepositoryInterface $orderRepository,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->paypalApi = $paypalApi;
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @return void
     */
    public function  getVoucherUrl()
    {
        try {
            $order = $this->getOrder();
            $paypalOrderId = $order->getPayment()->getAdditionalInformation('order_id');
            $voucherRequest = $this->paypalApi->getVoucherRequest($paypalOrderId);
            $response = $this->paypalApi->execute($voucherRequest);
            if (isset($response->result->payment_source->oxxo->document_references[0])) {
                return $response->result->payment_source->oxxo->document_references[0]->value;
            } else {
                return;
            }
        } catch (\Exception $e) {
            $this->logger->error(sprintf('[PAYPAL COMMERCE SUCCESS ERROR] - %s', $e->getMessage()));
            $this->logger->error(__METHOD__ . ' | Exception : ' . $e->getMessage());
            return;
        }
    }

    /**
     * @return bool
     */
    public function isOxxoPay()
    {
        $order = $this->getOrder();
        $paymentCode = $order->getPayment()->getMethod();
        return $paymentCode == 'paypaloxxo';
    }

    /**
     * @return \Magento\Sales\Api\Data\OrderInterface
     */
    public function getOrder(): \Magento\Sales\Api\Data\OrderInterface
    {
        $orderId = $this->getRequest()->getParam('order_id');
        return $this->orderRepository->get($orderId);
    }


}
