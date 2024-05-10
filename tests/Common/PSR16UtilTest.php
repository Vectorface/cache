<?php

namespace Vectorface\Tests\Cache\Common;

use DateInterval;
use ReflectionClass;
use stdClass;
use TypeError;
use Vectorface\Cache\Exception\CacheException;
use Vectorface\Cache\Exception\InvalidArgumentException;
use Vectorface\Cache\PHPCache;
use PHPUnit\Framework\TestCase;
use Vectorface\Tests\Cache\Helpers\BrokenDateTime;

class PSR16UtilTest extends TestCase
{
    public function testEnforcesScalarKey()
    {
        $this->expectException(TypeError::class);
        (new PHPCache)->get(new stdClass);
    }

    public function testEnforcesScalarKeys()
    {
        $this->expectException(InvalidArgumentException::class);
        (new PHPCache)->getMultiple([new stdClass()]);
    }

    public function testEnforcesIterableKeys()
    {
        $this->expectException(TypeError::class);
        (new PHPCache)->getMultiple("not array or Traversable");
    }

    /**
     * @throws CacheException
     */
    public function testConvertsDateIntervalToTtl()
    {
        $this->assertEquals(86462, PHPCache::ttl(new DateInterval("P0000-00-01T00:01:02")));
    }

    /**
     * @throws CacheException
     */
    public function testBrokenDateTime()
    {
        $this->expectException(CacheException::class);
        $ref = new ReflectionClass(PHPCache::class);
        $prop = $ref->getProperty('dateTimeClass');
        $prop->setAccessible(true);
        $prop->setValue(null, BrokenDateTime::class);

        PHPCache::ttl(new DateInterval("P0000-00-01T00:01:02"));
    }
}
