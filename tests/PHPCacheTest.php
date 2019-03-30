<?php

namespace Vectorface\Tests\Cache;

use Vectorface\Cache\Cache;
use Vectorface\Cache\PHPCache;

class PHPCacheTest extends GenericCacheTest
{
    protected function setUp()
    {
        $this->cache = new PHPCache();
    }

    public function testExpired()
    {
        /* The PHP cache can support negative TTL, so exploit that. */
        $this->assertTrue($this->cache->set('foo', 'bar', -1));
        $this->assertTrue($this->cache->clean());
        $this->assertFalse($this->cache->get('foo'));

        $this->assertTrue($this->cache->set('foo', 'bar', -1));
        $this->assertFalse($this->cache->get('foo'));
    }
}
