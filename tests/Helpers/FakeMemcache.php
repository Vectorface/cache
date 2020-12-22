<?php
/** @noinspection PhpComposerExtensionStubsInspection */

namespace Vectorface\Tests\Cache\Helpers;

if (version_compare(phpversion(), '8.0.0', '>=') && class_exists("MemcachePool")) {
    class FakeMemcache extends FakeMemcachePHP80 {}
} else {
    class FakeMemcache extends FakeMemcachePHP73 {}
}
