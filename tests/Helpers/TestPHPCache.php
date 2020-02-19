<?php

namespace Vectorface\Tests\Cache\Helpers;

use Vectorface\Cache\PHPCache;

class TestPHPCache extends PHPCache
{
    public $cache = [];

    public function normalizeKeys(callable $fn = null)
    {
        parent::normalizeKeys($fn);
    }
}
