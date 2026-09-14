<?php
/** @noinspection PhpComposerExtensionStubsInspection */

namespace Vectorface\Tests\Cache\Helpers;

/**
 * Fake some memcached functionality.
 */
class FakeMemcached extends \Memcached
{
    /**
     * An array to fake "memcached" key/value store for the duration of the script.
     *
     * @var mixed[]
     */
    public static array $cache = [];

    /**
     * A flag to indicate that this class should act as if all operations fail.
     */
    public bool $broken = false;

    private int $resultCode = self::RES_SUCCESS;

    public function getResultCode(): int
    {
        return $this->resultCode;
    }

    public function get(string $key, ?callable $cache_cb = null, int $get_flags = 0): mixed
    {
        if ($this->broken) {
            $this->resultCode = self::RES_FAILURE;
            return false;
        }

        if (!array_key_exists($key, static::$cache)) {
            $this->resultCode = self::RES_NOTFOUND;
            return false;
        }

        $this->resultCode = self::RES_SUCCESS;
        return static::$cache[$key];
    }

    public function getMulti(array $keys, int $get_flags = 0): array|false
    {
        if ($this->broken) {
            $this->resultCode = self::RES_FAILURE;
            return false;
        }

        $values = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, static::$cache)) {
                $values[$key] = static::$cache[$key];
            }
        }
        $this->resultCode = self::RES_SUCCESS;
        return $values;
    }

    public function set(string $key, mixed $value, int $expiration = 0): bool
    {
        if ($this->broken) {
            $this->resultCode = self::RES_FAILURE;
            return false;
        }

        static::$cache[$key] = $value; // $expiration is ignored.
        $this->resultCode = self::RES_SUCCESS;
        return true;
    }

    public function setMulti(array $items, int $expiration = 0): bool
    {
        foreach ($items as $key => $value) {
            if (!$this->set($key, $value, $expiration)) {
                return false;
            }
        }
        return true;
    }

    public function add(string $key, mixed $value, int $expiration = 0): bool
    {
        if ($this->broken) {
            $this->resultCode = self::RES_FAILURE;
            return false;
        }

        if (array_key_exists($key, static::$cache)) {
            $this->resultCode = self::RES_NOTSTORED;
            return false;
        }

        return $this->set($key, $value, $expiration);
    }

    public function delete(string $key, int $time = 0): bool
    {
        if ($this->broken) {
            $this->resultCode = self::RES_FAILURE;
            return false;
        }

        if (!array_key_exists($key, static::$cache)) {
            $this->resultCode = self::RES_NOTFOUND;
            return false;
        }

        unset(static::$cache[$key]);
        $this->resultCode = self::RES_SUCCESS;
        return true;
    }

    public function deleteMulti(array $keys, int $time = 0): array
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->delete($key, $time) ?: $this->resultCode;
        }
        return $results;
    }

    public function increment(string $key, int $offset = 1, int $initial_value = 0, int $expiry = 0): int|false
    {
        if ($this->broken) {
            $this->resultCode = self::RES_FAILURE;
            return false;
        }

        $old = $this->get($key);
        if ($old === false) {
            return false;
        }

        // Real memcached counters are unsigned and floor at zero
        $new = max(0, (is_numeric($old) ? (int)$old : 0) + $offset);
        return $this->set($key, $new) ? $new : false;
    }

    public function decrement(string $key, int $offset = 1, int $initial_value = 0, int $expiry = 0): int|false
    {
        return $this->increment($key, -$offset);
    }

    public function flush(int $delay = 0): bool
    {
        if ($this->broken) {
            $this->resultCode = self::RES_FAILURE;
            return false;
        }

        static::$cache = [];
        $this->resultCode = self::RES_SUCCESS;
        return true;
    }
}
