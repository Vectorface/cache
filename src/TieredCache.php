<?php

namespace Vectorface\Cache;

use InvalidArgumentException;
use Vectorface\Cache\Common\PSR16Util;

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
    use PSR16Util;

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
                throw new InvalidArgumentException("Argument $i is not of class Cache");
            }
            $this->caches[] = $cache;
        }
    }

    /**
     * @inheritDoc
     */
    public function get($key, $default = null)
    {
        $key = $this->key($key);
        foreach ($this->caches as $cache) {
            $value = $cache->get($key, null);
            if ($value !== null) {
                return $value;
            }
        }
        return $default;
    }

    /**
     * @inheritDoc
     */
    public function set($key, $value, $ttl = null)
    {
        return $this->any('set', $this->key($key), $value, $this->ttl($ttl));
    }

    /**
     * @inheritDoc
     */
    public function delete($key)
    {
        return $this->all('delete', $this->key($key));
    }

    /**
     * @inheritDoc
     */
    public function clean()
    {
        return $this->all('clean');
    }

    /**
     * @inheritDoc
     */
    public function flush()
    {
        return $this->all('flush');
    }

    /**
     * @inheritDoc
     */
    public function getMultiple($keys, $default = null)
    {
        $neededKeys = $keys;
        $values = [];
        foreach ($this->caches as $cache) {
            $result = $cache->getMultiple($neededKeys, null);
            $values = array_merge(
                $values,
                array_filter(is_array($result) ? $result : iterator_to_array($result, true))
            );
            if (count($values) === count($keys)) {
                return $values;
            }

            $neededKeys = array_diff($keys, $values);
        }

        /* Finally, set defaults */
        foreach ($keys as $key) {
            if (!isset($values[$key])) {
                $values[$key] = $default;
            }
        }

        return $values;
    }

    /**
     * @inheritDoc
     */
    public function setMultiple($values, $ttl = null)
    {
        return $this->any('setMultiple', $this->values($values), $this->ttl($ttl));
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple($keys)
    {
        return $this->all('deleteMultiple', $this->keys($keys));
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
     * Run a method on all caches, expect all caches to success for success
     *
     * @param string $call The cache interface method to be called
     * @param mixed ...$args The method's arguments
     * @return bool True if the operation was successful on all caches
     */
    private function all(string $call, ...$args)
    {
        $success = true;
        foreach ($this->caches as $cache) {
            $result = ([$cache, $call])(...$args);
            $success = $success && $result;
        }
        return $success;
    }

    /**
     * Run a method on all caches, expect any successful result for success
     *
     * @param string $call The cache interface method to be called
     * @param mixed ...$args The method's arguments
     * @return bool True if the operation was successful on any cache
     */
    private function any($call, ...$args)
    {
        $success = false;
        foreach ($this->caches as $cache) {
            $result = ([$cache, $call])(...$args);
            $success = $success || $result;
        }
        return $success;
    }
}
