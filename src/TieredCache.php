<?php

namespace Vectorface\Cache;

/**
 * This cache's speed is dependent on underlying caches, usually medium according to basic benchmarks:
 *
 * Parameters:
 *   MCCache + SQLCache
 *   9-byte key
 *   151-byte value
 *   10000-iteration test
 *
 * Result:
 *   11.9338240623 seconds
 *
 * Conclusion:
 *   Capable of approximately 837.96 requests/second
 */

/**
 * A cache composed of other caches layered on top of one another.
 */
class TieredCache implements Cache
{
    /**
     * The cache layers.
     *
     * @var Cache[]
     */
    private $caches = [];

    /**
     * Create a cache that layers caches on top of each other.
     *
     * Read requests hit caches in order until they get a hit. The first hit is returned.
     * Write operations hit caches in order, performing the write operation on all caches.
     *
     * @param Cache[] $caches An array of objects implementing the Cache interface.
     *
     * Note: Order is important. The first element is get/set first, and so on. Usually that means  you want to put the
     * fastest caches first.
     */
    public function __construct($caches = null)
    {
        if (!is_array($caches)) {
            $caches = func_get_args();
        }
        foreach ($caches as $i => $cache) {
            if (!($cache instanceof Cache)) {
                throw new \InvalidArgumentException("Argument $i is not of class Cache");
            }
            $this->caches[] = $cache;
        }
    }

    /**
     * Get an entry from the first cache that can provide a value.
     *
     * @param string $entry The cache key.
     * @param mixed $default Default value to return if the key does not exist.
     * @return mixed The cached value, or FALSE if not found.
     */
    public function get($entry, $default = null)
    {
        foreach ($this->caches as $cache) {
            $value = $cache->get($entry);
            if ($value) {
                return $value;
            }
        }
        return $default;
    }

    /**
     * Set an entry in all caches in the stack.
     *
     * @param string $entry The cache key.
     * @param mixed $value The value to be stored.
     * @param int|false $ttl The time-to-live for the cache entry.
     * @return bool Returns true if saving succeeded to any cache in the stack.
     */
    public function set($entry, $value, $ttl = false)
    {
        $setCallback = function($setSuccess, $cache) use ($entry, $value, $ttl) {
            return $setSuccess || $cache->set($entry, $value, $ttl);
        };
        return array_reduce($this->caches, $setCallback, false);
    }

    /**
     * Remove an entry from the cache.
     *
     * @param String $key The key to be deleted (removed) from the cache.
     * @return bool True if successful on all caches in the stack, false otherwise.
     */
    public function delete($key)
    {
        $deleteCallback = function($allDeleted, $cache) use ($key) {
            return $allDeleted && $cache->delete($key);
        };
        return array_reduce($this->caches, $deleteCallback, true);
    }

    /**
     * Perform a clean operation on all caches.
     *
     * @return bool Returns true if clean succeeded on all caches in the stack.
     */
    public function clean()
    {
        $cleanCallback = function($allCleaned, $cache) {
            return $allCleaned && $cache->clean();
        };
        return array_reduce($this->caches, $cleanCallback, true);
    }

    /**
     * Perform a flush operation on all caches.
     *
     * @return bool Returns true if flush succeeded on all caches in the stack.
     */
    public function flush()
    {
        $flushCallback = function($allFlushed, $cache) {
            return $allFlushed && $cache->flush();
        };
        return array_reduce($this->caches, $flushCallback, true);
    }
}
