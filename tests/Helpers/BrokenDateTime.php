<?php

namespace Vectorface\Tests\Cache\Helpers;

use DateTime;

class BrokenDateTime extends DateTime
{
    public function __construct(...$args)
    {
        throw new \Exception("I'm broken for tests!");
    }
}