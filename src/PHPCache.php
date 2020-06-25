<?php

namespace Vectorface\Cache;

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
     * @var mixed[]
     */
    protected $cache = [];

    /**
     * @inheritDoc
     */
    public function get($key, $default = null)
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
    public function set($key, $value, $ttl = null)
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
    public function delete($key)
    {
        unset($this->cache[$this->key($key)]);
        return true;
    }

    /**
     * @inheritDoc
     */
    public function clean()
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
    public function flush()
    {
        $this->cache = [];
        return true;
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
    public function has($key)
    {
        return $this->get($this->key($key)) !== null;
    }

    /**
     * @inheritDoc
     */
    public function increment($key, $step = 1)
    {
        $key = $this->key($key);
        $newValue = $this->get($key, 0) + $this->step($step);
        $result = $this->set($key, $newValue);
        return $result !== false ? $newValue : false;
    }

    /**
     * @inheritDoc
     */
    public function decrement($key, $step = 1)
    {
        $key = $this->key($key);
        $newValue = $this->get($key, 0) - $this->step($step);
        $result = $this->set($key, $newValue);
        return $result !== false ? $newValue : false;
    }
}
