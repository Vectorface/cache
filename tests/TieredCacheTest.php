<?php

namespace Vectorface\Tests\Cache;

use InvalidArgumentException;
use Vectorface\Cache\Exception\CacheException;
use Vectorface\Cache\NullCache;
use Vectorface\Cache\PHPCache;
use Vectorface\Cache\TieredCache;
use PHPUnit\Framework\TestCase;

class TieredCacheTest extends TestCase
{
    /**
     * @throws CacheException
     */
    public function testTieredCache()
    {
        $null = new NullCache();
        $php = new PHPCache();
        $tiered = new TieredCache($null, $php);

        $this->assertNull($tiered->get('foo')); // Default is null
        $this->assertFalse($tiered->has('foo'));
        $this->assertTrue($tiered->set('foo', 'bar'));
        $this->assertTrue($tiered->has('foo'));
        $this->assertEquals('bar', $tiered->get('foo'));
        $this->assertEquals(['foo' => 'bar'], $tiered->getMultiple(['foo']));
        $this->assertEquals(['foo' => 'bar', 'baz' => null], $tiered->getMultiple(['foo', 'baz']));
        $this->assertFalse($tiered->delete('foo')); // one op failed, so all fail.
        $this->assertFalse($tiered->clean()); // one op failed, so all fail.
        $this->assertFalse($tiered->flush()); // one op failed, so all fail.
        $this->assertFalse($tiered->clear()); // same as flush

        $this->assertTrue($tiered->setMultiple(['foo' => 'bar', 'baz' => 'quux']));
        $this->assertEquals(['foo' => 'bar', 'baz' => 'quux'], $tiered->getMultiple(['foo', 'baz']));
        $this->assertFalse($tiered->deleteMultiple(['foo', 'baz'])); // one op failed, so all fail.
    }

    /**
     * @throws CacheException
     */
    public function testTieredSet()
    {
        $php1 = new PHPCache();
        $php2 = new PHPCache();
        $tiered = new TieredCache($php1, $php2);

        $this->assertNull($tiered->get('foo'));
        $this->assertTrue($tiered->set('foo', 'bar'));
        $this->assertEquals('bar', $tiered->get('foo'));
        $this->assertEquals('bar', $php1->get('foo'));
        $this->assertEquals('bar', $php2->get('foo'));
        $this->assertTrue($tiered->flush());
        $this->assertNull($tiered->get('foo'));

        $this->assertTrue($tiered->setMultiple(['foo' => 'bar', 'baz' => 'quux']));
        $this->assertEquals(['foo' => 'bar', 'baz' => 'quux'], $tiered->getMultiple(['foo', 'baz']));
        $this->assertEquals('quux', $php1->get('baz'));
        $this->assertEquals('bar', $php2->get('foo'));
    }

    public function testBadArg()
    {
        $this->expectException(InvalidArgumentException::class);

        new TieredCache('foo');
    }
}
