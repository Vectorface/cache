<?php

namespace Vectorface\Cache;

use Psr\SimpleCache\CacheException;
use Psr\SimpleCache\CacheInterface;
use Vectorface\Cache\Common\PSR16Util;
use Vectorface\Cache\Exception\InvalidArgumentException;

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
class APCCache implements Cache
{
    use PSR16Util;

    /**
     * The module name that defines the APC methods.
     *
     * @var string
     */
    private $apcModule = 'apcu';

    /**
     * @inheritDoc Vectorface\Cache\Cache
     */
    public function get($key, $default = null)
    {
        $value = $this->call('fetch', $this->key($key));
        return ($value === false) ? $default : $value;
    }

    /**
     * @inheritDoc Vectorface\Cache\Cache
     */
    public function set($key, $value, $ttl = null)
    {
        return $this->call('store', $this->key($key), $value, $this->ttl($ttl));
    }

    /**
     * Remove an entry from the cache.
     *
     * @param String $key The key to be deleted (removed) from the cache.
     * @return bool True if successful, false otherwise.
     */
    public function delete($key)
    {
        return $this->call('delete', $this->key($key));
    }

    /*
     * This is a no-op for APC, which does this on its own.
     *
     * @return bool Returns falsey null.
     */
    public function clean()
    {
        return false;
    }

    /**
     * Flush the cache; Empty it of all entries.
     *
     * @return bool True if successful, false otherwise.
     */
    public function flush()
    {
        return $this->call('clear_cache');
    }

    /**
     * @inheritDoc Psr\SimpleCache\CacheInterface
     */
    public function clear()
    {
        return $this->flush();
    }

    /**
     * @inheritDoc Psr\SimpleCache\CacheInterface
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
     * @inheritDoc Psr\SimpleCache\CacheInterface
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
     * @inheritDoc Psr\SimpleCache\CacheInterface
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
     * @inheritDoc Psr\SimpleCache\CacheInterface
     */
    public function has($key)
    {
        return $this->call('exists', $this->key($key));
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
