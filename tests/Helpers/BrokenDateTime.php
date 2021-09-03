<?php

namespace Vectorface\Tests\Cache\Helpers;

use DateTime;
use Exception;

class BrokenDateTime extends DateTime
{
    /**
     * @noinspection PhpMissingParentConstructorInspection
     * @noinspection PhpUnusedParameterInspection
     * @param array $args
     * @throws Exception
     */
    public function __construct(...$args)
    {
        throw new Exception("I'm broken for tests!");
    }
}
