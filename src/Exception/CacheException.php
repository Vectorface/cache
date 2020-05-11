<?php

namespace Vectorface\Cache\Exception;

use Exception;
use Psr\SimpleCache\CacheException as SimpleCacheException;

class CacheException extends Exception implements SimpleCacheException
{
}
