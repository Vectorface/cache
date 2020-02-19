<?php

namespace Vectorface\Cache;

use Psr\Log\LoggerInterface;

/**
 * Decorates (Wraps) a Cache implementation with logging
 *
 * Note:
 *   This logs an estimated serialized object size. Cache serialization may
 *   use a different serialization mechanism, so the size should be used to
 *   give an idea of actual cached size rather than an exact value.
 */
class LogDecorator implements Cache
{
    /**
     * The wrapped cache class
     * @var Cache
     */
    private $cache;

    /**
     * The logger instance to which operations will be logged
     *
     * @var LoggerInterface
     */
    private $log;

    /**
     * The log level, which corresponds to a PSR-3 log level function call
     *
     * @var string
     */
    private $level;

    /**
     * @param Cache $cache
     * @param LoggerInterface $log
     * @param string $level
     */
    public function __construct(Cache $cache, LoggerInterface $log = null, $level = 'debug')
    {
        $levels = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];
        if (!in_array($level, $levels)) {
            throw new \InvalidArgumentException("Incompatible log level: $level");
        }

        $this->cache = $cache;
        $this->log = $log;
        $this->level = $level;
    }

    /**
     * @inheritDoc
     */
    public function get($key, $default = null)
    {
        $result = $this->cache->get($key);
        if ($result === null) {
            $this->log(sprintf("get %s MISS", $key));
            return $default;
        }

        $this->log(sprintf(
            "get %s HIT size=%d",
            $key,
            $this->getSize($result)
        ));
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function set($key, $value, $ttl = false)
    {
        $result = $this->cache->set($key, $value, $ttl);
        $this->log(sprintf(
            "set %s %s ttl=%s, type=%s, size=%d",
            $key,
            $result ? 'SUCCESS' : 'FAILURE',
            is_numeric($ttl) ? $ttl : "false",
            gettype($value),
            $this->getSize($value)
        ));
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function delete($key)
    {
        $result = $this->cache->delete($key);
        $this->log(sprintf(
            "delete %s %s",
            $key,
            $result ? 'SUCCESS' : 'FAILURE'
        ));
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function flush()
    {
        $result = $this->cache->flush();
        $this->log(sprintf("flush %s", $result ? 'SUCCESS' : 'FAILURE'));
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function clean()
    {
        $result = $this->cache->clean();
        $this->log(sprintf("clean %s", $result ? 'SUCCESS' : 'FAILURE'));
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function clear()
    {
        return $this->flush();
    }

    /**
     * @inheritDoc
     */
    public function getMultiple($keys, $default = null)
    {
        $values = $this->cache->getMultiple($keys, $default);
        $this->log(sprintf(
            "getMultiple [%s] count=%d",
            implode(', ', $keys),
            count($values)
        ));
        return $values;
    }

    /**
     * @inheritDoc
     */
    public function setMultiple($values, $ttl = null)
    {
        $result = $this->cache->setMultiple($values, $ttl);
        $this->log(sprintf(
            "setMultiple [%s] %s ttl=%s",
            implode(', ', array_keys($values)),
            $result ? 'SUCCESS' : 'FAILURE',
            is_numeric($ttl) ? $ttl : "null"
        ));
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple($keys)
    {
        $result = $this->cache->deleteMultiple($keys);
        $this->log(sprintf(
            "deleteMultiple [%s] %s",
            implode(', ', $keys),
            $result ? 'SUCCESS' : 'FAILURE'
        ));
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function has($key)
    {
        $result = $this->cache->has($key);
        $this->log(sprintf(
            "has %s %s",
            $key,
            $result ? 'true' : 'false'
        ));
        return $result;
    }

    /**
     * Log a message to the configured logger
     *
     * @param $message
     */
    private function log(string $message)
    {
        if (!$this->log) {
            return;
        }

        ([$this->log, $this->level])($message);
    }

    /**
     * Get a reasonable estimation for the serialized size of a cacheable value
     *
     * @param mixed $val The cacheable value
     * @return int An estimate of the cached size of the value
     */
    private function getSize($val)
    {
        return strlen(is_scalar($val) ? (string)$val : serialize($val));
    }
}