<?php

namespace Vectorface\Tests\Cache;

use Vectorface\Cache\Cache;
use Vectorface\Cache\NullCache;
use Vectorface\Cache\PHPCache;
use Vectorface\Cache\TieredCache;

class TieredCacheTest extends \PHPUnit\Framework\TestCase
{
    public function testTieredCache()
    {
        $null = new NullCache();
        $php = new PHPCache();
        $tiered = new TieredCache($null, $php);

        $this->assertNull($tiered->get('foo')); // Default is null
        $this->assertTrue($tiered->set('foo', 'bar'));
        $this->assertEquals('bar', $tiered->get('foo'));
        $this->assertFalse($tiered->delete('foo')); // one op failed, so all fail.
        $this->assertFalse($tiered->clean()); // one op failed, so all fail.
        $this->assertFalse($tiered->flush()); // one op failed, so all fail.
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testBadArg()
    {
        new TieredCache('foo');
    }
}
