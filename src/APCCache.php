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
    private static $apcModule = 'apc';

    /**
     * Create an instance of the APC cache.
     */
    public function __construct()
    {
        if (!function_exists('apc_fetch') && !extension_loaded(self::$apcModule)) {
            throw new \Exception('Unable to initialize APCCache: APC extension not loaded.');
        }
    }

    /**
     * Attempt to retrieve an entry from the cache.
     *
     * @return mixed Returns the value stored for the given key, or false on failure.
     */
    public function get($key)
    {
        return apc_fetch($key);
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
        return apc_store($key, $value, (int)$ttl);
    }

    /**
     * Remove an entry from the cache.
     *
     * @param String $key The key to be deleted (removed) from the cache.
     * @return bool True if successful, false otherwise.
     */
    public function delete($key)
    {
        return apc_delete($key);
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
        /* PHP 5.5+ uses "APCu", which takes no argument. 5.4 and under need to clear the "user" cache. */
        return (PHP_VERSION_ID >= 50500)? apc_clear_cache() : apc_clear_cache('user');
    }
}
