<?php

namespace Vectorface\Cache\Common;

use DateInterval;

/**
 * Wraps (get|set|delete) operations with their multiple counterparts
 *
 * This is useful when the underlying cache doesn't implement multi ops
 */
trait MultipleTrait
{
    abstract public function get(string $key, mixed $default);
    abstract public function set(string $key, mixed $value, DateInterval|int|null $ttl = null);
    abstract public function delete(string $key);
    abstract protected function keys(iterable $keys);
    abstract protected function values(iterable $values);

    /**
     * @inheritDoc
     */
    public function getMultiple(iterable $keys, mixed $default = null) : iterable
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
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null) : bool
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
    public function deleteMultiple($keys) : bool
    {
        $success = true;
        foreach ($keys as $key) {
            $success = $this->delete($key) && $success;
        }
        return $success;
    }
}
