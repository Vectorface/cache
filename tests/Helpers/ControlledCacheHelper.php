<?php

namespace Vectorface\Tests\Cache\Helpers;

use Vectorface\Cache\CacheHelper;

/**
 * A CacheHelper with a controllable clock and random source, for deterministic tests.
 */
class ControlledCacheHelper extends CacheHelper
{
    public static float $now = 1000000.0;

    /** @var float[] Sequence of "random" values to return; the last one repeats forever. */
    public static array $random = [1.0];

    protected static function time() : float
    {
        return static::$now;
    }

    protected static function random() : float
    {
        return count(static::$random) > 1 ? array_shift(static::$random) : static::$random[0];
    }
}
