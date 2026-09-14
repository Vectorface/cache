<?php

namespace Vectorface\Tests\Cache;

use Psr\SimpleCache\InvalidArgumentException;
use Vectorface\Cache\MemcachedCache;
use Vectorface\Tests\Cache\Helpers\FakeMemcached;
use Vectorface\Tests\Cache\Helpers\Memcached;

class MemcachedCacheTest extends GenericCacheTest
{
    private FakeMemcached $memcached;

    protected function setUp(): void
    {
        if (!class_exists("Memcached", false)) {
            /** @noinspection PhpIgnoredClassAliasDeclaration */
            class_alias(Memcached::class, "Memcached");
        }
        $this->memcached = new FakeMemcached();
        $this->cache = new MemcachedCache($this->memcached);
    }

    protected function tearDown(): void
    {
        $this->memcached->broken = false;
        $this->memcached->flush();
    }

    /**
     * @throws InvalidArgumentException
     */
    public function testGetMultipleWithBrokenCache()
    {
        $this->memcached->broken = true;
        $this->assertEquals([
            'foo' => 'baz',
            'bar' => 'baz',
        ], $this->cache->getMultiple(["foo", "bar"], "baz"));
    }

    public function testSetMultipleEmpty()
    {
        // Memcached::setMulti rejects an empty array, so the cache must short-circuit
        $this->assertTrue($this->cache->setMultiple([]));
        $this->assertTrue($this->cache->setMultiple((function () { yield from []; })()));
    }

    public function testGetMultiple()
    {
        $this->assertTrue($this->cache->setMultiple(["foo" => "foo", "bar" => "bar"]));
        $this->assertEquals(["foo" => "foo"], $this->cache->getMultiple(["foo"]));
        $this->assertEquals(["foo" => "foo", "nope" => null], $this->cache->getMultiple(["foo", "nope"]));
    }

    public function testFalsyValues()
    {
        foreach ([false, 0, '', null, []] as $value) {
            $this->assertTrue($this->cache->set("falsy", $value));
            $this->assertTrue($this->cache->has("falsy"));
            $this->assertSame($value, $this->cache->get("falsy", "default"));
        }
    }

    public function testDeleteMissing()
    {
        $this->assertTrue($this->cache->delete("missing"));
        $this->assertTrue($this->cache->deleteMultiple(["missing", "also-missing"]));

        $this->memcached->broken = true;
        $this->assertFalse($this->cache->delete("missing"));
        $this->assertFalse($this->cache->deleteMultiple(["missing"]));
    }
}
