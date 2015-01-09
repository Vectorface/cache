<?php

namespace Vectorface\Tests\Cache;

use Vectorface\Cache\Cache;
use Vectorface\Cache\NullCache;

class NullCacheTest extends \PHPUnit_Framework_TestCase
{
    public function testNullCache()
    {
        $cache = new NullCache();
        $this->assertFalse($cache->get('foo'));
        $this->assertFalse($cache->set('foo', 'bar'));
        $this->assertFalse($cache->delete('foo'));
        $this->assertFalse($cache->clean());
        $this->assertFalse($cache->flush());
    }
}
