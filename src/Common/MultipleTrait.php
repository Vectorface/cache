<?php

namespace Vectorface\Cache\Common;

/**
 * Wraps (get|set|delete) operations with their multiple counterparts
 *
 * This is useful when the underlying cache doesn't implement multi ops
 */
trait MultipleTrait
{
    abstract protected function keys($keys);

    /**
     * @inheritDoc \Psr\SimpleCache\CacheInterface
     */
    public function getMultiple($keys, $default = null)
    {
        $values = [];
        foreach ($this->keys($keys) as $key) {
            $values[$key] = $this->get($key, $default);
        }

        return $values;
    }

    /**
     * @inheritDoc \Psr\SimpleCache\CacheInterface
     */
    public function setMultiple($values, $ttl = null)
    {
        $success = true;
        foreach ($this->values($values) as $key => $value) {
            $success = $this->set($key, $value, $ttl) && $success;
        }
        return $success;
    }

    /**
     * @inheritDoc \Psr\SimpleCache\CacheInterface
     */
    public function deleteMultiple($keys)
    {
        $success = true;
        foreach ($keys as $key) {
            $success = $this->delete($key) && $success;
        }
        return $success;
    }
}