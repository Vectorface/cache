<?php

namespace Vectorface\Tests\Cache;

use Vectorface\Cache\Cache;
use Vectorface\Cache\PHPCache;
use Vectorface\Cache\CacheHelper;
use PHPUnit\Framework\TestCase;

class CacheHelperTest extends TestCase
{
    public function testCacheHelper()
    {
        /* A callback that returns "foo" only the first time. */
        $value = 'foo';
        $callback = function() use(&$value) {
            $once = $value;
            $value = null;
            return $once;
        };

        $cache = new PHPCache();
        $this->assertEquals('foo', CacheHelper::fetch($cache, 'bar', $callback, array(), 300));
        $this->assertEquals('foo', CacheHelper::fetch($cache, 'bar', $callback, array(), 300));
        $this->assertNull($callback());
        $this->assertNull($value);

        /* A callback that returns its arguments, so we can test various argument formats. */
        $callback = function() {
            return func_get_args();
        };

        $this->assertEquals(array(), CacheHelper::fetch($cache, 'a1', $callback, null, 300));
        $this->assertEquals(array(1, 2, 3), CacheHelper::fetch($cache, 'a2', $callback, array(1, 2, 3), 300));
        $this->assertEquals(array(1), CacheHelper::fetch($cache, 'a3', $callback, 1, 300));
    }

    public function testBadThings()
    {
        try {
            $emptyFunc = function() {
            };
            CacheHelper::fetch(new PHPCache(), $this, $emptyFunc, array(), 300);
            $this->fail('An invalid key should trigger an exception');
        } catch (\Exception $e) {
        } // Expected
    }
}
