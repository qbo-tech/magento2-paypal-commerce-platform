<?php

namespace PayPal\CommercePlatform\Model\Billing;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Stdlib\DateTime\DateTimeFactory;
use PayPal\CommercePlatform\Model\ResourceModel\Billing\Agreement\CollectionFactory;

/**
 * Billing Agreement abstract model
 *
 * @api
 * @method string getAgreementId()
 * @method \PayPal\CommercePlatform\Model\Billing\Agreement setAgreementId(string $value)
 * @method string getReferenceId()
 * @method \PayPal\CommercePlatform\Model\Billing\Agreement setReferenceId(string $value)
 * @method string getPayerEmail()
 * @method \PayPal\CommercePlatform\Model\Billing\Agreement setPayerEmail(string $value)
 * @method int getCustomerId()
 * @method \PayPal\CommercePlatform\Model\Billing\Agreement setCustomerId(int $value)
 * @method string getStatus()
 * @method \PayPal\CommercePlatform\Model\Billing\Agreement setStatus(string $value)
 * @method string getCreatedAt()
 * @method \PayPal\CommercePlatform\Model\Billing\Agreement setCreatedAt(string $value)
 * @method string getUpdatedAt()
 * @method \PayPal\CommercePlatform\Model\Billing\Agreement setUpdatedAt(string $value)
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @since 100.0.2
 */
class Agreement extends \Magento\Framework\Model\AbstractModel
{
    const STATUS_ACTIVE = 'active';
    const STATUS_CANCELED = 'canceled';

    /**
     * @var \PayPal\CommercePlatform\Model\ResourceModel\Billing\Agreement\CollectionFactory
     */
    protected $_billingAgreementFactory;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTimeFactory
     */
    protected $_dateFactory;

    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    protected $_encryptor;

    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \PayPal\CommercePlatform\Model\ResourceModel\Billing\Agreement\CollectionFactory $billingAgreementFactory,
        \Magento\Framework\Stdlib\DateTime\DateTimeFactory $dateFactory,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->_billingAgreementFactory = $billingAgreementFactory;
        $this->_dateFactory = $dateFactory;
        $this->_encryptor= $encryptor;
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(\PayPal\CommercePlatform\Model\ResourceModel\Billing\Agreement::class);
    }

    /**
     * Set created_at parameter
     *
     * @return \Magento\Framework\Model\AbstractModel
     */
    public function beforeSave()
    {
        $referenceIdEncrypted = $this->encryptReference($this->getReferenceId());
        $this->setReferenceId($referenceIdEncrypted);

        $date = $this->_dateFactory->create()->gmtDate();
        if ($this->isObjectNew() && !$this->getCreatedAt()) {
            $this->setCreatedAt($date);
        } else {
            $this->setUpdatedAt($date);
        }

    }

    public function encryptReference($string)
    {
        return $this->_encryptor->encrypt($string);
    }

    public function decryptReference($encryptedString)
    {
        return $this->_encryptor->decrypt($encryptedString);
    }

    /**
     * Retrieve billing agreement status label
     *
     * @return \Magento\Framework\Phrase|string
     */
    public function getStatusLabel()
    {
        switch ($this->getStatus()) {
            case self::STATUS_ACTIVE:
                return __('Active');
            case self::STATUS_CANCELED:
                return __('Canceled');
            default:
                return '';
        }
    }


    /**
     * Cancel billing agreement
     *
     * @return $this
     */
    public function cancel()
    {
        $this->setStatus(self::STATUS_CANCELED);
        $this->getPaymentMethodInstance()->updateBillingAgreementStatus($this);
        return $this->save();
    }

    /**
     * Check whether can cancel billing agreement
     *
     * @return bool
     */
    public function canCancel()
    {
        return $this->getStatus() != self::STATUS_CANCELED;
    }

    /**
     * Retrieve billing agreement statuses array
     *
     * @return array
     */
    public function getStatusesArray()
    {
        return [
            self::STATUS_ACTIVE     => __('Active'),
            self::STATUS_CANCELED   => __('Canceled')
        ];
    }

    /**
     * Validate data
     *
     * @return bool
     */
    public function isValid()
    {
        $result = parent::isValid();
        if (!$this->getCustomerId()) {
            $this->_errors[] = __('The customer ID is not set.');
        }
        if (!$this->getStatus()) {
            $this->_errors[] = __('The Billing Agreement status is not set.');
        }
        return $result && empty($this->_errors);
    }


    /**
     * Retrieve available customer Billing Agreements
     *
     * @param int $customerId
     * @return \PayPal\CommercePlatform\Model\ResourceModel\Billing\Agreement\Collection
     */
    public function getAvailableCustomerBillingAgreements($customerId)
    {
        $collection = $this->_billingAgreementFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId)
            ->addFieldToFilter('status', self::STATUS_ACTIVE);
        return $collection;
    }

    /**
     * Retrieve available customer Billing Agreements
     *
     * @param int $customerId
     * @param string $payerEmail
     * @return \PayPal\CommercePlatform\Model\ResourceModel\Billing\Agreement\Collection
     */
    public function getAvailableBillingAgreementsByPayer($customerId, $payerEmail)
    {
        $collection = $this->_billingAgreementFactory->create();
        $collection
            ->addFieldToFilter('customer_id', $customerId)
            ->addFieldToFilter('payer_email', $payerEmail)
            ->addFieldToFilter('status', self::STATUS_ACTIVE);
        return $collection;
    }

    /**
     * Check whether need to create billing agreement for customer
     *
     * @param int $customerId
     * @return bool
     */
    public function needToCreateForCustomer($customerId)
    {
        return $customerId ? count($this->getAvailableCustomerBillingAgreements($customerId)) == 0 : false;
    }
}
