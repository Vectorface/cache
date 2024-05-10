<?php

namespace Vectorface\Cache;

use DateInterval;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Vectorface\Cache\Exception\CacheException;

/**
 * Decorates (Wraps) a Cache implementation with logging
 *
 * Note:
 *   This logs an estimated serialized object size. Cache serialization may
 *   use a different serialization mechanism, so the size should be used to
 *   give an idea of actual cached size rather than an exact value.
 */
class LogDecorator implements Cache, AtomicCounter
{
    /**
     * The wrapped cache class
     */
    private Cache|AtomicCounter $cache;

    /**
     * The logger instance to which operations will be logged
     */
    private LoggerInterface|null $log;

    /**
     * The log level, which corresponds to a PSR-3 log level function call
     */
    private string $level;

    /**
     * @param Cache|AtomicCounter $cache
     * @param LoggerInterface|null $log
     * @param string $level
     */
    public function __construct(Cache|AtomicCounter $cache, LoggerInterface|null $log = null, string $level = 'debug')
    {
        $levels = ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'];
        if (!in_array($level, $levels)) {
            throw new InvalidArgumentException("Incompatible log level: {$level}");
        }

        $this->cache = $cache;
        $this->log = $log;
        $this->level = $level;
    }

    /**
     * @inheritDoc
     * @throws CacheException
     */
    public function get(string $key, mixed $default = null) : mixed
    {
        $this->throwIfNotInstanceof(Cache::class);

        /** @scrutinizer ignore-call */
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
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null) : bool
    {
        $this->throwIfNotInstanceof(Cache::class);

        /** @scrutinizer ignore-call */
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
     * @throws CacheException
     */
    public function delete(string $key) : bool
    {
        $this->throwIfNotInstanceof(Cache::class);

        /** @scrutinizer ignore-call */
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
     * @throws CacheException
     */
    public function flush() : bool
    {
        $this->throwIfNotInstanceof(Cache::class);

        /** @scrutinizer ignore-call */
        $result = $this->cache->flush();
        $this->log(sprintf("flush %s", $result ? 'SUCCESS' : 'FAILURE'));
        return $result;
    }

    /**
     * @inheritDoc
     * @throws CacheException
     */
    public function clean() : bool
    {
        $this->throwIfNotInstanceof(Cache::class);

        /** @scrutinizer ignore-call */
        $result = $this->cache->clean();
        $this->log(sprintf("clean %s", $result ? 'SUCCESS' : 'FAILURE'));
        return $result;
    }

    /**
     * @inheritDoc
     * @throws CacheException
     */
    public function clear() : bool
    {
        $this->throwIfNotInstanceof(Cache::class);

        return $this->flush();
    }

    /**
     * @inheritDoc
     * @throws CacheException
     */
    public function getMultiple(iterable $keys, mixed $default = null) : iterable
    {
        $this->throwIfNotInstanceof(Cache::class);

        /** @scrutinizer ignore-call */
        $values = $this->cache->getMultiple($keys, $default);
        $this->log(sprintf(
            "getMultiple [%s] count=%s",
            implode(', ', $keys),
            is_array($values) ? count($values) : ('[' . gettype($values) . ']')
        ));
        return $values;
    }

    /**
     * @inheritDoc
     */
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null) : bool
    {
        $this->throwIfNotInstanceof(Cache::class);

        /** @scrutinizer ignore-call */
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
     * @throws CacheException
     */
    public function deleteMultiple(iterable $keys) : bool
    {
        $this->throwIfNotInstanceof(Cache::class);

        /** @scrutinizer ignore-call */
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
     * @throws CacheException
     */
    public function has(string $key) : bool
    {
        $this->throwIfNotInstanceof(Cache::class);

        /** @scrutinizer ignore-call */
        $result = $this->cache->has($key);
        $this->log(sprintf(
            "has %s %s",
            $key,
            $result ? 'true' : 'false'
        ));
        return $result;
    }

    /**
     * @inheritDoc
     * @throws CacheException
     */
    public function increment(string $key, int $step = 1, DateInterval|int|null $ttl = null) : int|false
    {
        $this->throwIfNotInstanceof(AtomicCounter::class);

        /** @scrutinizer ignore-call */
        $result = $this->cache->increment($key, $step, $ttl);
        $this->log(sprintf(
            "increment %s by %d %s, value=%d",
            $key,
            $step,
            ($result !== false ? 'SUCCESS' : 'FAILURE'),
            ($result !== false ? $result : 0)
        ));
        return $result;
    }

    /**
     * @inheritDoc
     * @throws CacheException
     */
    public function decrement(string $key, int $step = 1, DateInterval|int|null $ttl = null) : int|false
    {
        $this->throwIfNotInstanceof(AtomicCounter::class);

        /** @scrutinizer ignore-call */
        $result = $this->cache->decrement($key, $step, $ttl);
        $this->log(sprintf(
            "decrement %s by %d %s, value=%d",
            $key,
            $step,
            ($result !== false ? 'SUCCESS' : 'FAILURE'),
            ($result !== false ? $result : 0)
        ));
        return $result;
    }

    /**
     * Log a message to the configured logger
     */
    private function log(string $message) : void
    {
        if (!$this->log) {
            return;
        }

        ([$this->log, $this->level])($message);
    }

    /**
     * Guards against calls on a decorated instance that does not support the underlying method
     *
     * @param string $class
     * @throws CacheException
     */
    private function throwIfNotInstanceof(string $class) : void
    {
        if (! $this->cache instanceof $class) {
            throw new CacheException("This decorated instance does not implement {$class}");
        }
    }

    /**
     * Get a reasonable estimation for the serialized size of a cacheable value
     *
     * @param mixed $val The cacheable value
     * @return int An estimate of the cached size of the value
     */
    private function getSize(mixed $val) : int
    {
        return strlen(is_scalar($val) ? (string)$val : serialize($val));
    }
}
