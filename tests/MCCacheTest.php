<?php

namespace Vectorface\Tests\Cache;

use Vectorface\Cache\MCCache;
use Vectorface\Tests\Cache\Helpers\FakeMemcache;

class MCCacheTest extends GenericCacheTest
{
    protected function setUp()
    {
        if (!class_exists("Memcache", false)) {
            $this->markTestSkipped("Please ensure that memcache.so is installed and configured");
        }
        $this->cache = new MCCache(new FakeMemcache());
    }
}
