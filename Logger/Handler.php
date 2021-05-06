<?php
namespace PayPal\CommercePlatform\Logger;

use Monolog\Logger;

class Handler //extends \Magento\Framework\Logger\Handler\Base
{

    /** @var \PayPal\CommercePlatform\Model\Config */
    protected $_paypalConfig;

   /** @var \Psr\Log\LoggerInterface */ 
    protected $_logger;

    /**
     * Logging level
     * @var int
     */
    protected $loggerType = Logger::DEBUG;

    /**
     * File name
     * @var string
     */
    protected $fileName = '/var/log/paypal-ppcp.log';


    public function __construct(
        \PayPal\CommercePlatform\Model\Config $config,
        \Psr\Log\LoggerInterface $logger
    )
    {
        $this->_paypalConfig = $config;
        $this->_logger       = $logger;
    }

    /**
     * Detailed debug information.
     *
     * @param string  $message
     * @param mixed[] $context
     *
     * @return void
     */
    public function debug($message, array $context = array())
    {
        if ($this->_paypalConfig->isSetFLag(\PayPal\CommercePlatform\Model\Config::CONFIG_XML_DEBUG_MODE)) {
            $this->_logger->debug($message, $context);
        }
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string  $message
     * @param mixed[] $context
     *
     * @return void
     */
    public function error($message, array $context = array())
    {
        $this->_logger->error($message, $context);
    }
}