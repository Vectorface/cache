# Cache
[![Build Status](https://travis-ci.org/Vectorface/cache.svg?branch=master)](https://travis-ci.org/Vectorface/cache)
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

The interface supports optional time-to-live (expiry) where supported by the underlying cache type. The interface also provides `delete`, `clean`, and `flush` methods to delete one entry, all expired entries, and all entries (respectively).

## Available Implementations

* `APCCache`: APC or APCu.
* `MCCache`: Memcache
* `NullCache`: A blackhole for your data
* `PHPCache`: Stores values in a local variable, for one script execution only.
* `SQLCache`: Values stored in an SQL table, accessed via PDO.
* `TempFileCache`: Store values in temporary files. Does not support atomic counting.
* `TieredCache`: Layer any of the above caches on top of each other to form a hybrid cache. Does not support atomic counting.

## Real-World Use

Why would you want to use this? It makes it almost trivial to switch your underlying cache implementation without any code changes. This can be especially useful for testing.

```php
use Vectorface\Cache\APCCache;
use Vectorface\Cache\PHPCache;
use Vectorface\Cache\TempFileCache;

// Memcache and SQL-based caches also work, but aren't as good as examples.
$caches = [new APCCache(), new PHPCache(), new TempFileCache()];
foreach ($caches as $cache) {
	// Look ma! Same interface!
	$cache->set('foo', 'bar');
	$cache->get('foo');
}
```

### Atomic Counters

A common use of caches are to implement atomic counting, i.e. incrementing or decrementing by some amount. Atomicity is important for reliability in distributed environments to avoid race conditions.

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

$cache->get("foo"); // Tries all caches in sequence until one succeeds. Fails if none succeed.
$cache->set("foo", "bar"); // Sets a value in all caches.
$cache->get("foo"); // Tries all caches in sequence. The fastest should succeed and return quickly.
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
