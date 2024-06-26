<?php
/** @noinspection SqlResolve */
/** @noinspection SqlWithoutWhere */
/** @noinspection SqlNoDataSourceInspection */
/** @noinspection PhpComposerExtensionStubsInspection */

namespace Vectorface\Cache;

use DateInterval;
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
class SQLCache implements Cache, AtomicCounter
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
    const CLEAN_SQL = 'DELETE FROM cache WHERE expires <= UNIX_TIMESTAMP()';

    /**
     * Statement for inserting or updating entries in the cache.
     */
    const SET_SQL = 'INSERT INTO cache (entry, value, expires) VALUES(?, ?, ?)';

    /**
     * Statement for updating if an entry already exists.
     */
    const UPDATE_SQL = 'UPDATE cache SET value = ?, expires = ? WHERE entry = ?';

    /**
     * Statement for atomically updating a stored count if an entry already exists.
     */
    const UPDATE_INCREMENT_SQL = 'UPDATE cache SET value = ? WHERE entry = ?';

    /**
     * Statement for checking if an entry exists
     */
    const HAS_SQL = 'SELECT COUNT(*) AS num FROM cache WHERE entry = ? AND expires >= UNIX_TIMESTAMP()';

    /**
     * Statement for retrieving an entry from the cache
     */
    const GET_SQL = 'SELECT value FROM cache WHERE entry = ? AND expires >= UNIX_TIMESTAMP()';

    /**
     * Statement for retrieving entries from the cache (no statement caching)
     */
    const MGET_SQL = 'SELECT entry, value FROM cache WHERE entry IN(%s) AND expires >= UNIX_TIMESTAMP()';

    /**
     * Statement for deleting an entry from the cache
     */
    const DELETE_SQL = 'DELETE FROM cache WHERE entry = ?';

    /**
     * Statement for deleting entries from the cache (no statement caching)
     */
    const MDELETE_SQL = 'DELETE FROM cache WHERE entry IN(%s)';

    /**
     * An associative array of PDO statements used in get/set.
     *
     * @var PDOStatement[]
     */
    private array $statements = [];

    /**
     * Create an instance of the SQL cache.
     *
     * @param PDO $conn The database connection to use for cache operations.
     */
    public function __construct(
        private PDO $conn,
    ) {}

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null) : mixed
    {
        $key = $this->key($key);
        $key = $this->hashKey($key);

        try {
            $stmt = $this->getStatement(__METHOD__, self::GET_SQL);
            $stmt->execute([$key]);
        } catch (PDOException) {
            return $default;
        }
        $result = $stmt->fetchColumn();
        if (empty($result)) {
            return $default;
        }
        return unserialize($result);
    }

    /**
     * @inheritDoc
     */
    public function getMultiple(iterable $keys, mixed $default = null) : iterable
    {
        if (empty($keys)) {
            return [];
        }

        $keys = $this->keys($keys);
        $sqlKeys = array_map([$this, 'hashKey'], $keys);

        try {
            $stmt = $this->conn->prepare(sprintf(
                self::MGET_SQL,
                implode(',', array_fill(0, count($keys), '?'))
            ));
            $stmt->execute($sqlKeys);
            $result = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        } catch (PDOException) {
            $result = [];
        }

        $return = array_map('unserialize', $result);
        foreach ($keys as $key) {
            $return[$key] ??= $default;
        }
        return $return;
    }


    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null) : bool
    {
        $key = $this->key($key);
        $key = $this->hashKey($key);
        $ttl = $this->ttl($ttl);
        $ttl = $ttl ? ($ttl + time()) : PHP_INT_MAX;
        $value = serialize($value);

        try {
            $stmt = $this->getStatement(__METHOD__ . ".insert", self::SET_SQL);
            return $stmt->execute([$key, $value, $ttl]);
        } catch (PDOException) {
            // Insert can fail if the entry exists; It's normal.
        }

        try {
            $stmt = $this->getStatement(__METHOD__ . ".update", self::UPDATE_SQL);
            $success = $stmt->execute([$value, $ttl, $key]);
            return $success && $stmt->rowCount() === 1;
        } catch (PDOException) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null) : bool
    {
        $success = true;
        foreach ($this->values($values) as $key => $value) {
            $success = $this->set($key, $value, $ttl) && $success;
        }
        return $success;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key) : bool
    {
        $key = $this->hashKey($this->key($key));

        try {
            $stmt = $this->getStatement(__METHOD__, self::DELETE_SQL);
            return $stmt->execute([$key]);
        } catch (PDOException) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple(iterable $keys) : bool
    {
        if (empty($keys)) {
            return true;
        }

        $keysArray = array_map([$this, 'hashKey'], is_array($keys) ? $keys : iterator_to_array($keys));

        try {
            $stmt = $this->conn->prepare(sprintf(
                self::MDELETE_SQL,
                implode(',', array_fill(0, count($keysArray), '?'))
            ));
            $stmt->execute($keysArray);
        } catch (PDOException) {
            return false;
        }
        return true;
    }

    /**
     * @inheritdoc
     */
    public function clean() : bool
    {
        try {
            $this->conn->exec(self::CLEAN_SQL);
        } catch (PDOException) {
            return false;
        }
        return true;
    }

    /**
     * @inheritdoc
     */
    public function flush() : bool
    {
        try {
            $this->conn->exec(self::FLUSH_SQL);
        } catch (PDOException) {
            return false;
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function clear() : bool
    {
        return $this->flush();
    }

    /**
     * @inheritDoc
     */
    public function has(string $key) : bool
    {
        $key = $this->hashKey($key);

        try {
            $stmt = $this->getStatement(__METHOD__, self::HAS_SQL);
            $stmt->execute([$key]);
        } catch (PDOException) {
            return false;
        }
        return (bool)$stmt->fetchColumn();
    }

    /**
     * @inheritDoc
     * @throws Exception\CacheException
     */
    public function increment(string $key, int $step = 1, DateInterval|int|null $ttl = null) : int|false
    {
        $step = $this->step($step);

        try {
            $result = $this->conn->beginTransaction();
            if (!$result) {
                return false;
            }

            $current = $this->get($key);
            $next = ($current ?? 0) + $step;
            if ($current !== null) {
                $stmt = $this->getStatement(__METHOD__, self::UPDATE_INCREMENT_SQL);
                $result = $stmt->execute([serialize($next), $key]) && $stmt->rowCount() === 1;
            } else {
                $result = $this->set($key, $next, $ttl);
            }
            if (!$result) {
                $this->conn->rollBack();
                return false;
            }

            $result = $this->conn->commit();
            if (!$result) {
                return false;
            }
            return $next;
        } catch (PDOException) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return false;
        }
    }

    /**
     * @inheritDoc
     * @throws Exception\CacheException
     */
    public function decrement(string $key, int $step = 1, DateInterval|int|null $ttl = null) : int|false
    {
        return $this->increment($key, -$step, $ttl);
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
    private function getStatement(string $method, string $sql) : PDOStatement
    {
        return $this->statements[$method] ??= $this->conn->prepare($sql);
    }

    /**
     * Get a unique hash key when the key is too long
     *
     * @param string $key
     * @return string The key, or the hash of the key parameter if it goes beyond maximum length
     * @private Public for testing
     */
    public static function hashKey(string $key) : string
    {
        return (strlen($key) > self::MAX_KEY_LEN) ? hash('sha256', $key) : $key;
    }
}
