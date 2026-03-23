<?php
/**
 * DevCore Storage Driver — LocalStorage.php
 * Stores files on the local server filesystem.
 *
 * Config keys used:
 *   storage.local.root      Absolute path to uploads folder  e.g. /var/www/html/uploads
 *   storage.local.base_url  Public URL prefix                e.g. https://mysite.com/uploads
 */
class LocalStorage implements StorageInterface {
    private string $root;
    private string $baseUrl;

    public function __construct(array $config) {
        $this->root    = rtrim($config['storage']['local']['root'] ?? __DIR__ . '/../../../../uploads', '/');
        $this->baseUrl = rtrim($config['storage']['local']['base_url'] ?? '/uploads', '/');
    }

    public function upload(string $localPath, string $destination): string {
        $destination = ltrim($destination, '/');
        $target      = $this->root . '/' . $destination;

        // Create directory if needed
        $dir = dirname($target);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (!move_uploaded_file($localPath, $target)) {
            // fallback for non-uploaded files (e.g. in tests)
            if (!copy($localPath, $target)) {
                throw new RuntimeException("LocalStorage: could not save file to $target");
            }
        }

        return $this->url($destination);
    }

    public function delete(string $path): bool {
        $full = $this->root . '/' . ltrim($this->stripBaseUrl($path), '/');
        return file_exists($full) ? unlink($full) : true;
    }

    public function url(string $path): string {
        return $this->baseUrl . '/' . ltrim($path, '/');
    }

    public function exists(string $path): bool {
        return file_exists($this->root . '/' . ltrim($this->stripBaseUrl($path), '/'));
    }

    private function stripBaseUrl(string $path): string {
        // Accept either a full URL or a bare path
        if (str_starts_with($path, $this->baseUrl)) {
            return substr($path, strlen($this->baseUrl));
        }
        return $path;
    }
}
