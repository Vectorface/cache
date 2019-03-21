<?php

namespace Vectorface\Tests\Cache;

use Vectorface\Cache\Cache;
use Vectorface\Cache\PHPCache;
use Vectorface\Cache\CacheHelper;

class CacheHelperTest extends \PHPUnit\Framework\TestCase
{
    public function testCacheHelper()
    {
        /* A callback that returns "foo" only the first time. */
        $value = 'foo';
        $callback = function() use (&$value) {
            $once = $value;
            $value = null;
            return $once;
        };

        $cache = new PHPCache();
        $this->assertEquals('foo', CacheHelper::fetch($cache, 'bar', $callback, [], 300));
        $this->assertEquals('foo', CacheHelper::fetch($cache, 'bar', $callback, [], 300));
        $this->assertNull($callback());
        $this->assertNull($value);

        /* A callback that returns its arguments, so we can test various argument formats. */
        $callback = function() {
            return func_get_args();
        };

        $this->assertEquals([], CacheHelper::fetch($cache, 'a1', $callback, null, 300));
        $this->assertEquals([1, 2, 3], CacheHelper::fetch($cache, 'a2', $callback, [1, 2, 3], 300));
        $this->assertEquals([1], CacheHelper::fetch($cache, 'a3', $callback, 1, 300));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testBadThings()
    {
        CacheHelper::fetch(new PHPCache(), $this, function() {
        }, [], 300);
    }
}
