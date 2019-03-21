<?php

namespace Vectorface\Tests\Cache;

use Vectorface\Cache\Cache;

abstract class GenericCacheTest extends \PHPUnit\Framework\TestCase
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
        }
    }

    /**
     * @dataProvider cacheDataProvider
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
     * @dataProvider cacheDataProvider
     */
    public function testDelete($key, $data, $ttl)
    {
        foreach ($this->getCaches() as $cache) {
            $cache->set($key, $data, $ttl);
            $cache->delete($key);
            $this->assertNull($cache->get($key));
        }
    }

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
     */
    public function testFlush($key, $data, $ttl)
    {
        foreach ($this->getCaches() as $cache) {
            $cache->set($key, $data, $ttl);
            $cache->set($key."2", $data, $ttl + 50000);
            $cache->flush();

            $this->assertNull($cache->get($key));
            $this->assertNull($cache->get($key . ".unrelated"));
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
