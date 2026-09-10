<?php

namespace Vectorface\Tests\Cache;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use TypeError;
use Vectorface\Cache\CacheHelper;
use Vectorface\Cache\Exception\CacheException;
use Vectorface\Cache\Exception\InvalidArgumentException;
use Vectorface\Cache\PHPCache;
use Vectorface\Tests\Cache\Helpers\ControlledCacheHelper;

class CacheHelperTest extends TestCase
{
    private PHPCache $cache;
    private int $calls = 0;

    protected function setUp() : void
    {
        $this->cache = new PHPCache();
        $this->calls = 0;
        ControlledCacheHelper::$now = 1000000.0;
        ControlledCacheHelper::$random = [1.0]; /* ln(1) = 0: never refresh early */
    }

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

    /**
     * A callback that counts its calls, takes $duration seconds of fake time, and returns its args
     */
    private function counter(float $duration = 0.0) : callable
    {
        return function(...$args) use ($duration) {
            $this->calls++;
            ControlledCacheHelper::$now += $duration;
            return ['call' => $this->calls, 'args' => $args];
        };
    }

    public function testProtectedMissAndHit()
    {
        $result = ControlledCacheHelper::fetchProtected($this->cache, 'k', $this->counter(2.0), [1, 2], 100);
        $this->assertEquals(['call' => 1, 'args' => [1, 2]], $result);

        /* Entry carries value, expiry, and recompute time; stored for ttl + staleTtl */
        $entry = $this->cache->get('k');
        $this->assertEquals($result, $entry['v']);
        $this->assertEquals(1000100.0, $entry['e']);
        $this->assertEquals(2.0, $entry['d']);
        ControlledCacheHelper::$now += 150; /* past ttl, but within stale window */
        $this->assertNotNull($this->cache->get('k'));
        ControlledCacheHelper::$now = 1000010.0;

        /* Still fresh, and random says no early refresh: served from cache */
        $result = ControlledCacheHelper::fetchProtected($this->cache, 'k', $this->counter(), [1, 2], 100);
        $this->assertEquals(['call' => 1, 'args' => [1, 2]], $result);
        $this->assertEquals(1, $this->calls);
    }

    public function testProtectedEarlyRefresh()
    {
        ControlledCacheHelper::fetchProtected($this->cache, 'k', $this->counter(2.0), [], 100);
        ControlledCacheHelper::$now = 1000090.0; /* 10s left, delta=2 */

        /* -ln(r) * 2 must be >= 10, i.e. r <= e^-5. Just above: no refresh. */
        ControlledCacheHelper::$random = [exp(-4.9), 1.0];
        $result = ControlledCacheHelper::fetchProtected($this->cache, 'k', $this->counter(2.0), [], 100);
        $this->assertEquals(1, $result['call']);

        /* Just below: refresh. */
        ControlledCacheHelper::$random = [exp(-5.1), 1.0];
        $result = ControlledCacheHelper::fetchProtected($this->cache, 'k', $this->counter(2.0), [], 100);
        $this->assertEquals(2, $result['call']);
        $this->assertEquals(1000190.0, $this->cache->get('k')['e']);
    }

    public function testProtectedGraceProtectsConcurrentFetchers()
    {
        ControlledCacheHelper::fetchProtected($this->cache, 'k', $this->counter(2.0), [], 100);
        ControlledCacheHelper::$now = 1000090.0;

        /* Simulate a refresher that has started but not finished: the callback checks the cache mid-flight. */
        $cache = $this->cache;
        $midFlight = null;
        $slow = function() use ($cache, &$midFlight) {
            $this->calls++;
            $midFlight = $cache->get('k');
            /* A concurrent request arriving now must be served the stale value without refreshing. */
            ControlledCacheHelper::$random = [1.0];
            $concurrent = ControlledCacheHelper::fetchProtected($cache, 'k', $this->counter(), [], 100);
            $this->assertEquals(1, $concurrent['call']);
            return ['call' => $this->calls];
        };

        ControlledCacheHelper::$random = [1e-9];
        $result = ControlledCacheHelper::fetchProtected($this->cache, 'k', $slow, [], 100);
        $this->assertEquals(2, $result['call']);
        $this->assertEquals(2, $this->calls);
        /* Grace = max(1, 5 * delta) = 10s, but never earlier than the original expiry */
        $this->assertEquals(1000100.0, $midFlight['e']);
        $this->assertEquals(1, $midFlight['v']['call']);
    }

    public function testProtectedHardExpiryUsesGrace()
    {
        ControlledCacheHelper::fetchProtected($this->cache, 'k', $this->counter(0.1), [], 100);
        ControlledCacheHelper::$now = 1000150.0; /* expired, but in the stale window */

        $cache = $this->cache;
        $midFlight = null;
        $slow = function() use ($cache, &$midFlight) {
            $midFlight = $cache->get('k');
            return 'fresh';
        };
        $this->assertEquals('fresh', ControlledCacheHelper::fetchProtected($this->cache, 'k', $slow, [], 100));
        /* delta was 0.1s so the grace is clamped to its 1s minimum */
        $this->assertEquals(1000151.0, $midFlight['e']);
    }

    public function testProtectedStaleOnErrorLogsToErrorLog()
    {
        ControlledCacheHelper::fetchProtected($this->cache, 'k', $this->counter(), [], 100);
        ControlledCacheHelper::$now = 1000150.0;
        $fail = static fn() => throw new RuntimeException("db down");

        $log = tempnam(sys_get_temp_dir(), 'log');
        $prev = ini_set('error_log', $log);
        try {
            $result = ControlledCacheHelper::fetchProtected($this->cache, 'k', $fail, [], 100);
        } finally {
            ini_set('error_log', $prev);
        }
        $this->assertEquals(1, $result['call']);
        $this->assertStringContainsString("Serving stale cache value for 'k': db down", file_get_contents($log));
        unlink($log);
    }

    public function testProtectedStaleOnErrorLogs()
    {
        ControlledCacheHelper::fetchProtected($this->cache, 'k', $this->counter(), [], 100);
        ControlledCacheHelper::$now = 1000150.0;
        $exception = new RuntimeException("db down");
        $fail = static fn() => throw $exception;

        $logger = new class extends AbstractLogger {
            public array $logs = [];
            public function log($level, $message, array $context = []) : void
            {
                $this->logs[] = [$level, $message, $context];
            }
        };
        $result = ControlledCacheHelper::fetchProtected($this->cache, 'k', $fail, [], 100, logger: $logger);
        $this->assertEquals(1, $result['call']);
        $this->assertCount(1, $logger->logs);
        $this->assertEquals('warning', $logger->logs[0][0]);
        $this->assertSame($exception, $logger->logs[0][2]['exception']);
        $this->assertEquals('k', $logger->logs[0][2]['key']);
    }

    public function testProtectedErrorWithoutStaleValueThrows()
    {
        $this->expectException(RuntimeException::class);
        ControlledCacheHelper::fetchProtected($this->cache, 'k', static fn() => throw new RuntimeException("db down"));
    }

    public function testProtectedBetaZeroOnlyRefreshesAtExpiry()
    {
        ControlledCacheHelper::fetchProtected($this->cache, 'k', $this->counter(2.0), [], 100, 0);
        ControlledCacheHelper::$random = [1e-300];
        ControlledCacheHelper::$now = 1000099.0;
        $result = ControlledCacheHelper::fetchProtected($this->cache, 'k', $this->counter(2.0), [], 100, 0);
        $this->assertEquals(1, $result['call']);
        ControlledCacheHelper::$now = 1000100.0;
        $result = ControlledCacheHelper::fetchProtected($this->cache, 'k', $this->counter(2.0), [], 100, 0);
        $this->assertEquals(2, $result['call']);
    }

    public function testProtectedNegativeBeta()
    {
        $this->expectException(InvalidArgumentException::class);
        ControlledCacheHelper::fetchProtected($this->cache, 'k', $this->counter(), [], 100, -1);
    }

    public function testProtectedEternalTtlBehavesLikeFetch()
    {
        $result = ControlledCacheHelper::fetchProtected($this->cache, 'k', $this->counter(), [], null);
        $this->assertEquals(1, $result['call']);
        $this->assertEquals($result, $this->cache->get('k')); /* stored raw, not wrapped */
        $result = ControlledCacheHelper::fetchProtected($this->cache, 'k', $this->counter(), [], null);
        $this->assertEquals(1, $result['call']);
    }

    public function testProtectedNullResultNotCached()
    {
        $this->assertNull(ControlledCacheHelper::fetchProtected($this->cache, 'k', static fn() => null));
        $this->assertNull($this->cache->get('k'));
    }

    public function testProtectedIgnoresForeignEntries()
    {
        $this->cache->set('k', 'raw value from fetch()');
        $result = ControlledCacheHelper::fetchProtected($this->cache, 'k', $this->counter(), [], 100);
        $this->assertEquals(1, $result['call']);
        $this->assertEquals(1, $this->cache->get('k')['v']['call']);
    }

    public function testProtectedRealRandomAndClock()
    {
        /* Smoke test of the real time/random implementations via the base class */
        $this->assertEquals(1, CacheHelper::fetchProtected($this->cache, 'k', $this->counter(), [], 100)['call']);
        $this->assertEquals(1, CacheHelper::fetchProtected($this->cache, 'k', $this->counter(), [], 100)['call']);
    }
}
