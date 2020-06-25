<?php
/** @noinspection PhpComposerExtensionStubsInspection */

namespace Vectorface\Tests\Cache\Helpers;

use Psr\Log\LoggerInterface;

class FakeLogger implements LoggerInterface
{
    private $lastMessage;

    public function emergency($message, array $context = array())
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    public function alert($message, array $context = array())
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    public function critical($message, array $context = array())
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    public function error($message, array $context = array())
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    public function warning($message, array $context = array())
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    public function notice($message, array $context = array())
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    public function info($message, array $context = array())
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    public function debug($message, array $context = array())
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    public function log($level, $message, array $context = array())
    {
        $this->lastMessage = sprintf(
            "%s: %s%s",
            $level,
            $message,
            empty($context) ? "" : "(context: " . json_encode($context) . ")"
        );
    }

    public function getLastMessage()
    {
        return $this->lastMessage;
    }
}
