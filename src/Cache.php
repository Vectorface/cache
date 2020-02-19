<?php

namespace Vectorface\Cache;

use DateInterval;
use Psr\SimpleCache\CacheInterface;

/**
 * Cache: A common interface to various types of caches
 * N.B. This interface would conflict with any class named Cache in the future
 * such as an ormdata generated Cache{Peer} class to match the database table.
 */
interface Cache extends CacheInterface
{
    /**
     * Fetch a cache entry by key.
     *
     * @param String $key The key for the entry to fetch
     * @param mixed  $default Default value to return if the key does not exist
     * @return mixed The value stored in the cache for $key
     */
    public function get($key, $default = null);

    /**
     * Set an entry in the cache.
     *
     * @param String $key The key/index for the cache entry
     * @param mixed $value The item to store in the cache
     * @param null|int $ttl The time to live of the cached item. Not all caches honor the TTL.
     * @return bool True if successful, false otherwise.
     */
    public function set($key, $value, $ttl = null);

    /**
     * Remove an entry from the cache.
     *
     * @param String $key The key to be deleted (removed) from the cache.
     * @return bool True if successful, false otherwise.
     */
    public function delete($key);

    /**
     * Manually clean out entries older than their TTL
     *
     * @return bool True if successful, false otherwise.
     */
    public function clean();

    /**
     * Clear the cache.
     *
     * @return bool True if successful, false otherwise.
     */
    public function flush();
}
