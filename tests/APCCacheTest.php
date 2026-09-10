<?php

namespace Vectorface\Tests\Cache;

use Vectorface\Cache\APCCache;

class APCCacheTest extends GenericCacheTest
{
    protected function setUp(): void
    {
        if (!extension_loaded('apcu') || (ini_get('apc.enable_cli') !== '1')) {
            $this->markTestSkipped("APCu module not loaded, or not enabled");
        }
        $this->cache = new APCCache();
    }

    public function testCachedFalse()
    {
        $this->cache->set('f', false);
        $this->assertFalse($this->cache->get('f', 'dflt'));
        $this->assertEquals('dflt', $this->cache->get('missing', 'dflt'));
    }
}
