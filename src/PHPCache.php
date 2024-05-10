<?php

namespace Vectorface\Cache;

use DateInterval;
use Vectorface\Cache\Common\PSR16Util;
use Vectorface\Cache\Common\MultipleTrait;

/**
 * A cache implementation using an internal PHP associative array.
 *
 * This cache is very fast, but volatile: cache is only maintained while the PHP interpreter is running.
 * Usually, this means one HTTP request.
 *
 * Capable of a huge number of requests/second
 */
class PHPCache implements Cache, AtomicCounter
{
    use MultipleTrait, PSR16Util;

    /**
     * The "cache" which stores entries for the lifetime of the request.
     *
     * Each entry is an [expiry, value] pair, where expiry is a timestamp.
     *
     * @var array{int, mixed}[]
     */
    protected array $cache = [];

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null) : mixed
    {
        $key = $this->key($key);
        if (isset($this->cache[$key])) {
            list($expires, $value) = $this->cache[$key];
            if (!$expires || ($expires >= microtime(true))) {
                return $value;
            }
            unset($this->cache[$key]);
        }
        return $default;
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null) : bool
    {
        /* Cache gets a microtime expiry date. */
        $ttl = $this->ttl($ttl);
        $this->cache[$this->key($key)] = [
            $ttl ? ((int)$ttl + microtime(true)) : false,
            $value
        ];
        return true;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key) : bool
    {
        unset($this->cache[$this->key($key)]);
        return true;
    }

    /**
     * @inheritDoc
     */
    public function clean() : bool
    {
        foreach ($this->cache as $key => $value) {
            list($expires) = $value;
            if ($expires && ($expires < microtime(true))) {
                unset($this->cache[$key]);
            }
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function flush() : bool
    {
        $this->cache = [];
        return true;
    }

    /**
     * @inheritDoc
     */
    public function clear() : bool
    {
        return $this->flush();
    }

    /**
     * @inheritDoc
     */
    public function has(string $key) : bool
    {
        return $this->get($this->key($key)) !== null;
    }

    /**
     * @inheritDoc
     */
    public function increment(string $key, int $step = 1, DateInterval|int|null $ttl = null) : int|false
    {
        $key = $this->key($key);
        $exists = $this->has($key);
        $newValue = $this->get($key, 0) + $this->step($step);
        $this->set($key, $newValue, (!$exists ? $ttl : null));
        return $newValue;
    }

    /**
     * @inheritDoc
     */
    public function decrement(string $key, int $step = 1, DateInterval|int|null $ttl = null) : int|false
    {
        return $this->increment($key, -$step, $ttl);
    }
}
