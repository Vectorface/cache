<?php

namespace Vectorface\Cache;

/**
 * Represents a cache whose entries are stored in temporary files.
 */
class TempFileCache implements Cache
{
    private $directory;
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
     */
    public function __construct($directory = null, $extension = '.tempcache')
    {
        $this->directory = $this->getTempDir($directory);

        if (!file_exists($this->directory)) {
            if (!@mkdir($this->directory, 0700, true)) {
                throw new \Exception("Directory does not exist, and could not be created: {$this->directory}");
            }
        } elseif (is_dir($this->directory)) {
            if (!is_writable($this->directory)) {
                throw new \Exception("Directory is not writable: {$this->directory}");
            }
        } else {
            throw new \Exception("Not a directory: {$this->directory}");
        }

        $this->directory = realpath($this->directory); /* Get rid of extraneous symlinks, ..'s, etc. */
        $this->extension = empty($extension) ? "" : (string)$extension;
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
            $classParts = explode("\\", get_called_class());
            return sys_get_temp_dir()  . '/' . end($classParts);
        } elseif (strpos($directory, '/') !== 0) {
            return sys_get_temp_dir() . '/' . $directory;
        } else {
            return $directory;
        }
    }

    /*y
     * Fetch a cache entry by key.
     *
     * @param String $key The key for the entry to fetch
     * @param mixed $default Default value to return if the key does not exist.
     * @return mixed The value stored in the cache for $key, or false on failure
     */
    public function get($key, $default = null)
    {
        $file = $this->makePath($key);
        if (!($data = @file_get_contents($file))) {
            return $default;
        }

        if (!($data = @unserialize($data))) {
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
     * Set an entry in the cache.
     *
     * @param String $key The key/index for the cache entry
     * @param mixed $value The item to store in the cache
     * @param int $ttl The time to live (or expiry) of the cached item. Not all caches honor the TTL.
     * @return bool True if the value was successfully stored, or false otherwise.
     */
    public function set($key, $value, $ttl = false)
    {
        $data = [$ttl ? microtime(true) + $ttl : false, $value];
        return @file_put_contents($this->makePath($key), serialize($data)) !== false;
    }

    /**
     * Delete an entry in the cache by key regaurdless of TTL
     *
     * @param string $key A key to delete from the cache.
     * @return bool True if the cache entry was successfully deleted, false otherwise.
     */
    public function delete($key)
    {
        return @unlink($this->makePath($key));
    }

    /**
     * Manually clean out entries older than their TTL
     *
     * @return bool Returns true if the cache directory was cleaned.
     */
    public function clean()
    {
        if (!($files = $this->getCacheFiles())) {
            return false;
        }

        foreach ($files as $file) {
            $key = basename($file, $this->extension);
            $this->get($key); // Automatically deletes if expired
        }
        return true;
    }

    /**
     * Clear the cache
     *
     * @return bool Returns true if all files were flushed.
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
     * @return Array Returns an array of filenames that represent cached entries.
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
