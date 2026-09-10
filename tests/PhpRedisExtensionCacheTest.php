<?php
/** @noinspection PhpComposerExtensionStubsInspection */

namespace Vectorface\Tests\Cache;

use InvalidArgumentException;
use Redis;
use Vectorface\Cache\RedisCache;

class PhpRedisExtensionCacheTest extends GenericCacheTest
{
    protected function setUp(): void
    {
        // Tests using the phpredis extension
        $redis = new Redis();
        $redis->connect('127.0.0.1', '6379');

        $this->cache = new RedisCache($redis);
    }

    public function testPrefix()
    {
        $redis = new Redis();
        $redis->connect('127.0.0.1', '6379');
        $cache = new RedisCache($redis, 'pfx:');

        $this->assertTrue($cache->setMultiple(['a' => 1, 'b' => 2]));
        $this->assertEquals(['a' => 1, 'b' => 2, 'c' => 'dflt'], $cache->getMultiple(['a', 'b', 'c'], 'dflt'));
        $this->assertEquals(1, $redis->get('pfx:a'));
        $this->assertTrue($cache->deleteMultiple(['a', 'b']));
    }

    public function testBadConstructor()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache = new RedisCache(null);
    }
}
