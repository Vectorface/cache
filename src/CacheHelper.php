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
     */
    public static function fetch(Cache $cache, $key, $callback, $args, $ttl = 300)
    {
        if (!(is_string($key))) {
            throw new \Exception('Cache key must be a string');
        }

        $item = $cache->get($key);
        if ($item === false) {
            if (!is_array($args)) {
                $args = isset($args) ? array($args) : array();
            }
            $item = call_user_func_array($callback, $args);

            if (isset($item)) {
                $cache->set($key, $item, $ttl);
            }
        }
        return $item;
    }
}
