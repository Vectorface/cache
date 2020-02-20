<?php
/**
 * Allow realpath to fail by overriding it in the namespace
 */
namespace Vectorface\Cache;

class FakeRealpath
{
    public static $broken = false;
    public static function realpath(...$args)
    {
        if (static::$broken) {
            return false;
        }

        return \realpath(...$args);
    }
}

function realpath(...$args)
{
    return FakeRealpath::realpath(...$args);
}