<?php

namespace Vectorface\Tests\Cache\Helpers;

/**
 * Fake some memcache functionality.
 */
class FakeMemcache extends \Memcache
{
    /**
     * An array to fake "memcache" key/value store for the duration of the script.
     *
     * @var mixed[]
     */
    public static $cache = array();

    /**
     * A flag to indicate that this class should act as if all operations fail.
     *
     * @var bool
     */
    public static $broken = false;

    /**
     * Mimic Memcache::get
     *
     * @see http://php.net/manual/en/memcache.get.php
     */
    public function get($key, &$flags = null)
    {
        if (self::$broken) {
            return false;
        }

        return isset(static::$cache[$key]) ? static::$cache[$key] : false;
    }

    /**
     * Mimic Memcache::set
     *
     * @see http://php.net/manual/en/memcache.set.php
     */
    public function set($key, $value, $flags = null, $ttl = 0)
    {
        if (self::$broken) {
            return false;
        }

        static::$cache[$key] = $value; // $ttl is ignored.
        return true;
    }

    /**
     * Mimic Memcache::flush
     *
     * @see http://php.net/manual/en/memcache.flush.php
     */
    public function flush()
    {
        static::$cache = array();
        return self::$broken ? false : true;
    }

    /**
     * Mimic Memcache::replace
     *
     * @see http://php.net/manual/en/memcache.replace.php
     */
    public function replace($key, $value, $flag = null, $expire = 0)
    {
        $old = $this->get($key);
        if ($old === false) {
            return false;
        }

        return $this->set($key, $value, $flag, $expire);
    }

    /**
     * Mimic Memcache::increment
     *
     * @see http://php.net/manual/en/memcache.increment.php
     */
    public function increment($key, $value = 1)
    {
        $old = $this->get($key);
        if ($old === false) {
            return false;
        } elseif (!is_numeric($old)) {
            $old = 0;
        }

        return $this->set($key, $old + $value) ? $this->get($key) : false;
    }

    /**
     * Mimic Memcache::decrement
     *
     * @see http://php.net/manual/en/memcache.decrement.php
     */
    public function decrement($key, $value = 1)
    {
        $this->increment($key, $value * -1);
    }

    /**
     * Mimic Memcache::delete
     *
     * @see http://php.net/manual/en/memcache.delete.php
     */
    public function delete($key)
    {
        unset(static::$cache[$key]);
        return true;
    }
}
