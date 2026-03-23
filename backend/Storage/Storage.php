<?php
/**
 * DevCore Shared Library — Storage.php
 * Factory + facade. Returns the correct driver based on config.
 *
 * Usage (anywhere in any project):
 *
 *   $url = Storage::upload($_FILES['image']['tmp_name'], 'properties/hero.jpg');
 *   Storage::delete('properties/hero.jpg');
 *   $url = Storage::url('properties/hero.jpg');
 *
 * Config key:
 *   storage.driver   =>  'local' | 's3' | 'r2'
 */
class Storage {
    private static ?StorageInterface $instance = null;

    public static function driver(): StorageInterface {
        if (self::$instance !== null) return self::$instance;

        $config = require dirname(__DIR__, 2) . '/config.php';
        $driver = $config['storage']['driver'] ?? 'local';

        self::$instance = match ($driver) {
            'local' => new LocalStorage($config),
            's3'    => new S3Storage($config),
            'r2'    => new R2Storage($config),
            default => throw new InvalidArgumentException("Storage: unknown driver '$driver'"),
        };

        return self::$instance;
    }

    // ── Facade shortcuts ───────────────────────────────────────

    /**
     * Upload a file. Returns public URL.
     *
     * @param  string $tmpPath     Temp file path (from $_FILES['x']['tmp_name'])
     * @param  string $destination Storage path e.g. 'menu/burger.jpg'
     *                             Pass null to auto-generate a unique name.
     */
    public static function upload(string $tmpPath, ?string $destination = null): string {
        if ($destination === null) {
            $ext         = pathinfo($tmpPath, PATHINFO_EXTENSION) ?: 'jpg';
            $destination = 'uploads/' . uniqid('', true) . '.' . $ext;
        }
        return self::driver()->upload($tmpPath, $destination);
    }

    /**
     * Upload from an $_FILES entry. Validates type + size, then uploads.
     *
     * @param  array  $file        $_FILES['fieldname']
     * @param  string $folder      Destination folder e.g. 'properties'
     * @param  array  $allowedMime Allowed MIME types
     * @param  int    $maxBytes    Max file size in bytes (default 5 MB)
     * @return string Public URL
     */
    public static function uploadFile(
        array  $file,
        string $folder      = 'uploads',
        array  $allowedMime = ['image/jpeg','image/png','image/webp','image/gif'],
        int    $maxBytes    = 5 * 1024 * 1024
    ): string {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('File upload error code: ' . $file['error']);
        }
        if ($file['size'] > $maxBytes) {
            throw new RuntimeException('File exceeds maximum size of ' . round($maxBytes / 1048576) . ' MB');
        }
        $mime = mime_content_type($file['tmp_name']);
        if (!in_array($mime, $allowedMime, true)) {
            throw new RuntimeException("File type '$mime' is not allowed");
        }

        $ext         = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'bin';
        $destination = rtrim($folder, '/') . '/' . uniqid('', true) . '.' . strtolower($ext);

        return self::driver()->upload($file['tmp_name'], $destination);
    }

    public static function delete(string $path): bool {
        return self::driver()->delete($path);
    }

    public static function url(string $path): string {
        return self::driver()->url($path);
    }

    public static function exists(string $path): bool {
        return self::driver()->exists($path);
    }

    /**
     * Swap driver at runtime (useful for testing)
     */
    public static function setDriver(StorageInterface $driver): void {
        self::$instance = $driver;
    }
}
