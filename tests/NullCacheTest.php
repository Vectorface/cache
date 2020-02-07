<?php

namespace Vectorface\Tests\Cache;

use Vectorface\Cache\Cache;
use Vectorface\Cache\NullCache;

class NullCacheTest extends \PHPUnit\Framework\TestCase
{
    public function testNullCache()
    {
        $cache = new NullCache();
        $this->assertNull($cache->get('foo'));
        $this->assertEquals('dflt', $cache->get("foo", "dflt"));
        $this->assertFalse($cache->set('foo', 'bar'));
        $this->assertFalse($cache->delete('foo'));
        $this->assertFalse($cache->clean());
        $this->assertFalse($cache->flush());
    }
}
