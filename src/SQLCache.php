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
    public const MAX_KEY_LEN = 64;

    /**
     * Statement for flushing all entries from the cache.
     */
    public const FLUSH_SQL = 'DELETE FROM cache';

    /**
     * Statement for deleting expired entries from the cache.
     */
    public const CLEAN_SQL = 'DELETE FROM cache WHERE expires <= UNIX_TIMESTAMP()';

    /**
     * Statement for inserting or updating entries in the cache.
     */
    public const SET_SQL = 'INSERT INTO cache (entry, value, expires) VALUES(?, ?, ?)';

    /**
     * Statement for updating if an entry already exists.
     */
    public const UPDATE_SQL = 'UPDATE cache SET value = ?, expires = ? WHERE entry = ?';

    /**
     * Statement for atomically updating a stored count if an entry already exists.
     */
    public const UPDATE_INCREMENT_SQL = 'UPDATE cache SET value = ? WHERE entry = ?';

    /**
     * Statement for checking if an entry exists
     */
    public const HAS_SQL = 'SELECT COUNT(*) AS num FROM cache WHERE entry = ? AND expires >= UNIX_TIMESTAMP()';

    /**
     * Statement for retrieving an entry from the cache
     */
    public const GET_SQL = 'SELECT value FROM cache WHERE entry = ? AND expires >= UNIX_TIMESTAMP()';

    /**
     * Statement for retrieving entries from the cache (no statement caching)
     */
    public const MGET_SQL = 'SELECT entry, value FROM cache WHERE entry IN(%s) AND expires >= UNIX_TIMESTAMP()';

    /**
     * Statement for deleting an entry from the cache
     */
    public const DELETE_SQL = 'DELETE FROM cache WHERE entry = ?';

    /**
     * Statement for deleting entries from the cache (no statement caching)
     */
    public const MDELETE_SQL = 'DELETE FROM cache WHERE entry IN(%s)';

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
        return $this->fetch($this->hashKey($this->key($key)), false, $default);
    }

    /**
     * Fetch an entry by its (hashed) key, optionally locking the row for the current transaction.
     *
     * @param string $key The hashed key
     * @param bool $lock Lock the row (SELECT ... FOR UPDATE) where the driver supports it
     * @param mixed $default The value to return if there is no entry
     */
    private function fetch(string $key, bool $lock, mixed $default) : mixed
    {
        $lock = $lock && $this->supportsRowLocks();
        try {
            $stmt = $this->getStatement(
                __METHOD__ . ($lock ? '.lock' : ''),
                self::GET_SQL . ($lock ? ' FOR UPDATE' : '')
            );
            $this->execute($stmt, [$key]);
        } catch (PDOException) {
            return $default;
        }
        $result = $stmt->fetchColumn();
        $stmt->closeCursor();
        if (empty($result)) {
            return $default;
        }
        return unserialize($result);
    }

    /**
     * Whether the underlying driver supports SELECT ... FOR UPDATE
     */
    private function supportsRowLocks() : bool
    {
        return in_array($this->conn->getAttribute(PDO::ATTR_DRIVER_NAME), ['mysql', 'pgsql'], true);
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
            $stmt->closeCursor();
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
            return $this->execute($stmt, [$key, $value, $ttl]);
        } catch (PDOException) {
            // Insert can fail if the entry exists; It's normal.
        }

        try {
            // Don't check rowCount: MySQL reports 0 affected rows when the update changes nothing
            $stmt = $this->getStatement(__METHOD__ . ".update", self::UPDATE_SQL);
            return $this->execute($stmt, [$value, $ttl, $key]);
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
            return $this->execute($stmt, [$key]);
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
            $this->execute($stmt, [$key]);
        } catch (PDOException) {
            return false;
        }
        $result = (bool)$stmt->fetchColumn();
        $stmt->closeCursor();
        return $result;
    }

    /**
     * @inheritDoc
     * @throws Exception\CacheException
     */
    public function increment(string $key, int $step = 1, DateInterval|int|null $ttl = null) : int|false
    {
        try {
            $result = $this->conn->beginTransaction();
            if (!$result) {
                return false;
            }

            // Lock the row so concurrent increments serialize on it rather than both reading the same value
            $current = $this->fetch($this->hashKey($this->key($key)), true, null);
            $next = ($current ?? 0) + $step;
            if ($current !== null) {
                $stmt = $this->getStatement(__METHOD__, self::UPDATE_INCREMENT_SQL);
                $result = $this->execute($stmt, [serialize($next), $this->hashKey($this->key($key))])
                    && $stmt->rowCount() === 1;
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
     * Execute a stored statement, resetting it when execution fails so it stays usable.
     *
     * A failed statement may be left mid-execution; on PHP 8.4.27, a pdo_sqlite statement whose execution fails
     * (e.g. a duplicate key on insert) can't be executed again until it is reset.
     *
     * @param PDOStatement $stmt A statement from getStatement()
     * @param array $params The statement's parameters
     * @return bool Whether execution succeeded
     * @throws PDOException When execution fails and the connection throws exceptions
     */
    private function execute(PDOStatement $stmt, array $params) : bool
    {
        try {
            $result = $stmt->execute($params);
        } catch (PDOException $e) {
            $this->reset($stmt);
            throw $e;
        }
        if (!$result) {
            $this->reset($stmt);
        }
        return $result;
    }

    /**
     * Reset a statement after a failed execution, ignoring any error from the reset itself.
     */
    private function reset(PDOStatement $stmt) : void
    {
        try {
            $stmt->closeCursor();
        } catch (PDOException) {
            // The statement is already unusable; the next execute will report why
        }
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
