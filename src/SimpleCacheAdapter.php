<?php

namespace Vectorface\Cache;

use Psr\SimpleCache\CacheInterface;

/**
 * Adapts a Vectorface cache instance to the PSR SimpleCache interface.
 */
class SimpleCacheAdapter implements CacheInterface
{
    /**
     * Create an adapter over a Vectorface cache instance to the SimpleCache interface.
     */
    public function __construct(
        protected Cache $cache,
    ) {}

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->cache->get($key, $default);
    }

    /**
     * @inheritDoc
     * @throws Exception\CacheException
     */
    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        return $this->cache->set($key, $value, $ttl);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key): bool
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
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        return $this->cache->getMultiple($keys, $default);
    }

    /**
     * @inheritDoc
     * @throws Exception\CacheException
     */
    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        return $this->cache->setMultiple($values, $ttl);
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple(iterable $keys): bool
    {
        return $this->cache->deleteMultiple($keys);
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        return $this->cache->has($key);
    }
}
