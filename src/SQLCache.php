<?php

namespace Vectorface\Cache;

use PDO;
use PDOStatement;
use PDOException;
use Vectorface\Cache\Common\PSR16Util;

/**
 * This cache is slow, according to basic benchmarks:
 *
 * Parameters:
 *   MySQL 5.0, running locally
 *   9-byte key
 *   151-byte value
 *   10000-iteration test
 *
 * Result:
 *   16.7824881077 seconds
 *
 * Conclusion:
 *   Capable of approximately 595.85 requests/second
 */

/**
 * A cache implementation that uses SQL for storage.
 *
 * An example table might look like:
 * CREATE TABLE cache (
 *     entry VARCHAR(64) PRIMARY KEY NOT NULL,
 *     value LONGBLOB,
 *     expires BIGINT UNSIGNED DEFAULT NULL,
 *     KEY expires (expires)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
 */
class SQLCache implements Cache
{
    use PSR16Util;

    /**
     * Hash keys beyond this size
     */
    const MAX_KEY_LEN = 64;

    /**
     * Statement for flushing all entries from the cache.
     */
    const FLUSH_SQL = 'DELETE FROM cache';

    /**
     * Statement for deleting expired entries from the cache.
     */
    const CLEAN_SQL = 'DELETE FROM cache WHERE expires<=UNIX_TIMESTAMP()';

    /**
     * Statement for inserting or updating entries in the cache.
     */
    const SET_SQL = 'INSERT INTO cache (entry,value,expires) VALUES(?,?,?)';

    /**
     * Statement for updating if an entry already exists.
     */
    const UPDATE_SQL = 'UPDATE cache SET value=?, expires=? WHERE entry=?';

    /**
     * Statement for checking if an entry exists
     */
    const HAS_SQL = 'SELECT COUNT(*) AS num FROM cache WHERE entry=? AND expires>=UNIX_TIMESTAMP()';

    /**
     * Statement for retrieving an entry from the cache
     */
    const GET_SQL = 'SELECT value FROM cache WHERE entry=? AND expires>=UNIX_TIMESTAMP()';

    /**
     * Statement for retrieving entries from the cache (no statement caching)
     */
    const MGET_SQL = 'SELECT entry,value FROM cache WHERE entry IN(%s) AND expires>=UNIX_TIMESTAMP()';

    /**
     * Statement for deleting an entry from the cache
     */
    const DELETE_SQL = 'DELETE FROM cache WHERE entry=?';

    /**
     * Statement for deleting entries from the cache (no statement caching)
     */
    const MDELETE_SQL = 'DELETE FROM cache WHERE entry IN(%s)';

    /**
     * The database connection to be used for cache operations.
     *
     * @var PDO
     */
    private $conn;

    /**
     * An associative array of PDO statements used in get/set.
     *
     * @var PDOStatement
     */
    private $statements = [];

    /**
     * Create an instance of the SQL cache.
     *
     * @param PDO $conn The database connection to use for cache operations.
     */
    public function __construct(PDO $conn)
    {
        $this->conn = $conn;
    }

    /**
     * @inheritDoc Vectorface\Cache\Cache
     */
    public function get($key, $default = null)
    {
        $key = $this->key($key);
        $key = (strlen($key) > self::MAX_KEY_LEN) ? $this->hashKey($key) : $key;

        try {
            $stmt = $this->getStatement(__METHOD__, self::GET_SQL);
            $stmt->execute([$key]);
        } catch (PDOException $e) {
            return $default;
        }
        $result = $stmt->fetchColumn();
        return empty($result) ? $default : unserialize($result);
    }

    /**
     * @inheritDoc
     */
    public function getMultiple($keys, $default = null)
    {
        if (empty($keys)) {
            return [];
        }

        $keys = $this->keys($keys);
        $sqlKeys = [];
        foreach ($keys as $key) {
            $sqlKeys[] = (strlen($key) > self::MAX_KEY_LEN) ? $this->hashKey($key) : $key;
        }

        try {
            $stmt = $this->conn->prepare(sprintf(
                self::MGET_SQL,
                implode(',', array_fill(0, count($keys), '?'))
            ));
            $stmt->execute($sqlKeys);
            $result = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (PDOException $e) {
            $result = [];
        }

        $return = array_map('unserialize', $result);
        foreach ($keys as $key) {
            if (!isset($return[$key])) {
                $return[$key] = $default;
            }
        }
        return $return;
    }


    /**
     * @inheritDoc Vectorface\Cache\Cache
     */
    public function set($key, $value, $ttl = null)
    {
        $key = $this->key($key);
        $key = (strlen($key) > self::MAX_KEY_LEN) ? $this->hashKey($key) : $key;
        $ttl = $this->ttl($ttl);
        $ttl = $ttl ? ($ttl + time()) : PHP_INT_MAX;
        $value = serialize($value);

        try {
            $stmt = $this->getStatement(__METHOD__ . ".insert", self::SET_SQL);
            return $stmt->execute([$key, $value, $ttl]);
        } catch (PDOException $e) {
            // Insert can fail if the entry exists; It's normal.
        }

        try {
            $stmt = $this->getStatement(__METHOD__ . ".update", self::UPDATE_SQL);
            $success = $stmt->execute([$value, $ttl, $key]);
            return $success && $stmt->rowCount() === 1;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * @inheritDoc Psr\SimpleCache\CacheInterface
     */
    public function setMultiple($values, $ttl = null)
    {
        $success = true;
        foreach ($this->values($values) as $key => $value) {
            $success = $this->set($key, $value, $ttl) && $success;
        }
        return $success;
    }

    /**
     * @inheritDoc Vectorface\Cache\Cache
     */
    public function delete($key)
    {
        $key = $this->key($key);
        $key = (strlen($key) > self::MAX_KEY_LEN) ? $this->hashKey($key) : $key;

        try {
            $stmt = $this->getStatement(__METHOD__, self::DELETE_SQL);
            return $stmt->execute([$key]);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple($keys)
    {
        if (empty($keys)) {
            return true;
        }

        $keysArray = [];
        foreach ($keys as $key) {
            $keysArray[] = (strlen($key) > self::MAX_KEY_LEN) ? $this->hashKey($key) : $key;
        }

        try {
            $stmt = $this->conn->prepare(sprintf(
                self::MDELETE_SQL,
                implode(',', array_fill(0, count($keysArray), '?'))
            ));
            $stmt->execute($keysArray);
        } catch (PDOException $e) {
            return false;
        }
        return true;
    }

    /**
     * @inheritdoc vectorface\cache\cache
     */
    public function clean()
    {
        try {
            $this->conn->exec(self::CLEAN_SQL);
        } catch (PDOException $e) {
            return false;
        }
        return true;
    }

    /**
     * @inheritdoc vectorface\cache\cache
     */
    public function flush()
    {
        try {
            $this->conn->exec(self::FLUSH_SQL);
        } catch (PDOException $e) {
            return false;
        }
        return true;
    }

    /**
     * @inheritDoc Psr\SimpleCache\CacheInterface
     */
    public function clear()
    {
        return $this->flush();
    }

    /**
     * @inheritDoc Psr\SimpleCache\CacheInterface
     */
    public function has($key)
    {
        $key = (strlen($key) > self::MAX_KEY_LEN) ? $this->hashKey($key) : $key;

        try {
            $stmt = $this->getStatement(__METHOD__, self::HAS_SQL);
            $stmt->execute([$key]);
        } catch (PDOException $e) {
            return false;
        }
        return $stmt->fetchColumn() ? true : false;
    }

    /**
     * Get a prepared statement for the given method's SQL.
     *
     * The result is stored internally to limit repeated preparing of SQL.
     *
     * @param string $method The method name to for which this statement applies.
     * @param string $sql The SQL statement associated with the given method.
     * @return PDOStatement Returns the prepared statement for the given method.
     */
    private function getStatement($method, $sql)
    {
        if (empty($this->statements[$method])) {
            $this->statements[$method] = $this->conn->prepare($sql);
        }
        return $this->statements[$method];
    }

    /**
     * Get a unique hash key; Used when the key is too long
     * @param string $key
     * @return string The hash of the key parameter
     * @private Public for testing
     */
    public static function hashKey($key)
    {
        return hash('sha256', $key);
    }
}
