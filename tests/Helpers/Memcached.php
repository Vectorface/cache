<?php

namespace Vectorface\Tests\Cache\Helpers;

/**
 * Unit tests set this up to pretend to be Memcached if memcached isn't loaded.
 */
class Memcached
{
    public const RES_SUCCESS = 0;
    public const RES_FAILURE = 1;
    public const RES_NOTSTORED = 14;
    public const RES_NOTFOUND = 16;
}
