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
 * Implements the Cache interface on top of APC or APCu.
 */
class APCCache implements Cache, AtomicCounter
{
    use PSR16Util;

    /**
     * The module name that defines the APC methods.
     */
    private string $apcModule = 'apcu';

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null) : mixed
    {
        $value = $this->call('fetch', $this->key($key));
        return ($value === false) ? $default : $value;
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null) : bool
    {
        return $this->call('store', $this->key($key), $value, $this->ttl($ttl));
    }

    /**
     * @inheritDoc
     */
    public function delete($key) : bool
    {
        return $this->call('delete', $this->key($key));
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
        return $this->call('clear_cache');
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
        return $this->defaults(
            $keys,
            $this->call('fetch', $keys),
            $default
        );
    }

    /**
     * @inheritDoc
     */
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null) : bool
    {
        $results = $this->call(
            'store',
            $this->values($values),
            null,
            $this->ttl($ttl)
        );
        return array_reduce($results, static fn($carry, $item) => $carry && $item, true);
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple(iterable $keys) : bool
    {
        $success = true;
        foreach ($this->keys($keys) as $key) {
            $success = $this->call('delete', $key) && $success;
        }

        return $success;
    }

    /**
     * @inheritDoc
     */
    public function has(string $key) : bool
    {
        return $this->call('exists', $this->key($key));
    }

    /**
     * @inheritDoc
     */
    public function increment(string $key, int $step = 1, DateInterval|int|null  $ttl = null) : int|false
    {
        return $this->call('inc', $this->key($key), $this->step($step), null, $this->ttl($ttl));
    }

    /**
     * @inheritDoc
     */
    public function decrement(string $key, int $step = 1, DateInterval|int|null $ttl = null) : int|false
    {
        return $this->call('dec', $this->key($key), $this->step($step), null, $this->ttl($ttl));
    }

    /**
     * Pass a call through to APC or APCu
     * @param string $call Transformed to a function apc(u)_$call
     * @param mixed ...$args Function arguments
     * @return mixed The result passed through from apc(u)_$call
     */
    private function call(string $call, ...$args) : mixed
    {
        $function = "{$this->apcModule}_{$call}";

        return $function(...$args);
    }
}
