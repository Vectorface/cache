<?php

namespace Vectorface\Cache;

use DateInterval;
use Psr\Log\LoggerInterface;
use Throwable;
use Vectorface\Cache\Common\PSR16Util;
use Vectorface\Cache\Exception\CacheException;
use Vectorface\Cache\Exception\InvalidArgumentException;

/**
 * Class with a few methods that may assist in implementing item caching.
 */
class CacheHelper
{
    use PSR16Util;

    /**
     * Implement the mechanics of caching the result of a heavy function call.
     *
     * For example, if one has a function like so:
     * public static function getLargeDatasetFromDB($arg1, $arg2) {
     *     // Lots of SQL/compute
     *     return $giantDataSet;
     * }
     *
     * One could cache this by adding cache calls to the top/bottom. CacheHelper::fetch can automate this:
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
     * @param DateInterval|int|null $ttl If a value is to be set in the cache, set this expiry time (in seconds).
     * @return mixed The value stored in the cache, or returned by the callback.
     * @throws CacheException
     */
    public static function fetch(Cache $cache, string $key, callable $callback, array $args = [], DateInterval|int|null $ttl = 300) : mixed
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

    /**
     * Like fetch(), but with cache stampede protection.
     *
     * Protection works in three ways:
     * 1. Early refresh ("XFetch", Vattani et al., VLDB 2015): each hit has a chance of refreshing the value before it
     *    expires, rising as expiry approaches and scaled by how long the last recompute took.
     * 2. Stale-while-revalidate: the refreshing request extends the stale entry by a short grace period so concurrent
     *    requests keep serving it instead of also refreshing.
     * 3. Stale-on-error: if the callback throws, the stale value is served and the error logged. With no stale value,
     *    the exception propagates.
     *
     * Entries are stored as [value, expiry, recompute time] and kept for $ttl + $staleTtl so the stale value stays
     * available. Don't share keys between fetch() and fetchProtected().
     *
     * @param Cache $cache The cache from/to which the values should be retrieved/set.
     * @param string $key The cache key which should store the value.
     * @param callable $callback A callable which is expected to return a value to be cached.
     * @param mixed[] $args The arguments to be passed to the callback, if it needs to be called.
     * @param DateInterval|int|null $ttl Lifetime of the value, in seconds. null/0 means no expiry; behaves like fetch().
     * @param float $beta Early-refresh aggressiveness. Default 1.0; higher refreshes earlier, 0 disables early refresh.
     * @param DateInterval|int|null $staleTtl How long past expiry a stale value is kept. Defaults to $ttl.
     * @param LoggerInterface|null $logger Where to report a stale value served on error. Defaults to error_log().
     * @return mixed The value stored in the cache, or returned by the callback.
     * @throws CacheException
     * @throws Throwable Anything thrown by the callback, if there is no stale value.
     */
    public static function fetchProtected(
        Cache $cache,
        string $key,
        callable $callback,
        array $args = [],
        DateInterval|int|null $ttl = 300,
        float $beta = 1.0,
        DateInterval|int|null $staleTtl = null,
        LoggerInterface|null $logger = null,
    ) : mixed {
        if ($beta < 0) {
            throw new InvalidArgumentException("beta must be a non-negative number");
        }

        $ttl = static::ttl($ttl);
        if (!$ttl) {
            return static::fetch($cache, $key, $callback, $args, $ttl);
        }
        $storeTtl = $ttl + (static::ttl($staleTtl) ?? $ttl);

        $entry = static::getEntry($cache, $key);
        $now = static::time();
        if ($entry !== null) {
            if (!static::shouldRefresh($entry, $now, $beta)) {
                return $entry['v'];
            }
            static::extendGrace($cache, $key, $entry, $now, $ttl, $storeTtl);
        }

        try {
            $value = $callback(...$args);
        } catch (Throwable $e) {
            return static::serveStale($key, $entry, $e, $logger);
        }

        if (isset($value)) {
            $cache->set($key, ['v' => $value, 'e' => $now + $ttl, 'd' => static::time() - $now], $storeTtl);
        }
        return $value;
    }

    /**
     * Get a fetchProtected() entry from the cache, or null if there is none.
     *
     * @return array{v: mixed, e: float, d: float}|null
     */
    private static function getEntry(Cache $cache, string $key) : array|null
    {
        $entry = $cache->get($key);
        return static::isEntry($entry) ? $entry : null;
    }

    /**
     * XFetch: refresh early with a probability that rises as expiry approaches.
     *
     * @param array{v: mixed, e: float, d: float} $entry
     */
    private static function shouldRefresh(array $entry, float $now, float $beta) : bool
    {
        return $now - ($entry['d'] * $beta * log(static::random())) >= $entry['e'];
    }

    /**
     * Extend a stale entry by a short grace period so concurrent requests don't also refresh it.
     *
     * @param array{v: mixed, e: float, d: float} $entry
     */
    private static function extendGrace(Cache $cache, string $key, array $entry, float $now, int $ttl, int $storeTtl) : void
    {
        $grace = min($ttl, max(1.0, 5 * $entry['d']));
        $entry['e'] = max($entry['e'], $now + $grace);
        $cache->set($key, $entry, $storeTtl);
    }

    /**
     * Serve the stale value after a failed callback, logging the error; rethrow if there is none.
     *
     * @param array{v: mixed, e: float, d: float}|null $entry
     * @throws Throwable The callback's exception, if there is no stale value
     */
    private static function serveStale(string $key, array|null $entry, Throwable $e, LoggerInterface|null $logger) : mixed
    {
        if ($entry === null) {
            throw $e;
        }
        $message = "Serving stale cache value for '$key': " . $e->getMessage();
        $logger
            ? $logger->warning($message, ['key' => $key, 'exception' => $e])
            : error_log($message);
        return $entry['v'];
    }

    /**
     * Whether a cached item is a fetchProtected() entry
     */
    private static function isEntry(mixed $entry) : bool
    {
        return is_array($entry)
            && array_key_exists('v', $entry)
            && isset($entry['e'], $entry['d'])
            && is_numeric($entry['e'])
            && is_numeric($entry['d']);
    }

    /**
     * Current unix time with microseconds. Overridable for testing.
     */
    protected static function time() : float
    {
        return microtime(true);
    }

    /**
     * Uniform random number in (0, 1]. Overridable for testing.
     */
    protected static function random() : float
    {
        return 1.0 - random_int(0, mt_getrandmax() - 1) / mt_getrandmax();
    }
}
