# Cache
[![Code Coverage](https://scrutinizer-ci.com/g/Vectorface/cache/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/Vectorface/cache/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/vectorface/cache/v/stable.svg)](https://packagist.org/packages/vectorface/cache)
[![License](https://poser.pugx.org/vectorface/cache/license.svg)](https://packagist.org/packages/vectorface/cache)

This is a simple cache library. It exposes several caching mechanisms (with different semantics), along with support for adapting to a PSR-16 compatible interface, and atomic counters. Nothing fancy.

## Interface

The cache interface exposes get and set methods, which do exactly what you'd expect from a cache:

```php
use Vectorface\Cache\PHPCache;

// PHPCache is a trivial array-backed cache.
$cache = new PHPCache();
$cache->get("foo"); // null, because we just created this cache.
$cache->get("foo", "dflt"); // "dflt"; same as above, but with our own default
$cache->set("foo", "bar"); // returns true if set. This cache always succeeds.
$cache->get("foo"); // "bar", because we just set it.
```

The interface supports optional time-to-live (expiry) where supported by the underlying cache type.

The `Cache` interface is a superset of PSR-16's `CacheInterface`: it has the same `get`, `set`, `delete`, `clear`, `has`, `getMultiple`, `setMultiple`, and `deleteMultiple` methods with the same signatures and semantics. It adds `clean`, which removes expired entries, and `flush`, an alias for `clear`. Any cache in this library can be handed to PSR-16 tooling via the `SimpleCacheAdapter` (see below).

## Available Implementations

* `APCCache`: APCu, using the [apcu](https://pecl.php.net/package/APCu) extension
* `MCCache`: Memcache, using the [memcache](https://pecl.php.net/package/memcache) extension. Takes a configured `Memcache` instance.
* `MemcachedCache`: Memcache, using the [memcached](https://pecl.php.net/package/memcached) extension. Takes a configured `Memcached` instance.
* `RedisCache`: Redis, using either the [phpredis](https://github.com/phpredis/phpredis) extension or the [php-redis-client](https://github.com/cheprasov/php-redis-client) library. Takes a connected `Redis` or `RedisClient` instance and an optional key prefix.
* `NullCache`: A blackhole for your data
* `PHPCache`: Stores values in a local variable, for one script execution only.
* `SQLCache`: Values stored in an SQL table, accessed via PDO. Takes a `PDO` instance.
* `TempFileCache`: Store values in temporary files. Does not support atomic counting.
* `TieredCache`: Layer any of the above caches on top of each other to form a hybrid cache. Does not support atomic counting.
* `LogDecorator`: Wraps any of the above and logs each operation to a PSR-3 logger.

## Real-World Use

Why would you want to use this? It makes it almost trivial to switch your underlying cache implementation without any code changes. This can be especially useful for testing.

```php
use Vectorface\Cache\APCCache;
use Vectorface\Cache\PHPCache;
use Vectorface\Cache\TempFileCache;

// The Memcache, Redis, and SQL-backed caches work the same way, but need a client/connection first.
$caches = [new APCCache(), new PHPCache(), new TempFileCache()];
foreach ($caches as $cache) {
    // Look ma! Same interface!
    $cache->set('foo', 'bar');
    $cache->get('foo');
}
```

### Atomic Counters

A common use of caches is to implement atomic counting, i.e. incrementing or decrementing by some amount. Atomicity is important for reliability in distributed environments to avoid race conditions.

Not all cache implementations in this library support atomic counting, because it either isn't possible or doesn't make sense in that context.

```php
use Vectorface\Cache\APCCache;
use Vectorface\Cache\AtomicCounter;

$cache = new APCCache();
assert($cache instanceof AtomicCounter);

// Can increment and decrement by key, defaults to steps of 1
assert($cache->increment("counter") === 1);
assert($cache->increment("counter") === 2);
assert($cache->decrement("counter") === 1);

// Can step by arbitrary amounts
assert($cache->increment("counter", 5) === 6);
assert($cache->decrement("counter", 2) === 4);
```

### Tiered Caching

Another particularly useful feature is the ability to stack caches. You can put fast caches in front of successively slower caches, presumably where the fast caches will have less storage and evict items used less often.

```php
use Vectorface\Cache\APCCache;
use Vectorface\Cache\MCCache;
use Vectorface\Cache\TempFileCache;
use Vectorface\Cache\TieredCache;

$memcache = new Memcache();
$memcache->addServer("127.0.0.1");
$cache = new TieredCache([
    new APCCache(),
    new MCCache($memcache),
    new TempFileCache(),
]);

$cache->get("foo"); // Tries each cache in order and returns the first hit. Returns the default if none hit.
$cache->set("foo", "bar"); // Sets the value in every cache. Succeeds if at least one cache accepted it.
$cache->get("foo"); // Tries each cache in order. The fastest should hit and return quickly.
$cache->delete("foo"); // Deletes from every cache. Succeeds only if every cache succeeded.
```

### Caching Expensive Calls

`CacheHelper::fetch` wraps the common get-or-compute pattern: return the cached value if there is one, otherwise call a callback, cache its result, and return it.

```php
use Vectorface\Cache\APCCache;
use Vectorface\Cache\CacheHelper;

$cache = new APCCache();

// Calls Report::build(2026, 9) only on a cache miss, and caches the result for 600 seconds.
$report = CacheHelper::fetch($cache, "report:2026-09", [Report::class, 'build'], [2026, 9], 600);
```

Results of `null` are treated as a miss and are not cached.

### Logging

`LogDecorator` wraps any cache and logs every operation (including hits, misses, and approximate value sizes) to a PSR-3 logger at the level of your choice. Without a logger it is a transparent pass-through.

```php
use Vectorface\Cache\LogDecorator;
use Vectorface\Cache\PHPCache;

$cache = new LogDecorator(new PHPCache(), $psr3Logger, 'debug');
$cache->set("foo", "bar"); // logged
$cache->get("foo");        // logged, as a HIT
```

### PSR-16 Support

If you need interoperability with other tooling that support PSR-16 SimpleCache, you may use the `SimpleCacheAdapter` class which can wrap any of the cache implementations in this library.

```php
use Psr\SimpleCache\CacheInterface;
use Vectorface\Cache\PHPCache;
use Vectorface\Cache\SimpleCacheAdapter;

$psr16Cache = new SimpleCacheAdapter(new PHPCache());

assert($psr16Cache instanceof CacheInterface);
```
