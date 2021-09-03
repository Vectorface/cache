<?php

namespace Vectorface\Cache;

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
     *
     * @var string
     */
    private $apcModule = 'apcu';

    /**
     * @inheritDoc
     */
    public function get($key, $default = null)
    {
        $value = $this->call('fetch', $this->key($key));
        return ($value === false) ? $default : $value;
    }

    /**
     * @inheritDoc
     */
    public function set($key, $value, $ttl = null)
    {
        return $this->call('store', $this->key($key), $value, $this->ttl($ttl));
    }

    /**
     * @inheritDoc
     */
    public function delete($key)
    {
        return $this->call('delete', $this->key($key));
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
        return $this->call('clear_cache');
    }

    /**
     * @inheritDoc
     */
    public function clear()
    {
        return $this->flush();
    }

    /**
     * @inheritDoc
     */
    public function getMultiple($keys, $default = null)
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
    public function setMultiple($values, $ttl = null)
    {
        $results = $this->call(
            'store',
            $this->values($values),
            null,
            $this->ttl($ttl)
        );
        return array_reduce($results, function($carry, $item) { return $carry && $item; }, true);
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple($keys)
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
    public function has($key)
    {
        return $this->call('exists', $this->key($key));
    }

    /**
     * @inheritDoc
     */
    public function increment($key, $step = 1, $ttl = null)
    {
        return $this->call('inc', $this->key($key), $this->step($step), null, $this->ttl($ttl));
    }

    /**
     * @inheritDoc
     */
    public function decrement($key, $step = 1, $ttl = null)
    {
        return $this->call('dec', $this->key($key), $this->step($step), null, $this->ttl($ttl));
    }

    /**
     * Pass a call through to APC or APCu
     * @param string $call Transformed to a function apc(u)_$call
     * @param mixed ...$args Function arguments
     * @return mixed The result passed through from apc(u)_$call
     */
    private function call($call, ...$args)
    {
        $function = "{$this->apcModule}_{$call}";

        return $function(...$args);
    }
}
