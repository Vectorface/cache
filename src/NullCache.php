<?php

namespace Vectorface\Cache;

use DateInterval;

/**
 * A cache that caches nothing and always fails.
 */
class NullCache implements Cache, AtomicCounter
{
    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null) : mixed
    {
        return $default;
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null) : bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key) : bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function clean() : bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function flush() : bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function clear() : bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function getMultiple(iterable $keys, mixed $default = null) : iterable
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
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null) : bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple(iterable $keys) : bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function has(string $key) : bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function increment(string $key, int $step = 1, DateInterval|int|null $ttl = null) : int|false
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function decrement(string $key, int $step = 1, DateInterval|int|null $ttl = null) : int|false
    {
        return false;
    }
}
