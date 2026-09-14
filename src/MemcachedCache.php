<?php
/** @noinspection PhpComposerExtensionStubsInspection */

namespace Vectorface\Cache;

use DateInterval;
use Memcached;
use Vectorface\Cache\Common\PSR16Util;

/**
 * Implements the cache interface on top of the Memcached extension
 *
 * @see MCCache for the equivalent using the older Memcache extension
 */
class MemcachedCache implements Cache, AtomicCounter
{
    use PSR16Util;

    /**
     * Create a new memcached-based cache.
     *
     * @param Memcached $mc The memcached instance, with servers already added.
     */
    public function __construct(
        private Memcached $mc,
    ) {}

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null) : mixed
    {
        $value = $this->mc->get($this->key($key));
        // Use the result code so falsy stored values aren't mistaken for a miss
        return ($this->mc->getResultCode() === Memcached::RES_SUCCESS) ? $value : $default;
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null) : bool
    {
        return $this->mc->set($this->key($key), $value, $this->ttl($ttl) ?? 0);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key) : bool
    {
        // PSR-16: deleting a missing key is not a failure
        return $this->mc->delete($this->key($key))
            || $this->mc->getResultCode() === Memcached::RES_NOTFOUND;
    }

    /**
     * @inheritDoc
     */
    public function clean() : bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function flush() : bool
    {
        return $this->mc->flush();
    }

    /**
     * @inheritDoc
     */
    public function clear() : bool
    {
        return $this->flush();
    }

    /**
     * @inheritDoc
     */
    public function getMultiple(iterable $keys, mixed $default = null) : iterable
    {
        $keys = $this->keys($keys);
        if (empty($keys)) {
            return [];
        }

        $values = $this->mc->getMulti($keys);
        if ($values === false) {
            $values = [];
        }

        // getMulti omits missing keys; fill them in with the default, preserving key order
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = array_key_exists($key, $values) ? $values[$key] : $default;
        }
        return $results;
    }

    /**
     * @inheritDoc
     */
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null) : bool
    {
        $values = $this->values($values);
        if (empty($values)) {
            return true;
        }

        return $this->mc->setMulti($values, $this->ttl($ttl) ?? 0);
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple(iterable $keys) : bool
    {
        $keys = $this->keys($keys);
        if (empty($keys)) {
            return true;
        }

        // deleteMulti returns true per key on success, or a result code on failure
        foreach ($this->mc->deleteMulti($keys) as $result) {
            if ($result !== true && $result !== Memcached::RES_NOTFOUND) {
                return false;
            }
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function has(string $key) : bool
    {
        $this->mc->get($this->key($key));
        return $this->mc->getResultCode() === Memcached::RES_SUCCESS;
    }

    /**
     * @inheritDoc
     */
    public function increment(string $key, int $step = 1, DateInterval|int|null $ttl = null) : int|false
    {
        $key = $this->key($key);

        // Memcached's initial value argument needs the binary protocol; add() works with either
        $this->mc->add($key, 0, $this->ttl($ttl) ?? 0);

        return $this->mc->increment($key, $step);
    }

    /**
     * @inheritDoc
     */
    public function decrement(string $key, int $step = 1, DateInterval|int|null $ttl = null) : int|false
    {
        $key = $this->key($key);

        // Memcached's initial value argument needs the binary protocol; add() works with either
        $this->mc->add($key, 0, $this->ttl($ttl) ?? 0);

        return $this->mc->decrement($key, $step);
    }
}
