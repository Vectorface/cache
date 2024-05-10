<?php

namespace Vectorface\Cache;

use DateInterval;
use Vectorface\Cache\Exception\CacheException;
use Vectorface\Cache\Exception\InvalidArgumentException;

/**
 * Cache: A common interface to caches that support atomic counters
 */
interface AtomicCounter
{
    /**
     * Increment the value stored under the given cache key
     *
     * @param string $key The unique cache key of the item to increment.
     * @param int $step Increment the key by this amount, defaults to 1.
     * @param DateInterval|int|null $ttl Optional. The TTL value of this item. If no value is sent and
     *                                   the driver supports TTL then the library may set a default value
     *                                   for it or let the driver take care of that.
     *
     * @return int|false The new numeric value, or false on failure.
     *
     * @throws InvalidArgumentException|CacheException
     *   MUST be thrown if the $key string is not a legal value.
     *   or if the $step amount is not a legal value.
     */
    public function increment(string $key, int $step = 1, DateInterval|int|null $ttl = null) : int|false;

    /**
     * Decrement the value stored under the given cache key
     *
     * @param string $key The unique cache key of the item to decrement.
     * @param int $step Decrement the key by this amount, defaults to 1.
     * @param null|int|DateInterval $ttl Optional. The TTL value of this item.If no value is sent and
     *                                   the driver supports TTL then the library may set a default value
     *                                   for it or let the driver take care of that.
     *
     * @return int|false The new numeric value, or false on failure.
     *
     * @throws InvalidArgumentException|CacheException
     *   MUST be thrown if the $key string is not a legal value
     *   or if the $step amount is not a legal value.
     */
    public function decrement(string $key, int $step = 1, DateInterval|int|null $ttl = null) : int|false;
}
