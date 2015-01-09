<?php

namespace Vectorface\Cache;

/**
 * A cache that caches nothing and always fails.
 */
class NullCache implements Cache
{
    /**
     * Fetch a cache entry by key.
     *
     * @param String $key The key for the entry to fetch
     * @return mixed The value stored in the cache for $key, or false on failure.
     */
    public function get($key)
    {
        return false;
    }

    /**
     * Set an entry in the cache.
     *
     * @param String $key The key/index for the cache entry
     * @param mixed $value The item to store in the cache
     * @param int $ttl The time to live (or expiry) of the cached item. Not all caches honor the TTL.
     * @return bool True if successful, false otherwise.
     */
    public function set($entry, $value, $ttl = false)
    {
        return false;
    }

    /**
     * Remove an entry from the cache.
     *
     * @param String $key The key to be deleted (removed) from the cache.
     * @return bool True if successful, false otherwise.
     */
    public function delete($key)
    {
        return false;
    }

    /**
     * Manually clean out entries older than their TTL
     *
     * @return bool True if successful, false otherwise.
     */
    public function clean()
    {
        return false;
    }

    /**
     * Clear the cache.
     *
     * @return bool True if successful, false otherwise.
     */
    public function flush()
    {
        return false;
    }
}
