<?php

namespace Vectorface\Cache;

use Memcache;
use Vectorface\Cache\Common\PSR16Util;

/**
 * This cache is very fast, according to basic benchmarks:
 *
 * Parameters:
 *   Memcache 1.2.2, running locally
 *   9-byte key
 *   151-byte value
 *   10000-iteration test
 *
 * Result:
 *   0.859622001648 seconds
 *
 * Conclusion:
 *   Capable of approximately 11678 requests/second
 */

/**
 * Implements the cache interface on top of Memcache
 */
class MCCache implements Cache
{
    use PSR16Util;

    /**
     * Memcache instance; Connection to the memcached server.
     *
     * @var Memcache
     */
    private $mc;

    /**
     * Create a new memcache-based cache.
     *
     * @param Memcache $mc The memcache instance, or null to try to build one.
     */
    public function __construct(Memcache $mc)
    {
        $this->mc = $mc;
    }

    /**
     * @inheritDoc Vectorface\Cache\Cache
     */
    public function get($key, $default = null)
    {
        $value = $this->mc->get($this->key($key));
        return ($value === false) ? $default : $value;
    }

    /**
    /**
     * @inheritDoc Vectorface\Cache\Cache
     */
    public function set($key, $value, $ttl = null)
    {
        return $this->mc->set($this->key($key), $value, null, $this->ttl($ttl));
    }

    /**
     * Remove an entry from the cache.
     *
     * @param String $key The key to be deleted (removed) from the cache.
     * @return bool True if successful, false otherwise.
     */
    public function delete($key)
    {
        return $this->mc->delete($this->key($key));
    }

    /**
     * Clean the cache. This does nothing for Memcache, which clears itself.
     */
    public function clean()
    {
        return true;
    }

    /**
     * Flush the cache; Clear all cache entries.
     *
     * @return bool True if successful, false otherwise.
     */
    public function flush()
    {
        return $this->mc->flush();
    }

    /**
     * @inheritDoc Psr\SimpleCache\CacheInterface
     */
    public function clear()
    {
        return $this->flush();
    }

    /**
     * @inheritDoc Psr\SimpleCache\CacheInterface
     */
    public function getMultiple($keys, $default = null)
    {
        $keys = $this->keys($keys);
        $values = $this->mc->get($keys);

        if ($values === false) {
            $values = [];
        } elseif (is_string($values)) {
            $values = [$values]; // shouldn't technically happen if $keys is an array
        }

        foreach ($keys as $key) {
            if (!isset($values[$key]) || $values[$key] === false) {
                $values[$key] = $default;
            }
        }

        return $values;
    }

    /**
     * @inheritDoc Psr\SimpleCache\CacheInterface
     */
    public function setMultiple($values, $ttl = null)
    {
        $success = true;
        foreach ($this->values($values) as $key => $value) {
            $success = $this->set($key, $value, $ttl) && $success;
        }
        return $success;
    }

    /**
     * @inheritDoc Psr\SimpleCache\CacheInterface
     */
    public function deleteMultiple($keys)
    {
        $success = true;
        foreach ($this->keys($keys) as $key) {
            $success = $this->delete($key) && $success;
        }

        return $success;
    }

    /**
     * @inheritDoc Psr\SimpleCache\CacheInterface
     */
    public function has($key)
    {
        return $this->get($this->key($key), null) !== null;
    }
}
