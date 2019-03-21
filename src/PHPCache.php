<?php

namespace Vectorface\Cache;

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
    /**
     * The "cache" which stores entries for the lifetime of the request.
     *
     * Each entry is an [expiry, value] pair, where expiry is a timestamp.
     *
     * @var mixed[]
     */
    protected $cache = [];

    /**
     * Fetch a cache entry by key.
     *
     * @param String $key The key for the entry to fetch
     * @param mixed $default Default value to return if the key does not exist.
     * @return mixed The value stored in the cache for $key
     */
    public function get($key, $default = null)
    {
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
     * Set an entry in the cache.
     *
     * @param String $key The key/index for the cache entry
     * @param mixed $value The item to store in the cache
     * @param int $ttl The time to live (or expiry) of the cached item. Not all caches honor the TTL.
     * @return bool True if successful, false otherwise.
     */
    public function set($key, $value, $ttl = false)
    {
        /* Cache gets a microtime expiry date. */
        $this->cache[$key] = [
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
        unset($this->cache[$key]);
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
            list($expires, $value) = $value;
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
}
