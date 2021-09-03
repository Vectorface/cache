<?php
/** @noinspection SqlResolve */
/** @noinspection SqlNoDataSourceInspection */
/** @noinspection PhpComposerExtensionStubsInspection */

namespace Vectorface\Tests\Cache;

use InvalidArgumentException;
use PDO;
use PDOException;
use Vectorface\Cache\Exception\CacheException;
use Vectorface\Cache\SQLCache;

class SQLCacheTest extends GenericCacheTest
{
    private $pdo;

    /** @var SQLCache */
    protected $cache;

    protected function setUp(): void
    {
        try {
            $this->pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (PDOException $e) {
            $this->markTestSkipped("Please ensure that the pdo_sqlite module is installed and configured");
        }
        $this->pdo->sqliteCreateFunction('UNIX_TIMESTAMP', 'time', 0);
        $this->createTable();

        $this->cache = new SQLCache($this->pdo);
    }

    /**
     * @throws CacheException
     * @noinspection DuplicatedCode
     */
    public function testBadThings()
    {
        /* Fail before statements are prepared. */
        $this->pdo->exec('DROP TABLE cache');
        $this->assertNull($this->cache->get('anything'));
        $this->assertFalse($this->cache->set('anything', 'anything'));
        $this->assertFalse($this->cache->delete('anything'));
        $this->assertFalse($this->cache->clean());
        $this->assertFalse($this->cache->flush());

        /* Create table then get Statements warmed up. */
        $this->createTable();
        $this->assertTrue($this->cache->set('foo', 'bar'));
        $this->assertEquals('bar', $this->cache->get('foo'));
        $this->assertTrue($this->cache->set('foo', 'bar'));
        $this->assertEquals('bar', $this->cache->get('foo'));
        $this->assertTrue($this->cache->delete('foo'));
        $this->assertNull($this->cache->get('foo'));
        $this->assertTrue($this->cache->clean());
        $this->assertTrue($this->cache->set('foo', 'bar'));
        $this->assertTrue($this->cache->flush());
        $this->assertNull($this->cache->get('foo'));

        /* Make 'em fail afterwards. */
        $this->pdo->exec('DROP TABLE cache');
        $this->assertNull($this->cache->get('anything'));
        $this->assertFalse($this->cache->set('anything', 'anything'));
        $this->assertFalse($this->cache->delete('anything'));
        $this->assertFalse($this->cache->clean());
        $this->assertFalse($this->cache->flush());
        $this->assertFalse($this->cache->has('anything'));
        $this->assertFalse($this->cache->deleteMultiple(['foo', 'bar']));
        $this->assertFalse($this->cache->increment('anything'));
        $this->assertFalse($this->cache->decrement('anything'));
        $this->assertEquals(['foo' => 'dflt', 'bar' => 'dflt'], $this->cache->getMultiple(['foo', 'bar'], 'dflt'));
    }

    /**
     * @throws CacheException
     */
    public function testLongKey()
    {
        $key = str_repeat("a", 100);
        $hash = $this->cache->hashKey($key);
        $expected = "test:" . microtime(true) . mt_rand();

        $this->cache->set($key, $expected);

        $this->assertEquals(
            serialize($expected),
            $this->pdo->query("SELECT value FROM cache WHERE entry=\"$hash\"")->fetch(PDO::FETCH_COLUMN)
        );

        $this->assertEquals($expected, $this->cache->get($key));
        $this->assertTrue($this->cache->delete($key));
        $this->assertEquals("default", $this->cache->get($key, "default"));
    }

    /**
     * @throws CacheException
     */
    public function testCounting()
    {
        $this->assertEquals(1, $this->cache->increment('foo'));
        $this->assertEquals(0, $this->cache->decrement('foo'));

        $this->assertEquals(5, $this->cache->increment('foo', 5));
        $this->assertEquals(2, $this->cache->decrement('foo', 3));
    }

    /**
     * @throws CacheException
     */
    public function testFailTransaction()
    {
        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->method('beginTransaction')->willReturn(false);

        $this->assertEquals(false, (new SQLCache($pdoMock))->increment('foo'));
        $this->assertEquals(false, (new SQLCache($pdoMock))->decrement('foo'));
    }

    /**
     * @throws CacheException
     */
    public function testBadPdoIncrement()
    {
        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->method('beginTransaction')->willThrowException(new PDOException('bad'));
        $pdoMock->method('inTransaction')->willReturn(true);
        $pdoMock->method('rollback')->willReturn(true);

        $this->assertEquals(false, (new SQLCache($pdoMock))->increment('foo'));
    }

    /**
     * @throws CacheException
     */
    public function testBadPdoDecrement()
    {
        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->method('beginTransaction')->willThrowException(new PDOException('bad'));
        $pdoMock->method('inTransaction')->willReturn(true);
        $pdoMock->method('rollback')->willReturn(true);

        $this->assertEquals(false, (new SQLCache($pdoMock))->decrement('foo'));
    }

    /**
     * @throws CacheException
     */
    public function testBadStep()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->assertEquals(false, $this->cache->increment('foo', null));
    }

    /**
     * Create the "cache" table in SQLite.
     */
    private function createTable()
    {
        $this->pdo->exec('
            CREATE TABLE cache (
                entry VARCHAR(64) PRIMARY KEY NOT NULL,
                value LONGBLOB,
                expires BIGINT DEFAULT NULL
            )
        ');
    }
}
