<?php

namespace Vectorface\Cache\Common;

/**
 * Wraps (get|set|delete) operations with their multiple counterparts
 *
 * This is useful when the underlying cache doesn't implement multi ops
 */
trait MultipleTrait
{
    abstract public function get($key, $default);
    abstract public function set($key, $value, $ttl = null);
    abstract public function delete($key);
    abstract protected function keys($keys);
    abstract protected function values($values);

    /**
     * @inheritDoc
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
     * @inheritDoc
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
     * @inheritDoc
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
