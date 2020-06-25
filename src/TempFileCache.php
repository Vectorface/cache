<?php
/** @noinspection PhpUsageOfSilenceOperatorInspection */

namespace Vectorface\Cache;

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

    /**
     * @var string
     */
    private $directory;

    /**
     * @var string
     */
    private $extension;

    /**
     * Create a temporary file cache.
     *
     * @param string $directory The directory in which cache files should be stored.
     * @param string $extension The extension to be used after the filename to uniquely identify cache files.
     *
     * Note:
     *  - Without a directory argument, the system tempdir will be used (e.g. /tmp/TempFileCache/)
     *  - If given a relative path, it will create that directory within the system tempdir.
     *  - If given an absolute path, it will attempt to use that path as-is. Not recommended.
     * @throws Exception
     */
    public function __construct($directory = null, $extension = '.tempcache')
    {
        $this->directory = $this->getTempDir($directory);
        $this->checkAndCreateDir($this->directory);

        $realpath = realpath($this->directory); /* Get rid of extraneous symlinks, ..'s, etc. */
        if (!$realpath) {
            throw new Exception("Could not get directory realpath");
        }
        $this->directory = $realpath;

        $this->extension = empty($extension) ? "" : (string)$extension;
    }

    /**
     * Check for a directory's existence and writability, and create otherwise
     *
     * @param string $directory
     * @throws Exception
     */
    private function checkAndCreateDir($directory)
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
     * @param string $directory The name or path of a temporary directory.
     * @return string The directory name, resolved to a full path.
     */
    private function getTempDir($directory)
    {
        if (empty($directory) || !is_string($directory)) {
            $classParts = explode("\\", static::class);
            return sys_get_temp_dir()  . '/' . end($classParts);
        } elseif (strpos($directory, '/') !== 0) {
            return sys_get_temp_dir() . '/' . $directory;
        } else {
            return $directory;
        }
    }

    /**
     * @inheritDoc
     */
    public function get($key, $default = null)
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

        list($expiry, $value) = $data;
        if ($expiry !== false && ($expiry < microtime(true))) {
            $this->delete($key);
            return $default;
        }

        return $value;
    }

    /**
     * @inheritDoc
     */
    public function set($key, $value, $ttl = null)
    {
        $ttl = $this->ttl($ttl);
        $data = [$ttl ? microtime(true) + $ttl : false, $value];
        return @file_put_contents($this->makePath($this->key($key)), serialize($data)) !== false;
    }

    /**
     * @inheritDoc
     */
    public function delete($key)
    {
        return @unlink($this->makePath($this->key($key)));
    }

    /**
     * @inheritDoc
     */
    public function clean()
    {
        if (!($files = $this->getCacheFiles())) {
            return false;
        }

        foreach ($files as $file) {
            $key = basename($file, $this->extension);
            try {
                // Automatically deletes if expired
                $this->get($key);
            } catch (InvalidArgumentException $e) {
                return false;
            }
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function flush()
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
    public function clear()
    {
        return $this->flush();
    }

    /**
     * @inheritDoc
     */
    public function has($key)
    {
        return $this->get($key, null) !== null;
    }

    /**
     * Creates a file path in the form directory/key.extension
     *
     * @param  String $key the key of the cached element
     * @return String The file path to the cached element's enclosing file.
     */
    private function makePath($key)
    {
        return $this->directory . "/" . hash("sha224", $key) . $this->extension;
    }

    /**
     * Finds all files with the cache extension in the cache directory
     *
     * @return array|false Returns an array of filenames that represent cached entries.
     */
    private function getCacheFiles()
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
    public function destroy()
    {
        return $this->flush() && @rmdir($this->directory);
    }
}
