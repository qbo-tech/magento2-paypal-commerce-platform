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
 * @author José Castañeda <jose@qbo.tech>
 * @category qbo
 *
 * @copyright   qbo (http://www.qbo.tech)
 * @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * 
 * © 2021 QBO DIGITAL SOLUTIONS. 
 *
 */

/**
 * Description of Info
 *
 * @author kasta
 */

namespace PayPal\CommercePlatform\Block;

use Magento\Framework\Pricing\PriceCurrencyInterface;

class Info extends \Magento\Payment\Block\Info
{

    /**
     * @var string
     */
    protected $_template = 'PayPal_CommercePlatform::info/default.phtml';

    /** @var PriceCurrencyInterface $priceCurrency */
    protected $priceCurrency;

    const ALLOWED_FIELDS = ["payment_id", "term", "consumer_fee_amount", "installments_type"];

    /**
     * Constructor
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Payment\Model\Config $paymentConfig
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Payment\Model\Config $paymentConfig,
        PriceCurrencyInterface $priceCurrency,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->paymentConfig = $paymentConfig;
        $this->priceCurrency = $priceCurrency;
    }
    /**
     * 
     * @param type $transport
     * @return type
     */
    protected function _prepareSpecificInformation($transport = null) 
    {
        $transport = parent::_prepareSpecificInformation($transport);
        $result = [];
        $info = $this->getInfo();

        if ($info->getAdditionalInformation()) {
            foreach ($info->getAdditionalInformation() as $field => $value) {
                if(in_array($field, self::ALLOWED_FIELDS)) {
                    $value = $field == "installments_type" ? __("Yes") : $value;
                    $value = $field == "consumer_fee_amount" ? $this->priceCurrency->convertAndFormat($value, false) : $value;
                    $this->_beautifyField($result, $field, $value);
                }
            } 
        }      
        return $transport->setData(array_merge($result, $transport->getData()));
    }
   
   /**
    * Prepare and trsanform fields
    * Remove "_" and replace for capitals
    */
    protected function _beautifyField(&$result, $field, $value) 
    {
        $beautifiedFieldName = str_replace("_", " ", ucwords(trim(preg_replace('/(?<=\\w)(?=[A-Z])/', " $1", $field))));
        $result[__($beautifiedFieldName)->__toString()] = __($value);
        
        return $result;
    }

}
