<?php

namespace Vectorface\Tests\Cache\Helpers;

/**
 * Allow realpath to fail by overriding it in the namespace
 */
class FakeRealpath
{
    /** @var bool */
    public static bool $broken = false;

    /**
     * @param mixed ...$args
     * @return bool|false|string
     */
    public static function realpath(...$args) : bool|string
    {
        if (static::$broken) {
            return false;
        }

        return \realpath(...$args);
    }
}

/**
 * @param mixed ...$args
 * @return bool|false|string
 */
function realpath(...$args) : bool|string
{
    return FakeRealpath::realpath(...$args);
}
