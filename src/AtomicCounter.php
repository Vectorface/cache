<?php

namespace Vectorface\Cache;

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
     *
     * @return int|false The new numeric value, or false on failure.
     *
     * @throws InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     *   or if the $step amount is not a legal value.
     */
    public function increment($key, $step = 1);

    /**
     * Decrement the value stored under the given cache key
     *
     * @param string $key The unique cache key of the item to decrement.
     * @param int $step Decrement the key by this amount, defaults to 1.
     *
     * @return int|false The new numeric value, or false on failure.
     *
     * @throws InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value
     *   or if the $step amount is not a legal value.
     */
    public function decrement($key, $step = 1);
}
