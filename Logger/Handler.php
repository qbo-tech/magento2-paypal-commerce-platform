<?php
namespace PayPal\CommercePlatform\Logger;

class Handler
{

    /** @var \PayPal\CommercePlatform\Model\Config */
    protected $_paypalConfig;

   /** @var \Psr\Log\LoggerInterface */ 
    protected $_logger;

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
        if ($this->_paypalConfig->isSetFlag(\PayPal\CommercePlatform\Model\Config::CONFIG_XML_DEBUG_MODE)) {
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