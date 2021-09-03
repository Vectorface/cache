<?php

namespace Vectorface\Tests\Cache;

use Psr\SimpleCache\InvalidArgumentException;
use Vectorface\Cache\MCCache;
use Vectorface\Tests\Cache\Helpers\FakeMemcache;
use Vectorface\Tests\Cache\Helpers\Memcache;

class MCCacheTest extends GenericCacheTest
{
    private $memcache;

    protected function setUp(): void
    {
        if (!class_exists("Memcache", false)) {
            /** @noinspection PhpIgnoredClassAliasDeclaration */
            class_alias(Memcache::class, "Memcache");
        }
        $this->memcache = new FakeMemcache();
        $this->cache = new MCCache($this->memcache);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testGetMultipleWithBrokenCache()
    {
        $this->memcache->broken = true;
        $this->assertEquals([
            'foo' => 'baz',
            'bar' => 'baz',
        ], $this->cache->getMultiple(["foo", "bar"], "baz"));
        $this->memcache->broken = false;
    }

    public function testGetMultiple()
    {
        $this->assertEquals(true, $this->cache->setMultiple(["foo" => "foo", "bar" => "bar"]));
        $this->assertEquals(["foo" => "foo"], $this->cache->getMultiple(["foo"]));
        $this->cache->flush();
    }
}
