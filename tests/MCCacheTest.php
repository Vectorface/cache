<?php

namespace Vectorface\Tests\Cache;

use Vectorface\Cache\MCCache;
use Vectorface\Tests\Cache\Helpers\FakeMemcache;

class MCCacheTest extends GenericCacheTest
{
    protected function setUp()
    {
        if (!class_exists("Memcache", false)) {
            class_alias("Vectorface\Tests\Cache\Helpers\Memcache", "Memcache");
        }
        $this->cache = new MCCache(new FakeMemcache());
    }
}
