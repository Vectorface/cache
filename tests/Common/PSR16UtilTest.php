<?php

namespace Vectorface\Tests\Cache;

use DateInterval;
use Vectorface\Cache\PHPCache;
use PHPUnit\Framework\TestCase;
use Vectorface\Tests\Cache\Helpers\BrokenDateTime;

class PSR16UtilTest extends TestCase
{
    /**
     * @expectedException Vectorface\Cache\Exception\InvalidArgumentException
     */
    public function testEnforcesScalarKey()
    {
        (new PHPCache)->get(new \stdClass);
    }

    /**
     * @expectedException Vectorface\Cache\Exception\InvalidArgumentException
     */
    public function testEnforcesScalarKeys()
    {
        (new PHPCache)->getMultiple([new \stdClass()]);
    }

    /**
     * @expectedException Vectorface\Cache\Exception\InvalidArgumentException
     */
    public function testEnforcesIterableKeys()
    {
        (new PHPCache)->getMultiple("not array or Traversable");
    }

    public function testConvertsDateIntervalToTtl()
    {
        $this->assertEquals(86462, PHPCache::ttl(new DateInterval("P0000-00-01T00:01:02")));
    }

    /**
     * @expectedException Vectorface\Cache\Exception\CacheException
     */
    public function testBrokenDateTime()
    {
        $ref = new \ReflectionClass(\Vectorface\Cache\PHPCache::class);
        $prop = $ref->getProperty('dateTimeClass');
        $prop->setAccessible(true);
        $prop->setValue(null, BrokenDateTime::class);

        PHPCache::ttl(new DateInterval("P0000-00-01T00:01:02"));
    }
}