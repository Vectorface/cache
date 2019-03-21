<?php

namespace Vectorface\Cache;

/**
 * Class with a few methods that may assist in implementing item caching.
 */
class CacheHelper
{
    /**
     * Implement the mechanics of caching the result of a heavy function call.
     *
     * For example, if one has a function like so:
     * public static function getLargeDatasetFromDB($arg1, $arg2) {
     *     // Lots of SQL/compute
     *     return $giantDataSet;
     * }
     *
     * One could cache this by adding cache calls to the top/bottom. CacheHelper::fetch can automatate this:
     *
     * function getLargeDataset($arg1, $arg2) {
     *     $key = "SomeClass::LargeDataset($arg1,$arg2)";
     *     $cache = new APCCache();
     *     return CacheHelper::fetch($cache, $key, [SomeClass, 'getLargeDatasetFromDB'], [$arg1, $arg2], 600);
     * }
     *
     * @param Cache $cache The cache from/to which the values should be retrieved/set.
     * @param string $key The cache key which should store the value.
     * @param callable $callback A callable which is expected to return a value to be cached.
     * @param mixed[] $args The arguments to be passed to the callback, if it needs to be called.
     * @param int $ttl If a value is to be set in the cache, set this expiry time (in seconds).
     * @return mixed The value stored in the cache, or returned by the callback.
     */
    public static function fetch(Cache $cache, $key, $callback, $args, $ttl = 300)
    {
        if (!(is_string($key))) {
            throw new \InvalidArgumentException('Cache key must be a string');
        }

        $item = $cache->get($key);
        if ($item === null) {
            $item = static::runCallback($callback, $args);

            if (isset($item)) {
                $cache->set($key, $item, $ttl);
            }
        }
        return $item;
    }

    /**
     * Run the callback, normalizing the arguments.
     *
     * @param callable $callback The callable to be executed to fetch the value the cache.
     * @param mixed[] $args The argument(s) to the callback function.
     * @return mixed The value returned by the callback.
     */
    protected static function runCallback($callback, $args)
    {
        if (!is_array($args)) {
            $args = isset($args) ? [$args] : [];
        }
        return call_user_func_array($callback, $args);
    }
}
