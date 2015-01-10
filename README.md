#Cache
[![Build Status](https://travis-ci.org/Vectorface/cache.svg?branch=master)](https://travis-ci.org/Vectorface/cache)
[![Code Coverage](https://scrutinizer-ci.com/g/Vectorface/cache/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/Vectorface/cache/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/vectorface/cache/v/stable.svg)](https://packagist.org/packages/vectorface/cache)
[![License](https://poser.pugx.org/vectorface/cache/license.svg)](https://packagist.org/packages/vectorface/cache)

This is a simple cache library. It exposes several different caching mechanisms (with different semantics) under a common interface. Nothing fancy.

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

