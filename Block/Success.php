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
use PayPal\CommercePlatform\Logger\Handler;
use PayPal\CommercePlatform\Model\Paypal\Api;

/**
 * Class Success
 * @package PayPal\CommercePlatform\Block
 */
class Success extends Template
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
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->paypalApi = $paypalApi;
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
    }

    /**
     * @return void
     */
    public function  getVoucherUrl()
    {
        try {
            $voucher = $this->checkoutSession->getData('paypal_voucher');
            return $voucher->href;
        } catch (\Exception $e) {
            $this->logger->error(sprintf('[PAYPAL COMMERCE SUCCESS ERROR] - %s', $e->getMessage()));
            $this->logger->error(__METHOD__ . ' | Exception : ' . $e->getMessage());
        }
    }

    /**
     * @return bool
     */
    public function isOxxoPay()
    {
        $order = $this->checkoutSession->getLastRealOrder();
        $paymentCode = $order->getPayment()->getMethod();
        return $paymentCode == 'paypaloxxo';
    }

}
