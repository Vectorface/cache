<?php

namespace Vectorface\Cache;

/**
 * A cache that caches nothing and always fails.
 */
class NullCache implements Cache, AtomicCounter
{
    /**
     * @inheritDoc
     */
    public function get($key, $default = null)
    {
        return $default;
    }

    /**
     * @inheritDoc
     */
    public function set($key, $value, $ttl = null)
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function delete($key)
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function clean()
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function flush()
    {
        return false;
    }

    /**
     * @inheritDoc
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
        $defaults = [];
        foreach ($keys as $key) {
            $defaults[$key] = $default;
        }
        return $defaults;
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

    /**
     * @inheritDoc
     */
    public function increment($key, $step = 1)
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function decrement($key, $step = 1)
    {
        return false;
    }
}
