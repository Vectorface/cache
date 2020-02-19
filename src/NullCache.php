<?php

namespace Vectorface\Cache;

use Vectorface\Cache\Common\PSR16Util;

/**
 * A cache that caches nothing and always fails.
 */
class NullCache implements Cache
{
    /**
     * @inheritDoc Vectorface\Cache\Cache
     */
    public function get($key, $default = null)
    {
        return $default;
    }

    /**
     * @inheritDoc Vectorface\Cache\Cache
     */
    public function set($key, $value, $ttl = null)
    {
        return false;
    }

    /**
     * @inheritDoc Vectorface\Cache\Cache
     */
    public function delete($key)
    {
        return false;
    }

    /**
     * @inheritDoc Vectorface\Cache\Cache
     */
    public function clean()
    {
        return false;
    }

    /**
     * @inheritDoc Vectorface\Cache\Cache
     */
    public function flush()
    {
        return false;
    }

    /**
     * @inheritDoc Psr\SimpleCache\CacheInterface
     */
    public function clear()
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function getMultiple($keys, $default = null)
    {
        return array_combine($keys, array_fill(0, count($keys), $default));
    }

    /**
     * @inheritDoc
     */
    public function setMultiple($values, $ttl = null)
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple($keys)
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function has($key)
    {
        return false;
    }
}
