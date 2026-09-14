<?php

namespace Vectorface\Tests\Cache;

use RedisClient\RedisClient;
use Vectorface\Cache\RedisCache;

class PhpRedisClientCacheTest extends GenericCacheTest
{
    protected function setUp(): void
    {
        $this->cache = new RedisCache($this->createClient());
    }

    public function testPrefix()
    {
        $redis = $this->createClient();
        $cache = new RedisCache($redis, 'pfx:');

        $this->assertTrue($cache->setMultiple(['a' => 1, 'b' => 2]));
        $this->assertEquals(['a' => 1, 'b' => 2, 'c' => 'dflt'], $cache->getMultiple(['a', 'b', 'c'], 'dflt'));
        $this->assertTrue($redis->exists('pfx:a') > 0);

        // flush() only removes prefixed keys, scanning with a cursor until the server reports it is done
        $this->assertTrue($this->cache->set('unprefixed', 'v'));
        $this->assertTrue($cache->flush());
        $this->assertFalse($cache->has('a'));
        $this->assertFalse($cache->has('b'));
        $this->assertTrue($this->cache->has('unprefixed'));
        $this->assertTrue($this->cache->delete('unprefixed'));
    }

    public function testPrefixFlushWithNoMatchingKeys()
    {
        $cache = new RedisCache($this->createClient(), 'empty-pfx:');
        $this->assertTrue($cache->flush());
    }

    private function createClient() : RedisClient
    {
        // Tests using the php-redis-client library
        return new RedisClient([
            'server' => '127.0.0.1:6379',
            'connection' => [
                // TODO: Making it work with PERSISTENT would be nice
                // 'flags' => STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT,
            ]
        ]);
    }
}
