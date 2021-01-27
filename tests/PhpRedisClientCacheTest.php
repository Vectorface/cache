<?php

namespace Vectorface\Tests\Cache;

use RedisClient\RedisClient;
use Vectorface\Cache\RedisCache;

class PhpRedisClientCacheTest extends GenericCacheTest
{
    protected function setUp(): void
    {
        // Tests using the php-redis-client library
        $redis = new RedisClient([
            'server' => '127.0.0.1:6379',
            'connection' => [
                // TODO: Making it work with PERSISTENT would be nice
                // 'flags' => STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT,
            ]
        ]);

        $this->cache = new RedisCache($redis);
    }
}
