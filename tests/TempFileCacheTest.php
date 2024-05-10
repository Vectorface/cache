<?php

namespace Vectorface\Tests\Cache;

use Exception;
use Psr\SimpleCache\InvalidArgumentException;
use Vectorface\Cache\Cache;
use Vectorface\Cache\Exception\CacheException;
use Vectorface\Tests\Cache\Helpers\FakeRealpath;
use Vectorface\Cache\TempFileCache;

class TempFileCacheTest extends GenericCacheTest
{
    /** @var TempFileCache */
    protected Cache $cache;

    protected function setUp(): void
    {
        $this->cache = new TempFileCache();
    }

    protected function tearDown(): void
    {
        $this->cache->destroy();
    }

    /**
     * @throws CacheException
     * @noinspection PhpUsageOfSilenceOperatorInspection
     */
    public function testBadThings()
    {
        /* Corrupt a cache file. */
        $this->cache->flush();
        $this->cache->set('foo', 'bar');
        $files = glob(sys_get_temp_dir() . "/TempFileCache/*.tempcache");
        $this->assertNotEmpty($files);
        file_put_contents(current($files), "***this is not unserializable!***");
        $this->assertNull($this->cache->get('foo')); // corrupted.

        /* Remove the cache directory entirely. */
        $this->cache->flush();
        if (!@rmdir(sys_get_temp_dir() . "/TempFileCache/")) {
            $this->markTestSkipped("Unable to remove cache dir. Test can't continue.");
        }

        $this->assertFalse($this->cache->clean());
        $this->assertFalse($this->cache->flush());

        /* Try all sorts of wacky stuff in the constructor */
        foreach (['/', '/etc/passwd', '/foo/bar/baz/thisdirshouldntexist'] as $dir) {
            try {
                new TempFileCache($dir);
                $this->fail('TempFileCache should not have been able to initialize');
            } catch (Exception $e) {
            } // Expected
        }
    }

    /**
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function testAlternateDirectory()
    {
        $other = "fooBarBaz";
        $cache = new TempFileCache($other);
        $this->assertTrue(is_dir(sys_get_temp_dir() . '/' . $other));
        $this->assertTrue($cache->set('foo', 'bar'));
        $this->assertEquals('bar', $cache->get('foo'));

        $cache2 = new TempFileCache($other);
        $this->assertEquals('bar', $cache2->get('foo'));

        $this->assertTrue($cache->destroy());
    }

    /**
     * @throws CacheException
     */
    public function testExpiry()
    {
        $this->assertTrue($this->cache->set('foo', 'bar', 1));
        $this->assertEquals('bar', $this->cache->get('foo'));

        $this->assertTrue($this->cache->set('foo', 'bar', -1));
        $this->assertNull($this->cache->get('foo'));
    }

    public function testBrokenRealpath()
    {
        FakeRealpath::$broken = true;
        try {
            new TempFileCache();
            $this->fail("Expected realpath exception to be thrown");
        } catch (Exception $e) {
            $this->assertTrue($e instanceof Exception);
        }
        FakeRealpath::$broken = false;
    }
}
