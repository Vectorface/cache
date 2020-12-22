<?php

namespace Vectorface\Tests\Cache;

use Exception;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException as IInvalidArgumentException;
use stdClass;
use Vectorface\Cache\AtomicCounter;
use Vectorface\Cache\Cache;
use Vectorface\Cache\Exception\CacheException;
use Vectorface\Cache\Exception\InvalidArgumentException;
use Vectorface\Cache\SimpleCacheAdapter;

abstract class GenericCacheTest extends TestCase
{
    /**
     * The cache entry to be set by child classes.
     *
     * @var Cache
     */
    protected $cache;

    public function testClass()
    {
        foreach ($this->getCaches() as $cache) {
            $this->assertTrue($cache instanceof Cache);
            $this->assertTrue(new SimpleCacheAdapter($cache) instanceof CacheInterface);
        }
    }

    /**
     * @dataProvider cacheDataProvider
     * @param string $key
     * @param mixed $data
     * @param int $ttl
     * @throws IInvalidArgumentException|CacheException
     */
    public function testGet($key, $data, $ttl)
    {
        foreach ($this->getCaches() as $cache) {
            $cache->set($key, $data, $ttl); /* Write */
            $this->assertEquals($data, $cache->get($key));

            $cache->set($key, $data . $data, $ttl); /* Overwrite */
            $this->assertEquals($data . $data, $cache->get($key));

            $this->assertNull($cache->get($key . ".unrelated"));
        }
    }

    /**
     * @throws IInvalidArgumentException|CacheException
     */
    public function testMultipleOperations()
    {
        $values = [
            'foo' => 'bar',
            'baz' => 'quux',
        ];
        foreach ($this->getCaches() as $cache) {
            $this->assertEquals(
                ['foo' => 'dflt', 'baz' => 'dflt'],
                $cache->getMultiple(array_keys($values), 'dflt'),
                "Expected the result to be populated with default values"
            );
            $this->assertEquals([], $cache->getMultiple([]));
            $this->assertTrue($cache->setMultiple($values));
            $this->assertEquals($values, $cache->getMultiple(array_keys($values)));
            $this->assertTrue($cache->deleteMultiple(array_keys($values)));
            $this->assertTrue($cache->deleteMultiple([]));
            $this->assertEquals(
                ['foo' => 'dflt', 'baz' => 'dflt'],
                $cache->getMultiple(array_keys($values), 'dflt')
            );

            // With TTL
            $this->assertTrue($cache->setMultiple($values, 10));
            $this->assertEquals($values, $cache->getMultiple(array_keys($values)));
            $this->assertTrue($cache->deleteMultiple(array_keys($values)));
        }
    }

    /**
     * Use a generator to enforce that multiple interfaces are iterable-compatible
     *
     * @throws IInvalidArgumentException|CacheException
     */
    public function testTraversables()
    {
        foreach ($this->getCaches() as $cache) {
            $this->assertEquals(
                ['foo' => 'dflt', 'bar' => 'dflt'],
                $cache->getMultiple(
                    (function() { yield 'foo'; yield 'bar'; })(),
                    'dflt'
                ),
                "Expected the result to be populated with default values"
            );
            $this->assertTrue($cache->setMultiple(
                (function() { yield 'foo' => 'bar'; yield 'baz' => 'quux'; })()
            ));
            $this->assertEquals('bar', $cache->get('foo'));
            $this->assertEquals('quux', $cache->get('baz'));
            $this->assertTrue($cache->deleteMultiple((function() { yield 'foo'; yield 'baz'; })()));
        }
    }

    /**
     * @dataProvider cacheDataProvider
     * @param string $key
     * @param mixed $data
     * @param int $ttl
     * @throws IInvalidArgumentException|CacheException
     */
    public function testDelete($key, $data, $ttl)
    {
        foreach ($this->getCaches() as $cache) {
            $this->assertTrue($cache->set($key, $data, $ttl));
            $this->assertTrue($cache->delete($key));
            $this->assertNull($cache->get($key));
        }
    }

    /**
     * @throws IInvalidArgumentException|CacheException
     */
    public function testHas()
    {
        foreach ($this->getCaches() as $cache) {
            $this->assertFalse($cache->has(__FUNCTION__));
            $this->assertTrue($cache->set(__FUNCTION__, __METHOD__));
            $this->assertTrue($cache->has(__FUNCTION__));
        }
    }

    /**
     * @throws IInvalidArgumentException|CacheException
     */
    public function testClean()
    {
        /* Not all caches can clean, so just test that we can try and get a valid success/failure result. */
        foreach ($this->getCaches() as $cache) {
            $this->assertTrue($cache->set('foo', 'bar'));
            $this->assertTrue(is_bool($cache->clean()));
        }
    }

    /**
     * @dataProvider cacheDataProvider
     * @param string $key
     * @param mixed $data
     * @param int $ttl
     * @throws IInvalidArgumentException|CacheException
     */
    public function testFlush($key, $data, $ttl)
    {
        $this->realTestFlushAndClear($key, $data, $ttl, true);
    }

    /**
     * @dataProvider cacheDataProvider
     * @param string $key
     * @param mixed $data
     * @param int $ttl
     * @throws IInvalidArgumentException|CacheException
     */
    public function testClear($key, $data, $ttl)
    {
        $this->realTestFlushAndClear($key, $data, $ttl, false);
    }

    /**
     * @param string $key
     * @param mixed $data
     * @param int $ttl
     * @param bool $flush
     * @throws IInvalidArgumentException|CacheException
     */
    public function realTestFlushAndClear($key, $data, $ttl, $flush)
    {
        foreach ($this->getCaches() as $cache) {
            $cache->set($key, $data, $ttl);
            $cache->set($key."2", $data, $ttl + 50000);
            $flush ? $cache->flush() : $cache->clear();

            $this->assertNull($cache->get($key));
            $this->assertNull($cache->get($key . ".unrelated"));
        }
    }

    /**
     * @throws IInvalidArgumentException
     */
    public function testIncrementDecrement()
    {
        foreach ($this->getCaches() as $cache) {
            // Only test caches that support counting
            if (! $cache instanceof AtomicCounter) {
                $this->assertNotInstanceOf(AtomicCounter::class, $cache);
                continue;
            }
            $this->assertInstanceOf(AtomicCounter::class, $cache);

            // Note: The implementation should create the key if it does not exist.
            $this->assertEquals(1, $cache->increment("counter", 1), get_class($cache));
            $this->assertEquals(2, $cache->increment("counter", 1), get_class($cache));
            $this->assertEquals(7, $cache->increment("counter", 5), get_class($cache));
            $this->assertEquals(6, $cache->decrement("counter", 1), get_class($cache));
            $this->assertEquals(4, $cache->decrement("counter", 2), get_class($cache));

            // With TTL
            $this->assertTrue($cache->delete("counter"), get_class($cache));
            $this->assertEquals(3, $cache->increment("counter", 3, 10), get_class($cache));
            $this->assertEquals(1, $cache->decrement("counter", 2, 10), get_class($cache));
        }
    }

    /**
     * @noinspection PhpParamsInspection
     */
    public function testPSR16()
    {
        $expectIAE = function($callback, $message = '') {
            try {
                $callback();
                $this->fail("$message: Expected exception, but none happened");
            } catch (InvalidArgumentException $e) {
                $this->assertInstanceOf(
                    IInvalidArgumentException::class,
                    $e,
                    "$message: Expected Psr\SimpleCache\InvalidArgumentException"
                );
            }
        };

        foreach ($this->getCaches() as $cache) {
            $cache = new SimpleCacheAdapter($cache);
            $expectIAE(function() use($cache) { $cache->get(new stdClass()); }, "Invalid key in get");
            $expectIAE(function() use($cache) { $cache->set(new stdClass(), "value"); }, "Invalid key in set, exception expected");
            $expectIAE(function() use($cache) { $cache->set("key", "value", []); }, "Invalid ttl in " . get_class($cache) . " set, exception expected");
            $expectIAE(function() use($cache) { $cache->getMultiple(new Exception()); }, "Shouldn't be able to getMultiple with a non-iterable");
            $expectIAE(function() use($cache) { $cache->setMultiple(new Exception()); }, "Shouldn't be able to setMultiple on a non-iterable");
        }
    }

    public function cacheDataProvider()
    {
        return [
            [
                "testKey1",
                "testData1",
                50 * 60
            ],
            [
                "AnotherKey",
                "Here is some more data that I would like to test with",
                3000
            ],
            [
                "IntData",
                17,
                3000
            ],
        ];
    }

    protected function getCaches()
    {
        return is_array($this->cache) ? $this->cache : [$this->cache];
    }
}
