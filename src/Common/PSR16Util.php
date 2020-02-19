<?php

namespace Vectorface\Cache\Common;

use DateInterval;
use DateTime;
use Traversable;
use Vectorface\Cache\Exception\CacheException;
use Vectorface\Cache\Exception\InvalidArgumentException as CacheArgumentException;


/**
 *
 */
trait PSR16Util
{
    /**
     * The DateTime implementation to use
     *
     * @var string
     */
    private static $dateTimeClass = DateTime::class;

    /**
     * Enforce a fairly standard key format
     *
     * @param $key
     * @return mixed Returns the key, if valid
     * @throws CacheArgumentException Thrown if the key is not a legal value
     */
    protected function key($key)
    {
        if (is_scalar($key)) {
            return $key;
        }

        throw new CacheArgumentException("key is not a legal value");
    }

    /**
     * Enforce fairly standard key formats on an iterable of values
     * @param iterable $values
     * @return iterable The values array
     * @throws CacheArgumentException Thrown if any of the keys is not a legal value
     */
    protected function values($values) {
        foreach ($values as $key => $value) {
            $key = $this->key($key); // Checks the key
        }
        return $values;
    }

    /**
     * Enforce a fairly standard key format on an array or Traversable of keys
     *
     * @param iterable $keys
     * @return mixed Returns the key, if valid
     * @throws CacheArgumentException Thrown if any of the keys is not a legal value
     */
    protected function keys($keys)
    {
        if (!is_array($keys) && !($keys instanceof Traversable)) {
            throw new CacheArgumentException("keys must be provided as an array or a Traversable");
        }

        foreach ($keys as &$key) {
            $key = $this->key($key);
        }
        return $keys;
    }

    /**
     * Add defaults to an array of values from a cache
     *
     * Note: This does NOT check the keys array
     *
     * @param array|iterable $keys An array of expected keys
     * @param array $values An array of values pulled from the cache
     * @param mixed $default The default value to be populated for missing entries
     * @return array The values array, with defaults added
     */
    protected static function defaults($keys, $values, $default)
    {
        foreach ($keys as $key) {
            if (!isset($values[$key])) {
                $values[$key] = $default;
            }
        }
        return $values;
    }

    /**
     * Convert a PSR-16 compatible TTL argument to a standard integer TTL as used by most caches
     *
     * @param null|int|DateInterval $ttl
     * @return int
     */
    public static function ttl($ttl)
    {
        if (is_numeric($ttl) || $ttl === null) {
            return $ttl;
        } elseif ($ttl instanceof DateInterval) {
            return static::intervalToTTL($ttl);
        }

        throw new CacheArgumentException("TTL must be specified as a number, a DateInterval, or null");
    }

    /**
     * Convert a DateInterval to a time diff in seconds
     * @param DateInterval $interval
     * @return int The number of seconds from now until $interval
     */
    public static function intervalToTTL(DateInterval $interval)
    {
        $dateClass = static::$dateTimeClass;

        try {
            $now = new $dateClass();
            $exp = (new $dateClass())->add($interval);
        } catch (\Exception $e) {
            throw new CacheException("Could not get current DateTime");
        }

        return $exp->getTimestamp() - $now->getTimestamp();
    }
}