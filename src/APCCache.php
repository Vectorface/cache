<?php

namespace Vectorface\Cache;

use DateInterval;
use Vectorface\Cache\Common\PSR16Util;

/**
 * This cache is ridiculously fast, according to basic benchmarks:
 *
 * Parameters:
 *   APC 3.0.19
 *   9-byte key
 *   151-byte value
 *   10000-iteration test
 *
 * Result:
 *   0.065223 seconds
 *
 * Conclusion:
 *   Capable of approximately 150000 requests/second
 */

/**
 * Implements the Cache interface on top of APCu.
 */
class APCCache implements Cache, AtomicCounter
{
    use PSR16Util;

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null) : mixed
    {
        $value = apcu_fetch($this->key($key));
        return ($value === false) ? $default : $value;
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null) : bool
    {
        return apcu_store($this->key($key), $value, $this->ttl($ttl) ?? 0);
    }

    /**
     * @inheritDoc
     */
    public function delete($key) : bool
    {
        return apcu_delete($this->key($key));
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
        return apcu_clear_cache();
    }

    /**
     * @inheritDoc
     */
    public function clear() : bool
    {
        return $this->flush();
    }

    /**
     * @inheritDoc
     */
    public function getMultiple(iterable $keys, mixed $default = null) : iterable
    {
        $keys = $this->keys($keys);
        return $this->defaults($keys, apcu_fetch($keys), $default);
    }

    /**
     * @inheritDoc
     */
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null) : bool
    {
        // Storing an array returns the list of keys that failed; empty means success
        $failed = apcu_store($this->values($values), null, $this->ttl($ttl) ?? 0);
        return empty($failed);
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple(iterable $keys) : bool
    {
        $success = true;
        foreach ($this->keys($keys) as $key) {
            $success = apcu_delete($key) && $success;
        }

        return $success;
    }

    /**
     * @inheritDoc
     */
    public function has(string $key) : bool
    {
        return apcu_exists($this->key($key));
    }

    /**
     * @inheritDoc
     */
    public function increment(string $key, int $step = 1, DateInterval|int|null $ttl = null) : int|false
    {
        return apcu_inc($this->key($key), $this->step($step), $success, $this->ttl($ttl) ?? 0);
    }

    /**
     * @inheritDoc
     */
    public function decrement(string $key, int $step = 1, DateInterval|int|null $ttl = null) : int|false
    {
        return apcu_dec($this->key($key), $this->step($step), $success, $this->ttl($ttl) ?? 0);
    }
}
