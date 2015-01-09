<?php

namespace Vectorface\Tests\Cache;

use Vectorface\MySQLite\MySQLite;
use Vectorface\Cache\Cache;
use Vectorface\Cache\SQLCache;

class SQLCacheTest extends GenericCacheTest
{
    private $pdo;

    public function setUp()
    {
        try {
            $this->pdo = new \PDO('sqlite::memory:', null, null, array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION));
        } catch (\PDOException $e) {
            $this->markTestSkipped("Please ensure that the pdo_sqlite module is installed and configured");
        }
        $this->pdo->sqliteCreateFunction('UNIX_TIMESTAMP', 'time', 0);
        $this->createTable();

        $this->cache = new SQLCache($this->pdo);
    }

    public function testBadThings()
    {
        /* Fail before statements are prepared. */
        $this->pdo->exec('DROP TABLE cache');
        $this->assertFalse($this->cache->get('anything'));
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
        $this->assertFalse($this->cache->get('foo'));
        $this->assertTrue($this->cache->clean());
        $this->assertTrue($this->cache->set('foo', 'bar'));
        $this->assertTrue($this->cache->flush());
        $this->assertFalse($this->cache->get('foo'));

        /* Make 'em fail afterwards. */
        $this->pdo->exec('DROP TABLE cache');
        $this->assertFalse($this->cache->get('anything'));
        $this->assertFalse($this->cache->set('anything', 'anything'));
        $this->assertFalse($this->cache->delete('anything'));
        $this->assertFalse($this->cache->clean());
        $this->assertFalse($this->cache->flush());
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
                expires UNSIGNED INT DEFAULT NULL
            )
        ');
    }
}
