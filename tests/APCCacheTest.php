<?php

namespace Vectorface\Tests\Cache;

use Vectorface\Cache\Cache;
use Vectorface\Cache\APCCache;

class APCCacheTest extends GenericCacheTest
{
    public function setUp()
    {
        $apc = extension_loaded('apc') || extension_loaded('apcu');
        if (!$apc || (PHP_SAPI == 'cli' && ini_get('apc.enable_cli') !== '1')) {
            require_once __DIR__ . '/Helpers/fakeapc.php';
        }
        $this->cache = new APCCache();

    }

    public function testModuleNotAvailable()
    {
        $cls = new \ReflectionClass('Vectorface\\Cache\\APCCache');
        $prop = $cls->getProperty('apcModule');
        $prop->setAccessible(true);
        $orig = $prop->getValue();
        $prop->setValue(null, 'invalidmodulename');
        try {
            $cache = new APCCache();
            $this->fail('The invalidated module name should prevent using this cache.');
        } catch (\Exception $e) {
        } // Expected.
        $prop->setValue(null, $orig);
    }
}
