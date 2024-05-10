<?php

namespace Vectorface\Tests\Cache;

use Psr\SimpleCache\InvalidArgumentException;
use Vectorface\Cache\Exception\CacheException;
use PHPUnit\Framework\TestCase;
use Vectorface\Cache\PHPCache;
use Vectorface\Cache\SimpleCacheAdapter;

class SimpleCacheAdapterTest extends TestCase
{
    /**
     * @throws CacheException|InvalidArgumentException
     */
    public function testSimpleCacheAdapter()
    {
        $cache = new SimpleCacheAdapter(new PHPCache());
        $this->assertNull($cache->get('foo'));
        $this->assertEquals('dflt', $cache->get("foo", "dflt"));
        $this->assertTrue($cache->set('foo', 'bar'));
        $this->assertTrue($cache->delete('foo'));
        $this->assertTrue($cache->clear());
        $this->assertEquals(['foo' => 'dflt', 'bar' => 'dflt'], $cache->getMultiple(['foo', 'bar'], 'dflt'));
        $this->assertTrue($cache->setMultiple(['foo' => 'bar']));
        $this->assertTrue($cache->deleteMultiple(['foo', 'bar']));
        $this->assertFalse($cache->has('foo'));
    }
}
