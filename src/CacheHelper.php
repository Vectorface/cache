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
     * @throws Exception\CacheException
     */
    public static function fetch(Cache $cache, string $key, callable $callback, array $args = [], $ttl = 300)
    {
        $item = $cache->get($key);
        if ($item === null) {
            $item = $callback(...$args);

            if (isset($item)) {
                $cache->set($key, $item, $ttl);
            }
        }
        return $item;
    }
}
