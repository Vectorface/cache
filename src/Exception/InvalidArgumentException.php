<?php


namespace Vectorface\Cache\Exception;

use InvalidArgumentException as ParentInvalidArgumentException;
use Psr\SimpleCache\InvalidArgumentException as SimpleCacheInvalidArgumentException;

class InvalidArgumentException extends ParentInvalidArgumentException implements SimpleCacheInvalidArgumentException
{
}