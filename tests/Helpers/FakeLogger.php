<?php
/** @noinspection PhpComposerExtensionStubsInspection */

namespace Vectorface\Tests\Cache\Helpers;

use Psr\Log\LoggerInterface;

class FakeLogger implements LoggerInterface
{
    private $lastMessage;

    #[\ReturnTypeWillChange]
    public function emergency($message, array $context = [])
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    #[\ReturnTypeWillChange]
    public function alert($message, array $context = [])
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    #[\ReturnTypeWillChange]
    public function critical($message, array $context = [])
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    #[\ReturnTypeWillChange]
    public function error($message, array $context = [])
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    #[\ReturnTypeWillChange]
    public function warning($message, array $context = [])
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    #[\ReturnTypeWillChange]
    public function notice($message, array $context = [])
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    #[\ReturnTypeWillChange]
    public function info($message, array $context = [])
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    #[\ReturnTypeWillChange]
    public function debug($message, array $context = [])
    {
        $this->log(__FUNCTION__, $message, $context);
    }

    #[\ReturnTypeWillChange]
    public function log($level, $message, array $context = [])
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
