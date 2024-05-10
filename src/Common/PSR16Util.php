<?php

namespace Vectorface\Cache\Common;

use DateInterval;
use DateTime;
use Exception;
use Traversable;
use Vectorface\Cache\Exception\CacheException;
use Vectorface\Cache\Exception\InvalidArgumentException as CacheArgumentException;

/**
 * Utility methods common to many PSR-16 cache implementations
 */
trait PSR16Util
{
    /**
     * The DateTime implementation to use
     */
    private static string $dateTimeClass = DateTime::class;

    /**
     * Enforce a fairly standard key format
     *
     * @throws CacheArgumentException Thrown if the key is not a legal value
     */
    protected function key(mixed $key) : string
    {
        if (is_numeric($key) || is_string($key)) {
            return (string)$key;
        }

        throw new CacheArgumentException("key is not a legal value");
    }

    /**
     * Enforce fairly standard key formats on an iterable of values
     *
     * @throws CacheArgumentException Thrown if any of the keys is not a legal value
     */
    protected function values(iterable $values) : iterable
    {
        if (!is_array($values) && !($values instanceof Traversable)) {
            throw new CacheArgumentException("values must be provided as an array or a Traversable");
        }

        $array = [];
        foreach ($values as $key => $value) {
            $array[$this->key($key)] = $value;
        }
        return $array;
    }

    /**
     * Enforce a fairly standard key format on an array or Traversable of keys
     *
     * @throws CacheArgumentException Thrown if any of the keys is not a legal value
     */
    protected function keys(iterable $keys) : array
    {
        if (!is_array($keys) && !($keys instanceof Traversable)) {
            throw new CacheArgumentException("keys must be provided as an array or a Traversable");
        }

        $array = [];
        foreach ($keys as $key) {
            $array[] = $this->key($key);
        }
        return $array;
    }

    /**
     * Enforce a valid step value for increment/decrement methods
     *
     * @throws CacheArgumentException Thrown if the step is not a legal value
     */
    protected function step(mixed $step) : int
    {
        if (!is_integer($step)) {
            throw new CacheArgumentException("step must be an integer");
        }

        return $step;
    }

    /**
     * Add defaults to an array of values from a cache
     *
     * Note: This does NOT check the keys array
     *
     * @param iterable|array $keys An array of expected keys
     * @param array $values An array of values pulled from the cache
     * @param mixed $default The default value to be populated for missing entries
     * @return array The values array, with defaults added
     */
    protected static function defaults(iterable $keys, array $values, mixed $default) : array
    {
        foreach ($keys as $key) {
            $values[$key] ??= $default;
        }
        return $values;
    }

    /**
     * Convert a PSR-16 compatible TTL argument to a standard integer TTL as used by most caches
     *
     * @throws CacheException Throws if the argument is not a valid TTL
     */
    public static function ttl(mixed $ttl) : int|null
    {
        if ($ttl instanceof DateInterval) {
            return static::intervalToTTL($ttl);
        }
        if (is_numeric($ttl) || $ttl === null) {
            return $ttl;
        }

        throw new CacheArgumentException("TTL must be specified as a number, a DateInterval, or null");
    }

    /**
     * Convert a DateInterval to a time diff in seconds
     *
     * @throws CacheException
     */
    public static function intervalToTTL(DateInterval $interval) : int
    {
        $dateClass = self::$dateTimeClass;

        try {
            $now = new $dateClass();
            $exp = (new $dateClass())->add($interval);
        } catch (Exception) {
            throw new CacheException("Could not get current DateTime");
        }

        return $exp->getTimestamp() - $now->getTimestamp();
    }
}
