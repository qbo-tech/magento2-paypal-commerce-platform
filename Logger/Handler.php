<?php
namespace PayPal\CommercePlatform\Logger;

use Psr\Log\LoggerInterface;

class Handler implements LoggerInterface
{
    /** @var \PayPal\CommercePlatform\Model\Config */
    protected $_paypalConfig;

    /** @var LoggerInterface */
    protected $_logger;

    public function __construct(
        \PayPal\CommercePlatform\Model\Config $config,
        LoggerInterface $logger
    ) {
        $this->_paypalConfig = $config;
        $this->_logger       = $logger;
    }

    /**
     * System is unusable.
     *
     * @param string  $message
     * @param mixed[] $context
     *
     * @return void
     */
    public function emergency($message, array $context = array())
    {
        $this->_logger->emergency($message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * @param string  $message
     * @param mixed[] $context
     *
     * @return void
     */
    public function alert($message, array $context = array())
    {
        $this->_logger->alert($message, $context);
    }

    /**
     * Critical conditions.
     *
     * @param string  $message
     * @param mixed[] $context
     *
     * @return void
     */
    public function critical($message, array $context = array())
    {
        $this->_logger->critical($message, $context);
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

    /**
     * Exceptional occurrences that are not errors.
     *
     * @param string  $message
     * @param mixed[] $context
     *
     * @return void
     */
    public function warning($message, array $context = array())
    {
        $this->_logger->warning($message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string  $message
     * @param mixed[] $context
     *
     * @return void
     */
    public function notice($message, array $context = array())
    {
        $this->_logger->notice($message, $context);
    }

    /**
     * Interesting events.
     *
     * @param string  $message
     * @param mixed[] $context
     *
     * @return void
     */
    public function info($message, array $context = array())
    {
        $this->_logger->info($message, $context);
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
     * Logs with an arbitrary level.
     *
     * @param mixed   $level
     * @param string  $message
     * @param mixed[] $context
     *
     * @return void
     */
    public function log($level, $message, array $context = array())
    {
        $this->_logger->log($level, $message, $context);
    }
}