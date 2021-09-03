<?php

namespace Vectorface\Tests\Cache;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use stdClass;
use Vectorface\Cache\AtomicCounter;
use Vectorface\Cache\Exception\CacheException;
use Vectorface\Cache\NullCache;
use Vectorface\Cache\PHPCache;
use Vectorface\Cache\LogDecorator;
use Vectorface\Tests\Cache\Helpers\FakeLogger;

class LogDecoratorTest extends TestCase
{
    /**
     * @throws CacheException
     */
    public function testSynopsis()
    {
        $cache = new PHPCache();
        $logger = new FakeLogger();
        $loggedCache = new LogDecorator($cache, $logger);

        /*
         * All cache methods pass through to their underlying cache, with some
         * information about the cache operation logged to the logger
         *
         * The following are successes with the PHPCache
         */
        $this->assertEquals("dflt", $loggedCache->get("newKey", "dflt"));
        $this->assertEquals("debug: get newKey MISS", $logger->getLastMessage());

        $this->assertTrue($loggedCache->set("testKey", "val", 123));
        $this->assertEquals("debug: set testKey SUCCESS ttl=123, type=string, size=3", $logger->getLastMessage());

        $this->assertTrue($loggedCache->has("testKey"));
        $this->assertEquals("debug: has testKey true", $logger->getLastMessage());

        $this->assertEquals("val", $loggedCache->get("testKey", "dflt"));
        $this->assertEquals("debug: get testKey HIT size=3", $logger->getLastMessage());

        $this->assertTrue($loggedCache->delete("testKey"));
        $this->assertEquals("debug: delete testKey SUCCESS", $logger->getLastMessage());

        $this->assertTrue($loggedCache->clean());
        $this->assertEquals("debug: clean SUCCESS", $logger->getLastMessage());

        $this->assertTrue($loggedCache->flush());
        $this->assertEquals("debug: flush SUCCESS", $logger->getLastMessage());
        $this->assertTrue($loggedCache->clear()); /* alias difference: PSR v.s. Internal*/
        $this->assertEquals("debug: flush SUCCESS", $logger->getLastMessage());

        $this->assertEquals(['foo' => null, 'bar' => null], $loggedCache->getMultiple(['foo', 'bar']));
        $this->assertEquals("debug: getMultiple [foo, bar] count=2", $logger->getLastMessage());

        $this->assertTrue($loggedCache->setMultiple(['foo' => 'bar', 'baz' => 'quux'], 123));
        $this->assertEquals("debug: setMultiple [foo, baz] SUCCESS ttl=123", $logger->getLastMessage());

        $this->assertTrue($loggedCache->deleteMultiple(['foo', 'baz']));
        $this->assertEquals("debug: deleteMultiple [foo, baz] SUCCESS", $logger->getLastMessage());

        $this->assertEquals(1, $loggedCache->increment("counter"));
        $this->assertEquals("debug: increment counter by 1 SUCCESS, value=1", $logger->getLastMessage());

        $this->assertEquals(0, $loggedCache->decrement("counter"));
        $this->assertEquals("debug: decrement counter by 1 SUCCESS, value=0", $logger->getLastMessage());
    }

    /**
     * @throws CacheException
     */
    public function testFailures()
    {
        /* The following are failures with NullCache */
        $cache = new NullCache();
        $logger = new FakeLogger();
        $loggedCache = new LogDecorator($cache, $logger);

        $result = $loggedCache->get("newKey", "dflt");
        $this->assertEquals("dflt", $result);
        $this->assertEquals("debug: get newKey MISS", $logger->getLastMessage());

        $result = $loggedCache->set("testKey", "val", 123);
        $this->assertEquals(false, $result);
        $this->assertEquals("debug: set testKey FAILURE ttl=123, type=string, size=3", $logger->getLastMessage());

        $result = $loggedCache->get("testKey", "dflt");
        $this->assertEquals("dflt", $result);
        $this->assertEquals("debug: get testKey MISS", $logger->getLastMessage());

        $result = $loggedCache->delete("testKey");
        $this->assertEquals(false, $result);
        $this->assertEquals("debug: delete testKey FAILURE", $logger->getLastMessage());

        $result = $loggedCache->clean();
        $this->assertEquals(false, $result);
        $this->assertEquals("debug: clean FAILURE", $logger->getLastMessage());

        $result = $loggedCache->flush();
        $this->assertEquals(false, $result);
        $this->assertEquals("debug: flush FAILURE", $logger->getLastMessage());

        try {
            $loggedCache->increment("counter");
        } catch (CacheException $e) {
            $this->assertEquals($e->getMessage(), "This decorated instance does not implement " . AtomicCounter::class);
        }

        try {
            $loggedCache->decrement("counter");
        } catch (CacheException $e) {
            $this->assertEquals($e->getMessage(), "This decorated instance does not implement " . AtomicCounter::class);
        }
    }

    /**
     * @throws CacheException
     */
    public function testLoggerNotPresent()
    {
        /* You can omit the logger, and it still operates as a pass-through cache */
        $this->assertFalse((new LogDecorator(new NullCache()))->set("foo", "bar"));
    }

    public function testInvalidLevel()
    {
        $this->expectException(InvalidArgumentException::class);

        new LogDecorator(new NullCache(), null, "can't log this; na na na na, na na, na na!");
    }

    /**
     * @throws CacheException
     * @throws ReflectionException
     */
    public function testInvalidClass()
    {
        $this->expectException(CacheException::class);

        $decorator = new LogDecorator(new NullCache());

        $class = new ReflectionClass($decorator);
        $prop = $class->getProperty('cache');
        $prop->setAccessible(true);
        $prop->setValue($decorator, new stdClass());

        $decorator->clean();
    }
}
