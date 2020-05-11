# Cache
[![Build Status](https://travis-ci.org/Vectorface/cache.svg?branch=master)](https://travis-ci.org/Vectorface/cache)
[![Code Coverage](https://scrutinizer-ci.com/g/Vectorface/cache/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/Vectorface/cache/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/vectorface/cache/v/stable.svg)](https://packagist.org/packages/vectorface/cache)
[![License](https://poser.pugx.org/vectorface/cache/license.svg)](https://packagist.org/packages/vectorface/cache)

This is a simple cache library. It exposes several different caching mechanisms (with different semantics) under a common PSR-16 compatible interface. Nothing fancy.

## Interface

The cache interface exposes get and set methods, which do exactly what you'd expect from a cache:

```php
// PHPCache is a trivial array-backed cache.
$cache = new \Vectorface\Cache\PHPCache();
$cache->get("foo"); // null, because we just created this cache.
$cache->get("foo", "dflt"); // "dflt"; same as above, but with our own default
$cache->set("foo", "bar"); // returns true if set. This cache always succeeds.
$cache->get("foo"); // "bar", because we just set it.
```

The interface supports optional time-to-live (expiry) where supported by the underlying cache type. The interface also provides `delete`, `clean`, and `flush` methods to delete one entry, all expired entries, and all entries (respectively).

## Available Implementations

* APCCache: APC or APCu.
* MCCache: Memcache
* NullCache: A blackhole for your data
* PHPCache: Stores values in a local variable, for one script execution only.
* SQLCache: Values stored in an SQL table, accessed via PDO.
* TempFileCache: Store values in temporary files.
* TieredCache: Layer any of the above caches on top of each other to form a hybrid cache.

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
