<?php
/**
 * DevCore Shared Library — StorageInterface.php
 * Every storage driver must implement this contract.
 */
interface StorageInterface {
    /**
     * Upload a file and return its public URL.
     * @param  string $localPath  Absolute path to the temp file (e.g. $_FILES['x']['tmp_name'])
     * @param  string $destination  Path/key inside the bucket/folder (e.g. 'properties/hero.jpg')
     * @return string  Public URL of the uploaded file
     */
    public function upload(string $localPath, string $destination): string;

    /**
     * Delete a file by its storage key or full URL.
     */
    public function delete(string $path): bool;

    /**
     * Get the public URL for a stored path/key.
     */
    public function url(string $path): string;

    /**
     * Check if a file exists.
     */
    public function exists(string $path): bool;
}
