<?php

namespace Vectorface\Tests\Cache;

use TypeError;
use Vectorface\Cache\Exception\CacheException;
use Vectorface\Cache\PHPCache;
use Vectorface\Cache\CacheHelper;
use PHPUnit\Framework\TestCase;

class CacheHelperTest extends TestCase
{
    /**
     * @throws CacheException
     */
    public function testCacheHelper()
    {
        /* A callback that returns "foo" only the first time. */
        $value = 'foo';
        $callback = static function() use (&$value) {
            $once = $value;
            $value = null;
            return $once;
        };

        $cache = new PHPCache();
        $this->assertEquals('foo', CacheHelper::fetch($cache, 'bar', $callback));
        $this->assertEquals('foo', CacheHelper::fetch($cache, 'bar', $callback));
        $this->assertNull($callback());
        $this->assertNull($value);

        /* A callback that returns its arguments, so we can test various argument formats. */
        $callback = static function() {
            return func_get_args();
        };

        $this->assertEquals([], CacheHelper::fetch($cache, 'a1', $callback));
        $this->assertEquals([1, 2, 3], CacheHelper::fetch($cache, 'a2', $callback, [1, 2, 3]));
        $this->assertEquals([1], CacheHelper::fetch($cache, 'a3', $callback, [1]));
    }

    /**
     * @throws CacheException
     */
    public function testBadThings()
    {
        $this->expectException(TypeError::class);
        CacheHelper::fetch(new PHPCache(), $this, static function() {});
    }
}
