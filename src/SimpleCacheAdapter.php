<?php

namespace Vectorface\Cache;

use Psr\SimpleCache\CacheInterface;
use Traversable;

/**
 * Adapts a Vectorface cache instance to the PSR SimpleCache interface.
 */
class SimpleCacheAdapter implements CacheInterface
{
    /**
     * Create an adapter over a Vectorface cache instance to the SimpleCache interface.
     *
     * @param Cache $cache
     */
    public function __construct(
        protected Cache $cache,
    ) {}

    /**
     * @inheritDoc
     */
    public function get($key, $default = null)
    {
        return $this->cache->get($key, $default);
    }

    /**
     * @inheritDoc
     * @throws Exception\CacheException
     */
    public function set($key, $value, $ttl = null) : bool
    {
        return $this->cache->set($key, $value, $ttl);
    }

    /**
     * @inheritDoc
     */
    public function delete($key) : bool
    {
        return $this->cache->delete($key);
    }

    /**
     * @inheritDoc
     */
    public function clear() : bool
    {
        return $this->cache->clear();
    }

    /**
     * @inheritDoc
     * @param array|Traversable $keys
     */
    public function getMultiple($keys, $default = null) : iterable
    {
        return $this->cache->getMultiple($keys, $default);
    }

    /**
     * @inheritDoc
     * @param array|Traversable $values
     * @throws Exception\CacheException
     */
    public function setMultiple($values, $ttl = null) : bool
    {
        return $this->cache->setMultiple($values, $ttl);
    }

    /**
     * @inheritDoc
     * @param array|Traversable $keys
     */
    public function deleteMultiple($keys) : bool
    {
        return $this->cache->deleteMultiple($keys);
    }

    /**
     * @inheritDoc
     */
    public function has($key) : bool
    {
        return $this->cache->has($key);
    }
}
