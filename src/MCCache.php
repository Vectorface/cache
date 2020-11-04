<?php
/** @noinspection PhpComposerExtensionStubsInspection */

namespace Vectorface\Cache;

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
     * Memcache instance; Connection to the memcached server.
     *
     * @var Memcache
     */
    private $mc;

    /**
     * Create a new memcache-based cache.
     *
     * @param Memcache $mc The memcache instance, or null to try to build one.
     */
    public function __construct(Memcache $mc)
    {
        $this->mc = $mc;
    }

    /**
     * @inheritDoc
     */
    public function get($key, $default = null)
    {
        $value = $this->mc->get($this->key($key));
        return ($value === false) ? $default : $value;
    }

    /**
     * @inheritDoc
     */
    public function set($key, $value, $ttl = null)
    {
        return $this->mc->set($this->key($key), $value, null, $this->ttl($ttl));
    }

    /**
     * @inheritDoc
     */
    public function delete($key)
    {
        return $this->mc->delete($this->key($key));
    }

    /**
     * @inheritDoc
     */
    public function clean()
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function flush()
    {
        return $this->mc->flush();
    }

    /**
     * @inheritDoc
     */
    public function clear()
    {
        return $this->flush();
    }

    /**
     * @inheritDoc
     */
    public function getMultiple($keys, $default = null)
    {
        $keys = $this->keys($keys);
        $values = $this->mc->get($keys);

        if ($values === false) {
            $values = [];
        } elseif (is_string($values)) {
            $values = [$values]; // shouldn't technically happen if $keys is an array
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
    public function setMultiple($values, $ttl = null)
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
    public function deleteMultiple($keys)
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
    public function has($key)
    {
        return $this->get($this->key($key), null) !== null;
    }

    /**
     * @inheritDoc
     */
    public function increment($key, $step = 1, $ttl = null)
    {
        $key = $this->key($key);

        // If the key already exists, this is a no-op, otherwise it ensures the key is created.
        // See https://www.php.net/manual/en/memcache.increment.php#90864
        $this->mc->add($key, 0, null, $this->ttl($ttl));

        return $this->mc->increment($key, $this->step($step));
    }

    /**
     * @inheritDoc
     */
    public function decrement($key, $step = 1, $ttl = null)
    {
        $key = $this->key($key);

        // If the key already exists, this is a no-op, otherwise it ensures the key is created.
        // See https://www.php.net/manual/en/memcache.increment.php#90864
        $this->mc->add($key, 0, null, $this->ttl($ttl));

        return $this->mc->decrement($key, $this->step($step));
    }
}
