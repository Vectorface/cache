<?php
/** @noinspection PhpComposerExtensionStubsInspection */

namespace Vectorface\Tests\Cache;

use InvalidArgumentException;
use Redis;
use stdClass;
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

        // flush() only removes prefixed keys
        $this->assertTrue($this->cache->set('unprefixed', 'v'));
        $this->assertTrue($cache->flush());
        $this->assertFalse($cache->has('a'));
        $this->assertTrue($this->cache->has('unprefixed'));
        $this->assertTrue($this->cache->delete('unprefixed'));
    }

    public function testValueTypes()
    {
        foreach ([17, 1.5, true, false, null, ['a' => [1, 2]], (object)['x' => 1]] as $value) {
            $this->assertTrue($this->cache->set('typed', $value));
            $this->assertSame(serialize($value), serialize($this->cache->get('typed', 'dflt')));
        }
        $this->assertTrue($this->cache->delete('typed'));
    }

    public function testExpiredTtl()
    {
        $this->assertTrue($this->cache->set('exp', 'v'));
        $this->assertTrue($this->cache->set('exp', 'v', 0));
        $this->assertFalse($this->cache->has('exp'));
        $this->assertTrue($this->cache->setMultiple(['exp' => 'v', 'exp2' => 'v']));
        $this->assertTrue($this->cache->setMultiple(['exp' => 'v', 'exp2' => 'v'], -1));
        $this->assertFalse($this->cache->has('exp'));
        $this->assertFalse($this->cache->has('exp2'));
    }

    public function testInvalidSetMultipleLeavesConnectionUsable()
    {
        try {
            $this->cache->setMultiple((function () { yield 'ok' => 1; yield new stdClass() => 2; })());
            $this->fail("Expected exception");
        } catch (\Vectorface\Cache\Exception\InvalidArgumentException) {
        }
        $this->assertTrue($this->cache->set('after', 'v'));
        $this->assertSame('v', $this->cache->get('after'));
        $this->assertTrue($this->cache->delete('after'));
    }

    public function testDeleteMissing()
    {
        $this->assertTrue($this->cache->delete('missing'));
        $this->assertTrue($this->cache->deleteMultiple(['missing', 'missing2']));
        $this->assertTrue($this->cache->deleteMultiple((function () { yield from []; })()));
    }

    public function testBadConstructor()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->cache = new RedisCache(null);
    }

    public function testCounterReadBackThroughGet()
    {
        // Counters are stored raw rather than serialized, and must come back through get() as-is
        $this->assertTrue($this->cache->delete('counter'));
        $this->assertEquals(3, $this->cache->increment('counter', 3));
        $this->assertEquals(3, $this->cache->get('counter'));
        $this->assertEquals(['counter' => 3], $this->cache->getMultiple(['counter']));
        $this->assertTrue($this->cache->delete('counter'));
    }

    public function testNonStringValuesPassThroughUnpack()
    {
        // A client configured with its own serializer may hand back non-string values; they are returned untouched
        $redis = $this->createMock(Redis::class);
        $redis->method('get')->willReturn(5);
        $redis->method('mget')->willReturn([5, ['x' => 1]]);

        $cache = new RedisCache($redis);
        $this->assertSame(5, $cache->get('counter'));
        $this->assertSame(['a' => 5, 'b' => ['x' => 1]], $cache->getMultiple(['a', 'b']));
    }
}
