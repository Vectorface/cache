<?php

namespace Vectorface\Cache;

use Vectorface\Cache\Common\PSR16Util;
use Redis;
use RedisClient\RedisClient;
use Vectorface\Cache\Exception\InvalidArgumentException;

/**
 * A cache implementation using phpredis/php-redis-client
 */
class RedisCache implements Cache
{
    use PSR16Util { key as PSR16Key; }

    /**
     * @var Redis|RedisClient
     */
    private $redis;

    /**
     * @var string
     */
    private $prefix;

    private function key($key)
    {
        return $this->prefix . $this->PSR16Key($key);
    }

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
    public function get($key, $default = null)
    {
        $result = $this->redis->get($this->key($key));

        /* Not found: false in phpredis, null in php-redis-client */
        $notFoundResult = ($this->redis instanceof Redis) ? false : null;

        return ($result !== $notFoundResult) ? $result : $default;
    }

    /**
     * @inheritDoc Vectorface\Cache\Cache
     */
    public function set($key, $value, $ttl = null)
    {
        $args = [$this->key($key), $this->ttl($ttl), $value];

        /* Compatible signatures, different function case; probably shouldn't care. */
        return ($this->redis instanceof Redis) ? $this->redis->setEx(...$args) : $this->redis->setex(...$args);
    }

    /**
     * @inheritDoc Vectorface\Cache\Cache
     */
    public function delete($key)
    {
        $key = $this->key($key);

        if ($this->redis instanceof Redis) {
            throw new InvalidArgumentException("write me!");
        }

        return (bool)$this->redis->del([$key]);
    }

    /**
     * @inheritDoc Vectorface\Cache\Cache
     */
    public function clean()
    {
        return true; /* redis does this on its own */
    }

    /**
     * @inheritDoc Vectorface\Cache\Cache
     */
    public function flush()
    {
        if ($this->redis instanceof Redis) {
            throw new InvalidArgumentException("write me!");
        }

        return (bool)$this->redis->flushdb(); // We probably don't actually want to do this
    }

    /**
     * @inheritDoc \Psr\SimpleCache\CacheInterface
     */
    public function clear()
    {
        return $this->flush();
    }

    /**
     * @inheritDoc \Psr\SimpleCache\CacheInterface
     */
    public function has($key)
    {
        $key = $this->key($key);

        if ($this->redis instanceof Redis) {
            throw new InvalidArgumentException("write me!");
        }

        return (bool)$this->redis->exists($key);
    }

    /**
     * @inheritDoc \Psr\SimpleCache\CacheInterface
     */
    public function getMultiple($keys, $default = null)
    {
        throw new InvalidArgumentException("write me!");
    }

    /**
     * @inheritDoc \Psr\SimpleCache\CacheInterface
     */
    public function setMultiple($values, $ttl = null)
    {
        throw new InvalidArgumentException("write me!");
    }

    /**
     * @inheritDoc \Psr\SimpleCache\CacheInterface
     */
    public function deleteMultiple($keys)
    {
        throw new InvalidArgumentException("write me!");
    }
}
