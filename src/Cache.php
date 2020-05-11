<?php

namespace Vectorface\Cache;

use Psr\SimpleCache\CacheInterface;

/**
 * Cache: A common interface to various types of caches
 */
interface Cache extends CacheInterface
{
    /**
     * @inheritDoc
     * @param array|\Traversable $keys A list of keys that can obtained in a single operation.
     * @return array|\Traversable A list of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
     */
    public function getMultiple($keys, $default = null);

    /**
     * @inheritDoc
     * @param array|\Traversable $values A list of keys that can obtained in a single operation.
     * @return array|\Traversable A list of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
     */
    public function setMultiple($values, $ttl = null);

    /**
     * @inheritDoc
     * @param array|\Traversable $keys A list of keys that can obtained in a single operation.
     */
    public function deleteMultiple($keys);

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
