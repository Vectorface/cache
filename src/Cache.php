<?php

namespace Vectorface\Cache;

use DateInterval;
use Psr\SimpleCache\CacheInterface;

/**
 * Cache: A common interface to various types of caches
 */
interface Cache extends CacheInterface
{
    /**
     * Obtains multiple cache items by their unique keys.
     *
     * Note:
     *   This docblock has been copied from CacheInterface, but changes the
     *   iterable param type to \iterable for tools that interpret iterable as
     *   a namespaced type: Psr\SimpleCache\iterable
     *
     * @param array|\Traversable $keys A list of keys that can obtained in a single operation.
     * @param mixed             $default Default value to return for keys that do not exist.
     * @return array|\Traversable A list of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function getMultiple($keys, $default = null);

    /**
     * Manually clean out entries older than their TTL
     *
     * @return bool True if successful, false otherwise.
     */
    public function clean();

    /**
     * Clear the cache. Equivalent to CacheInterface::clean()
     *
     * @return bool True if successful, false otherwise.
     */
    public function flush();
}
