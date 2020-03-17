<?php

namespace Vectorface\Cache;

use DateInterval;
use Psr\SimpleCache\CacheInterface;

/**
 * Cache: A common interface to various types of caches
 */
interface Cache extends CacheInterface
{
    /**
     * Manually clean out entries older than their TTL
     *
     * @return bool True if successful, false otherwise.
     */
    public function clean();

    /**
     * Clear the cache. Equivalent to CacheInterface::clean()
     *
     * @return bool True if successful, false otherwise.
     */
    public function flush();
}
