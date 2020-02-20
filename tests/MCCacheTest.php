<?php

namespace Vectorface\Tests\Cache;

use Vectorface\Cache\MCCache;
use Vectorface\Tests\Cache\Helpers\FakeMemcache;

class MCCacheTest extends GenericCacheTest
{
    private $memcache;

    protected function setUp()
    {
        if (!class_exists("Memcache", false)) {
            class_alias("Vectorface\Tests\Cache\Helpers\Memcache", "Memcache");
        }
        $this->memcache = new FakeMemcache();
        $this->cache = new MCCache($this->memcache);
    }

    public function testGetMultipleWithBrokenCache()
    {
        $this->memcache->broken = true;
        $this->assertEquals([
            'foo' => 'baz',
            'bar' => 'baz',
        ], $this->cache->getMultiple(["foo", "bar"], "baz"));
        $this->memcache->broken = false;
    }
}
