<?php

namespace Vectorface\Tests\Cache;

use Exception;
use ReflectionClass;
use ReflectionException;
use Vectorface\Cache\APCCache;

class APCCacheTest extends GenericCacheTest
{
    protected function setUp()
    {
        if (!extension_loaded('apcu') || (ini_get('apc.enable_cli') !== '1')) {
            $this->markTestSkipped("APCu module not loaded, or not enabled");
        }
        $this->cache = new APCCache();
    }

    /**
     * @throws ReflectionException
     */
    public function testModuleNotAvailable()
    {
        $cls = new ReflectionClass(APCCache::class);
        $prop = $cls->getProperty('apcModule');
        $prop->setAccessible(true);
        try {
            $cache = new APCCache();
            $orig = $prop->getValue($cache);
            $prop->setValue($cache, 'invalidmodulename');
            $this->fail('The invalidated module name should prevent using this cache.');
        } catch (Exception $e) {
            // Expected.
        }

        /** @noinspection PhpUndefinedVariableInspection */
        $prop->setValue($cache, $orig);
    }
}
