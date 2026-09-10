<?php

namespace Vectorface\Tests\Cache;

use Vectorface\Cache\Exception\CacheException;
use Vectorface\Cache\PHPCache;

class PHPCacheTest extends GenericCacheTest
{
    protected function setUp(): void
    {
        $this->cache = new PHPCache();
    }

    /**
     * @throws CacheException
     */
    public function testExpired()
    {
        /* The PHP cache can support negative TTL, so exploit that. */
        $this->assertTrue($this->cache->set('foo', 'bar', -1));
        $this->assertTrue($this->cache->clean());
        $this->assertNull($this->cache->get('foo'));

        $this->assertTrue($this->cache->set('foo', 'bar', -1));
        $this->assertNull($this->cache->get('foo'));
    }

    public function testIncrementKeepsExpiry()
    {
        $this->cache->increment('n', 1, 100);
        $this->cache->increment('n', 1, 100);
        $prop = new \ReflectionProperty($this->cache, 'cache');
        $prop->setAccessible(true); /* Needed on PHP 8.0 */
        $entry = $prop->getValue($this->cache)['n'];
        $this->assertEquals(2, $entry[1]);
        $this->assertEqualsWithDelta(microtime(true) + 100, $entry[0], 5);

        /* Incrementing a value set() with no TTL keeps it eternal */
        $this->cache->set('e', 5);
        $this->cache->increment('e', 1, 100);
        $entry = $prop->getValue($this->cache)['e'];
        $this->assertSame([false, 6], $entry);
    }
}
