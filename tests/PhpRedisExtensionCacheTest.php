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
        $this->assertTrue($redis->exists('pfx:a') > 0);
        $this->assertTrue($cache->deleteMultiple(['a', 'b']));
    }

    public function testValueTypes()
    {
        foreach ([17, 1.5, true, false, null, ['a' => [1, 2]], (object)['x' => 1]] as $value) {
            $this->assertTrue($this->cache->set('typed', $value));
            $this->assertSame(serialize($value), serialize($this->cache->get('typed', 'dflt')));
        }
        $this->assertTrue($this->cache->delete('typed'));
    }

    public function testBadConstructor()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache = new RedisCache(null);
    }
}
