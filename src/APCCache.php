<?php

namespace Vectorface\Cache;

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
    /**
     * The module name that defines the APC methods.
     *
     * @var string
     */
    private $apcModule = 'apcu';

    /**
     * Attempt to retrieve an entry from the cache.
     *
     * @param mixed  $default Default value to return if the key does not exist.
     * @return mixed Returns the value stored for the given key, or false on failure.
     */
    public function get($key, $default = null)
    {
        $value = $this->call('fetch', $key);
        return ($value === false) ? $default : $value;
    }

    /**
     * Place an entry in the cache, overwriting it if it already exists.
     *
     * @param string $key The cache key.
     * @param mixed $value The value to be stored.
     * @param int $ttl The time to live, in seconds.
     * @return True if successful, false otherwise.
     */
    public function set($key, $value, $ttl = false)
    {
        return $this->call('store', $key, $value, (int)$ttl);
    }

    /**
     * Remove an entry from the cache.
     *
     * @param String $key The key to be deleted (removed) from the cache.
     * @return bool True if successful, false otherwise.
     */
    public function delete($key)
    {
        return $this->call('delete', $key);
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

    private function call($call, ...$args)
    {
        $function = "{$this->apcModule}_{$call}";

        return $function(...$args);
    }
}
