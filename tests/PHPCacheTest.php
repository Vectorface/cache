<?php

namespace Vectorface\Tests\Cache;

use Vectorface\Cache\Exception\CacheException;
use Vectorface\Cache\PHPCache;

class PHPCacheTest extends GenericCacheTest
{
    protected function setUp()
    {
        $this->cache = new PHPCache();
    }

    /**
     * @throws CacheException
     */
    public function testExpired()
    {
        /* The PHP cache can support negative TTL, so exploit that. */
        $this->assertTrue($this->cache->set('foo', 'bar', -1));
        $this->assertTrue($this->cache->clean());
        $this->assertNull($this->cache->get('foo'));

        $this->assertTrue($this->cache->set('foo', 'bar', -1));
        $this->assertNull($this->cache->get('foo'));
    }
}
