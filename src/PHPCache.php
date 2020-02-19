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
class PHPCache implements Cache
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
     * @inheritDoc Vectorface\Cache\Cache
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
     * @inheritDoc Vectorface\Cache\Cache
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
     * Remove an entry from the cache.
     *
     * @param String $key The key to be deleted (removed) from the cache.
     * @return bool True if successful, false otherwise.
     */
    public function delete($key)
    {
        unset($this->cache[$this->key($key)]);
        return true;
    }

    /**
     * Manually clean out entries older than their TTL
     *
     * @return bool True if successful, false otherwise.
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
     * Clear the cache.
     *
     * @return bool True if successful, false otherwise.
     */
    public function flush()
    {
        $this->cache = [];
        return true;
    }

    /**
     * @inheritDoc \Psr\SimpleCache\CacheInterface
     */
    public function clear()
    {
        return $this->flush();
    }

    /**
     * @inheritDoc \Psr\SimpleCache\CacheInterface
     */
    public function has($key)
    {
        return $this->get($this->key($key)) !== null;
    }
}
