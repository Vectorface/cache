<?php

namespace Vectorface\Cache;

use Memcache;

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
     * Retrieve a cache entry by key
     *
     * @param string $key The cache key.
     * @param mixed  $default Default value to return if the key does not exist.
     * @return mixed The value stored for the given key, or false on failure.
     */
    public function get($key, $default = null)
    {
        $return = $this->mc->get($key);
        return ($return === false) ? $default : $return;
    }

    /**
     * Place an item into the cache
     *
     * @param string $key The cache key.
     * @param mixed $value The value to be stored. (Warning: Caches cannot store items of type resource.)
     * @param int $ttl The time to live, in seconds. The time before the object should expire.
     * @return bool True if successful, false otherwise.
     */
    public function set($key, $value, $ttl = false)
    {
        return $this->mc->set($key, $value, null, (int)$ttl);
    }

    /**
     * Remove an entry from the cache.
     *
     * @param String $key The key to be deleted (removed) from the cache.
     * @return bool True if successful, false otherwise.
     */
    public function delete($key)
    {
        return $this->mc->delete($key);
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
}
