<?php

namespace Vectorface\Cache;

class FakeAPC
{
    public static $cache = array();
}

function apc_fetch($key)
{
    return isset(FakeAPC::$cache[$key]) ? FakeAPC::$cache[$key] : false;
}

function apc_store($key, $value, $ttl = 0)
{
    FakeAPC::$cache[$key] = $value;
    return true;
}

function apc_delete($key)
{
    unset(FakeAPC::$cache[$key]);
    return true;
}

function apc_clear_cache($arg = null)
{
    FakeAPC::$cache = array();
    return true;
}
