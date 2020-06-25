<?php

namespace Vectorface\Tests\Cache;

use Vectorface\Cache\NullCache;
use PHPUnit\Framework\TestCase;

class NullCacheTest extends TestCase
{
    /**
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function testNullCache()
    {
        $cache = new NullCache();
        $this->assertNull($cache->get('foo'));
        $this->assertEquals('dflt', $cache->get("foo", "dflt"));
        $this->assertFalse($cache->set('foo', 'bar'));
        $this->assertFalse($cache->delete('foo'));
        $this->assertFalse($cache->clean());
        $this->assertFalse($cache->flush());
        $this->assertFalse($cache->clear());
        $this->assertEquals(['foo' => 'dflt', 'bar' => 'dflt'], $cache->getMultiple(['foo', 'bar'], 'dflt'));
        $this->assertFalse($cache->setMultiple(['foo' => 'bar']));
        $this->assertFalse($cache->deleteMultiple(['foo', 'bar']));
        $this->assertFalse($cache->has('foo'));
        $this->assertFalse($cache->increment('foo'));
        $this->assertFalse($cache->decrement('foo'));
    }
}
