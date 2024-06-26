<?php
/** @noinspection PhpComposerExtensionStubsInspection */

namespace Vectorface\Cache;

use DateInterval;
use Vectorface\Cache\Common\PSR16Util;
use Redis;
use RedisClient\RedisClient;
use Vectorface\Cache\Exception\InvalidArgumentException;

/**
 * A cache implementation using one of two client implementations:
 *
 * @see https://github.com/cheprasov/php-redis-client
 * @see https://github.com/phpredis/phpredis
 */
class RedisCache implements Cache, AtomicCounter
{
    use PSR16Util { key as PSR16Key; }

    /** @var Redis|RedisClient */
    private $redis;

    private string $prefix;

    /**
     * RedisCache constructor.
     *
     * @param $redis
     * @param string $prefix
     */
    public function __construct($redis, string $prefix = '')
    {
        if (!($redis instanceof Redis || $redis instanceof RedisClient)) {
            throw new InvalidArgumentException("Unsupported Redis implementation");
        }

        $this->redis = $redis;
        $this->prefix = $prefix;
    }

    /**
     * @inheritDoc Vectorface\Cache\Cache
     */
    public function get(string $key, mixed $default = null) : mixed
    {
        $result = $this->redis->get($this->key($key));

        // Not found is 'false' in phpredis, 'null' in php-redis-client
        $notFoundResult = ($this->redis instanceof Redis) ? false : null;

        return ($result !== $notFoundResult) ? $result : $default;
    }

    /**
     * @inheritDoc Vectorface\Cache\Cache
     */
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null) : bool
    {
        $ttl = $this->ttl($ttl);

        // The setex function doesn't support null TTL, so we use set instead
        if ($ttl === null) {
            return $this->redis->set($this->key($key), $value);
        }

        return $this->redis->setex($this->key($key), $ttl, $value);
    }

    /**
     * @inheritDoc Vectorface\Cache\Cache
     */
    public function delete(string $key) : bool
    {
        return (bool)$this->redis->del($this->key($key));
    }

    /**
     * @inheritDoc Vectorface\Cache\Cache
     */
    public function clean() : bool
    {
        return true; /* redis does this on its own */
    }

    /**
     * @inheritDoc Vectorface\Cache\Cache
     */
    public function flush() : bool
    {
        if ($this->redis instanceof Redis) {
            return (bool)$this->redis->flushDB();
        }

        return (bool)$this->redis->flushdb(); // We probably don't actually want to do this
    }

    /**
     * @inheritDoc Vectorface\Cache\Cache
     */
    public function clear() : bool
    {
        return $this->flush();
    }

    /**
     * @inheritDoc Vectorface\Cache\Cache
     */
    public function has(string $key) : bool
    {
        return (bool)$this->redis->exists($this->key($key));
    }

    /**
     * @inheritDoc Vectorface\Cache\Cache
     */
    public function getMultiple(iterable $keys, mixed $default = null) : iterable
    {
        $keys = $this->keys($keys);

        // Some redis client impls don't work with empty args, so return early.
        if (empty($keys)) {
            return [];
        }

        $values = $this->redis->mget($keys);
        // var_dump("Keys: " . json_encode($keys));
        // var_dump("Values: " . json_encode($values));

        $results = [];
        foreach ($keys as $index => $key) {
            if (!isset($values[$index]) || $values[$index] === false) {
                $results[$key] = $default;
            } else {
                $results[$key] = $values[$index];
            }
        }
        // var_dump("Results: " . json_encode($results));
        // echo "\n\n";

        return $results;
    }

    /**
     * @inheritDoc Vectorface\Cache\Cache
     */
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null) : bool
    {
        $ttl = $this->ttl($ttl);

        // We can't use mset because there's no msetex for expiry,
        // so we use multi-exec instead.
        $this->redis->multi();

        foreach ($this->values($values) as $key => $value) {
            // Null or TTLs under 1 aren't supported, so we need to just use set in that case.
            if ($ttl === null || $ttl < 1) {
                $this->redis->set($key, $value);
            } else {
                $this->redis->setex($key, $ttl, $value);
            }
        }

        $results = $this->redis->exec();

        foreach ($results as $result) {
            if ($result === false) {
                // @codeCoverageIgnoreStart
                return false;
                // @codeCoverageIgnoreEnd
            }
        }

        return true;
    }

    /**
     * @inheritDoc Vectorface\Cache\Cache
     */
    public function deleteMultiple(iterable $keys) : bool
    {
        if (empty($keys)) {
            return true;
        }

        return (bool)$this->redis->del($this->keys($keys));
    }

    /**
     * @inheritdoc AtomicCounter
     */
    public function increment(string $key, int $step = 1, DateInterval|int|null $ttl = null) : int|false
    {
        return $this->atomicCounter('incrby', $key, $step, $ttl);
    }

    /**
     * @inheritdoc AtomicCounter
     */
    public function decrement(string $key, int $step = 1, DateInterval|int|null $ttl = null) : int|false
    {
        return $this->atomicCounter('decrby', $key, $step, $ttl);
    }

    private function atomicCounter(string $method, string $key, int $step = 1, DateInterval|int|null $ttl = null) : int|false
    {
        $ttl = $this->ttl($ttl);
        $key = $this->key($key);
        $step = $this->step($step);

        // We can't just use incrby/decrby because it doesn't support expiry,
        // so we use multi-exec instead.
        $this->redis->multi();

        // Set only if the key does not exist (safely sets expiry only if doesn't exist).
        // The two redis clients have different advanced set APIs for this.
        // They also don't support null or TTLs under 1, so we need to just use setnx in that case.
        if ($ttl === null || $ttl < 1) {
            $this->redis->setnx($key, 0);
        } else {
            if ($this->redis instanceof Redis) {
                $this->redis->set($key, 0, ['NX', 'EX' => $ttl]);
            } else {
                $this->redis->set($key, 0, $ttl, null, 'NX');
            }
        }

        $this->redis->{$method}($key, $step);

        $result = $this->redis->exec();

        // Since we ran two commands, the 1 index should be the incrby/decrby result
        return $result[1] ?? false;
    }

    /**
     * Override of {@see PSR16Util::key} to allow for having a cache prefix
     *
     * @param mixed $key
     * @return string
     */
    private function key(mixed $key) : string
    {
        return $this->prefix . $this->PSR16Key($key);
    }
}
