<?php
/** @noinspection PhpUsageOfSilenceOperatorInspection */

namespace Vectorface\Cache;

use DateInterval;
use Exception;
use Psr\SimpleCache\InvalidArgumentException;
use Vectorface\Cache\Common\MultipleTrait;
use Vectorface\Cache\Common\PSR16Util;

/**
 * Represents a cache whose entries are stored in temporary files.
 */
class TempFileCache implements Cache
{
    use MultipleTrait, PSR16Util;

    private string $directory;
    private string $extension;

    /**
     * Create a temporary file cache.
     *
     * @param string|null $directory The directory in which cache files should be stored.
     * @param string $extension The extension to be used after the filename to uniquely identify cache files.
     *
     * Note:
     *  - Without a directory argument, the system tempdir will be used (e.g. /tmp/TempFileCache/)
     *  - If given a relative path, it will create that directory within the system tempdir.
     *  - If given an absolute path, it will attempt to use that path as-is. Not recommended.
     * @throws Exception
     */
    public function __construct(string|null $directory = null, string $extension = '.tempcache')
    {
        $this->directory = $this->getTempDir($directory);
        $this->checkAndCreateDir($this->directory);

        $realpath = realpath($this->directory); /* Get rid of extraneous symlinks, ..'s, etc. */
        if (!$realpath) {
            // @codeCoverageIgnoreStart
            throw new Exception("Could not get directory realpath");
            // @codeCoverageIgnoreEnd
        }
        $this->directory = $realpath;

        $this->extension = empty($extension) ? "" : $extension;
    }

    /**
     * Check for a directory's existence and writability, and create otherwise
     *
     * @param string $directory
     * @throws Exception
     */
    private function checkAndCreateDir(string $directory) : void
    {
        if (!file_exists($directory)) {
            if (!@mkdir($directory, 0700, true)) {
                throw new Exception("Directory does not exist, and could not be created: {$directory}");
            }
        } elseif (is_dir($directory)) {
            if (!is_writable($directory)) {
                throw new Exception("Directory is not writable: {$directory}");
            }
        } else {
            throw new Exception("Not a directory: {$directory}");
        }
    }

    /**
     * Generate a consistent temporary directory based on a requested directory name.
     *
     * @param string|null $directory The name or path of a temporary directory.
     * @return string The directory name, resolved to a full path.
     */
    private function getTempDir(string|null $directory) : string
    {
        if (empty($directory)) {
            $classParts = explode("\\", static::class);
            return sys_get_temp_dir()  . '/' . end($classParts);
        }
        if (!str_starts_with($directory, '/')) {
            return sys_get_temp_dir() . '/' . $directory;
        }
        return $directory;
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null) : mixed
    {
        $file = $this->makePath($this->key($key));
        $data = @file_get_contents($file);
        if (!$data) {
            return $default;
        }

        $data = @unserialize($data);
        if (!$data) {
            $this->delete($key); /* Delete corrupted. */
            return $default;
        }

        [$expiry, $value] = $data;
        if ($expiry !== false && ($expiry < microtime(true))) {
            $this->delete($key);
            return $default;
        }

        return $value;
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null) : bool
    {
        $ttl = $this->ttl($ttl);
        $data = [$ttl ? microtime(true) + $ttl : false, $value];
        return @file_put_contents($this->makePath($this->key($key)), serialize($data)) !== false;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $key) : bool
    {
        return @unlink($this->makePath($this->key($key)));
    }

    /**
     * @inheritDoc
     */
    public function clean() : bool
    {
        if (!($files = $this->getCacheFiles())) {
            return false;
        }

        foreach ($files as $file) {
            $key = basename($file, $this->extension);
            try {
                // Automatically deletes if expired
                $this->get($key);
                // @codeCoverageIgnoreStart
            } catch (InvalidArgumentException) {
                return false;
                // @codeCoverageIgnoreEnd
            }
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function flush() : bool
    {
        if (($files = $this->getCacheFiles()) === false) {
            return false;
        }

        $result = true;
        foreach ($files as $file) {
            $result = $result && @unlink($file);
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    public function clear() : bool
    {
        return $this->flush();
    }

    /**
     * @inheritDoc
     */
    public function has(string $key) : bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Creates a file path in the form directory/key.extension
     *
     * @param  string $key the key of the cached element
     * @return string The file path to the cached element's enclosing file.
     */
    private function makePath(string $key) : string
    {
        return $this->directory . "/" . hash("sha224", $key) . $this->extension;
    }

    /**
     * Finds all files with the cache extension in the cache directory
     *
     * @return array|false Returns an array of filenames that represent cached entries.
     */
    private function getCacheFiles() : array|false
    {
        if (!($files = @scandir($this->directory, 1))) {
            return false;
        }

        $negExtLen = -1 * strlen($this->extension);
        $return = [];
        foreach ($files as $file) {
            if (substr($file, $negExtLen) === $this->extension) {
                $return[] = $this->directory . '/' . $file;
            }
        }
        return $return;
    }

    /**
     * Destroy this cache; Clear everything.
     *
     * Any operations on the cache after this operation are invalid, and their behavior will be undefined.
     *
     * @return bool True if the cache was flushed and the directory deleted.
     */
    public function destroy() : bool
    {
        return $this->flush() && @rmdir($this->directory);
    }
}
