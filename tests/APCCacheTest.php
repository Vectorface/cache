<?php

namespace Vectorface\Tests\Cache;

use Vectorface\Cache\Cache;
use Vectorface\Cache\APCCache;

class APCCacheTest extends GenericCacheTest
{
    public function setUp()
    {
        if (!extension_loaded('apcu') || (ini_get('apc.enable_cli') !== '1')) {
            $this->markTestSkipped("APCu module not loaded, or not enabled");
        }
        $this->cache = new APCCache();
    }

    public function testModuleNotAvailable()
    {
        $cls = new \ReflectionClass('Vectorface\\Cache\\APCCache');
        $prop = $cls->getProperty('apcModule');
        $prop->setAccessible(true);
        try {
            $cache = new APCCache();
            $orig = $prop->getValue($cache);
            $prop->setValue($cache, 'invalidmodulename');
            $this->fail('The invalidated module name should prevent using this cache.');
        } catch (\Exception $e) {
        } // Expected.
        $prop->setValue($cache, $orig);
    }
}
