<?php

namespace Vectorface\Tests\Cache\Helpers;

/**
 * Unit tests set this up to pretend to be Memcached if memcached isn't loaded.
 */
class Memcached
{
    const RES_SUCCESS = 0;
    const RES_FAILURE = 1;
    const RES_NOTSTORED = 14;
    const RES_NOTFOUND = 16;
}
