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

    public function testBadConstructor()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache = new RedisCache(null);
    }
}
