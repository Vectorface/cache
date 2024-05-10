<?php
/** @noinspection PhpComposerExtensionStubsInspection */

namespace Vectorface\Cache;

use DateInterval;
use Memcache;
use Vectorface\Cache\Common\PSR16Util;

/**
 * Implements the cache interface on top of Memcache
 *
 * This cache is very fast, according to basic benchmarks:
 *
 * Parameters:
 *   Memcache 1.2.2, running locally
 *   9-byte key
 *   151-byte value
 *   10000-iteration test
 *
 * Result:
 *   0.859622001648 seconds
 *
 * Conclusion:
 *   Capable of approximately 11678 requests/second
 */
class MCCache implements Cache, AtomicCounter
{
    use PSR16Util;

    /**
     * Create a new memcache-based cache.
     *
     * @param Memcache $mc The memcache instance, or null to try to build one.
     */
    public function __construct(
        private Memcache $mc,
    ) {}

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null) : mixed
    {
        $value = $this->mc->get($this->key($key));
        return ($value === false) ? $default : $value;
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null) : bool
    {
        return $this->mc->set($this->key($key), $value, 0, $this->ttl($ttl) ?? 0);
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key) : bool
    {
        return $this->mc->delete($this->key($key));
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
        $values = $this->mc->get($keys);

        if ($values === false) {
            $values = [];
        }

        foreach ($keys as $key) {
            if (!isset($values[$key]) || $values[$key] === false) {
                $values[$key] = $default;
            }
        }

        return $values;
    }

    /**
     * @inheritDoc
     */
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null) : bool
    {
        $success = true;
        foreach ($this->values($values) as $key => $value) {
            $success = $this->set($key, $value, $ttl) && $success;
        }
        return $success;
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple(iterable $keys) : bool
    {
        $success = true;
        foreach ($this->keys($keys) as $key) {
            $success = $this->delete($key) && $success;
        }

        return $success;
    }

    /**
     * @inheritDoc
     */
    public function has(string $key) : bool
    {
        return $this->get($this->key($key)) !== null;
    }

    /**
     * @inheritDoc
     */
    public function increment(string $key, int $step = 1, DateInterval|int|null $ttl = null) : int|false
    {
        $key = $this->key($key);

        // If the key already exists, this is a no-op, otherwise it ensures the key is created.
        // See https://www.php.net/manual/en/memcache.increment.php#90864
        $this->mc->add($key, 0, 0, $this->ttl($ttl) ?? 0);

        return $this->mc->increment($key, $this->step($step));
    }

    /**
     * @inheritDoc
     */
    public function decrement(string $key, int $step = 1, DateInterval|int|null $ttl = null) : int|false
    {
        $key = $this->key($key);

        // If the key already exists, this is a no-op, otherwise it ensures the key is created.
        // See https://www.php.net/manual/en/memcache.increment.php#90864
        $this->mc->add($key, 0, 0, $this->ttl($ttl) ?? 0);

        return $this->mc->decrement($key, $this->step($step));
    }
}
