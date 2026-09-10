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

        return ($result !== $notFoundResult) ? $this->unpack($result) : $default;
    }

    /**
     * @inheritDoc Vectorface\Cache\Cache
     */
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null) : bool
    {
        $ttl = $this->ttl($ttl);

        // The setex function doesn't support null TTL, so we use set instead
        if ($ttl === null) {
            return $this->redis->set($this->key($key), serialize($value));
        }

        // PSR-16: a TTL of zero or less means the item is already expired
        if ($ttl < 1) {
            return $this->delete($key);
        }

        return $this->redis->setex($this->key($key), $ttl, serialize($value));
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
        // Unprefixed keys for the result array; prefixed keys for the lookup
        $keys = is_array($keys) ? array_values($keys) : iterator_to_array($keys, false);
        $keys = array_map([$this, 'PSR16Key'], $keys);

        // Some redis client impls don't work with empty args, so return early.
        if (empty($keys)) {
            return [];
        }

        $values = $this->redis->mget(array_map([$this, 'key'], $keys));

        $results = [];
        foreach ($keys as $index => $key) {
            if (!isset($values[$index]) || $values[$index] === false) {
                $results[$key] = $default;
            } else {
                $results[$key] = $this->unpack($values[$index]);
            }
        }

        return $results;
    }

    /**
     * @inheritDoc Vectorface\Cache\Cache
     */
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null) : bool
    {
        $ttl = $this->ttl($ttl);
        $values = $this->values($values); // Validate before multi() so a failure can't leave it open

        // We can't use mset because there's no msetex for expiry,
        // so we use multi-exec instead.
        $this->redis->multi();

        foreach ($values as $key => $value) {
            if ($ttl === null) {
                $this->redis->set($key, serialize($value));
            } elseif ($ttl < 1) {
                $this->redis->del($key); // PSR-16: already expired
            } else {
                $this->redis->setex($key, $ttl, serialize($value));
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
     * Unserialize a stored value; raw (unserialized) values such as counters are returned as-is
     */
    private function unpack(mixed $raw) : mixed
    {
        if (!is_string($raw)) {
            return $raw;
        }

        $value = @unserialize($raw);
        return ($value === false && $raw !== serialize(false)) ? $raw : $value;
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
