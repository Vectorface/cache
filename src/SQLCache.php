<?php

namespace Vectorface\Cache;

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
 *     expires INT(10) UNSIGNED DEFAULT NULL,
 *     KEY expires (expires)
 * ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
 */
class SQLCache implements Cache
{
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
     * Statement for retrieving entries from the cache.
     */
    const GET_SQL = 'SELECT value FROM cache WHERE entry=? AND expires>=UNIX_TIMESTAMP()';

    /**
     * Statement for deleting entries from the cache.
     */
    const DELETE_SQL = 'DELETE FROM cache WHERE entry=?';

    /**
     * The database connection to be used for cache operations.
     *
     * @var \PDO
     */
    private $conn;

    /**
     * An associative array of PDO statements used in get/set.
     *
     * @var \PDOStatement
     */
    private $statements = [];

    /**
     * Create an instance of the SQL cache.
     *
     * @param \PDO $conn The database connection to use for cache operations.
     */
    public function __construct(\PDO $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Attempt to retrieve an entry from the cache.
     *
     * The value will be unserialized before it is returned.
     *
     * @param mixed  $default Default value to return if the key does not exist.
     * @return mixed Returns the value stored for the given key, or false on failure.
     */
    public function get($key, $default = null)
    {
        try {
            $stmt = $this->getStatement(__METHOD__, self::GET_SQL);
            $stmt->execute([$key]);
        } catch (\PDOException $e) {
            return false;
        }
        $result = $stmt->fetchColumn();
        return empty($result) ? $default : unserialize($result);
    }

    /**
     * Place an entry in the cache, overwriting it if it already exists.
     *
     * The value will be serialized before it is written.
     *
     * @param string $key The cache key.
     * @param mixed $value The value to be stored.
     * @param int $ttl The time to live, in seconds.
     * @return bool True if successful, false otherwise.
     */
    public function set($key, $value, $ttl = false)
    {
        $ttl = $ttl ? ($ttl + time()) : (pow(2, 32) - 1);
        $value = serialize($value);

        try {
            $stmt = $this->getStatement(__METHOD__ . ".insert", self::SET_SQL);
            return $stmt->execute([$key, $value, $ttl]);
        } catch (\PDOException $e) {
        } // fall through to attempt update

        try {
            $stmt = $this->getStatement(__METHOD__ . ".update", self::UPDATE_SQL);
            return $stmt->execute([$value, $ttl, $key]);
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Remove an entry from the cache.
     *
     * @param String $key The key to be deleted (removed) from the cache.
     */
    public function delete($key)
    {
        try {
            $stmt = $this->getStatement(__METHOD__, self::DELETE_SQL);
            return $stmt->execute([$key]);
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Remove any expired items from the cache.
     *
     * @return bool True if successful, false otherwise.
     */
    public function clean()
    {
        try {
            $this->conn->exec(self::CLEAN_SQL);
        } catch (\PDOException $e) {
            return false;
        }
        return true;
    }

    /**
     * Flush the cache; Empty it of all entries.
     *
     * @return bool True if successful, false otherwise.
     */
    public function flush()
    {
        try {
            $this->conn->exec(self::FLUSH_SQL);
        } catch (\PDOException $e) {
            return false;
        }
        return true;
    }

    /**
     * Get a prepared statement for the given method's SQL.
     *
     * The result is stored internally to limit repeated preparing of SQL.
     *
     * @param string $method The method name to for which this statement applies.
     * @param string $sql The SQL statement associated with the given method.
     * @return \PDOStatement Returns the prepared statement for the given method.
     */
    private function getStatement($method, $sql)
    {
        if (empty($this->statements[$method])) {
            $this->statements[$method] = $this->conn->prepare($sql);
        }
        return $this->statements[$method];
    }
}
