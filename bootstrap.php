<?php
/**
 * DevCore Shared Library — bootstrap.php
 * Include this ONE file at the top of every project entry point
 *
 * Usage:
 *   require_once '../../core/bootstrap.php';
 *   // Now Database, Api, Analytics, QrCode, Realtime are all available
 */

if (!defined('DEVCORE_ROOT')) {
    define('DEVCORE_ROOT', __DIR__);
}

// Optional Composer autoload for third-party packages (Dotenv, etc.)
$projectVendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($projectVendorAutoload)) {
    require_once $projectVendorAutoload;
}

// Load project .env when Dotenv is available
if (class_exists('Dotenv\\Dotenv')) {
    try {
        Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
    } catch (Throwable $e) {
        error_log('DevCore dotenv load warning: ' . $e->getMessage());
    }
}

if (!function_exists('devcore_env')) {
    function devcore_env(string $key, mixed $default = null): mixed {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }

        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }

        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }

        return $default;
    }
}

if (!function_exists('devcore_config')) {
    function devcore_config(): array {
        static $config = null;

        if ($config !== null) {
            return $config;
        }

        $configPath = dirname(__DIR__) . '/config.php';
        $base = is_file($configPath) ? (require $configPath) : [];
        $config = is_array($base) ? $base : [];

        $envMap = [
            'db_host' => 'DB_HOST',
            'db_name' => 'DB_NAME',
            'db_user' => 'DB_USER',
            'db_pass' => 'DB_PASS',
            'app_name' => 'APP_NAME',
            'app_url' => 'APP_URL',
            'api_secret' => 'API_SECRET',
        ];

        foreach ($envMap as $configKey => $envKey) {
            $envVal = devcore_env($envKey, null);
            if ($envVal !== null && $envVal !== '') {
                $config[$configKey] = $envVal;
            }
        }

        $debugEnv = devcore_env('DEBUG', null);
        if ($debugEnv !== null && $debugEnv !== '') {
            $config['debug'] = filter_var($debugEnv, FILTER_VALIDATE_BOOLEAN);
        }

        $storageDriver = devcore_env('STORAGE_DRIVER', null);
        if ($storageDriver !== null && $storageDriver !== '') {
            $config['storage']['driver'] = $storageDriver;
        }

        return $config;
    }
}

// Auto-load all core classes
spl_autoload_register(function (string $class): void {
    $map = [
        // Core
        'Database'         => DEVCORE_ROOT . '/backend/Database.php',
        'Api'              => DEVCORE_ROOT . '/backend/Api.php',
        'Analytics'        => DEVCORE_ROOT . '/backend/Analytics.php',
        'QrCode'           => DEVCORE_ROOT . '/backend/QrCode.php',
        'Auth'             => DEVCORE_ROOT . '/backend/Auth.php',
        'Validator'        => DEVCORE_ROOT . '/backend/Validator.php',
        // Storage — interface first, then drivers, then facade
        'StorageInterface' => DEVCORE_ROOT . '/backend/Storage/StorageInterface.php',
        'LocalStorage'     => DEVCORE_ROOT . '/backend/Storage/LocalStorage.php',
        'S3Storage'        => DEVCORE_ROOT . '/backend/Storage/S3Storage.php',
        'R2Storage'        => DEVCORE_ROOT . '/backend/Storage/R2Storage.php',
        'Storage'          => DEVCORE_ROOT . '/backend/Storage/Storage.php',
    ];
    if (isset($map[$class])) require_once $map[$class];
});

// Global error handler — returns JSON errors in API context
set_exception_handler(function (Throwable $e): void {
    $config = function_exists('devcore_config') ? devcore_config() : [];
    $isDev = (bool)($config['debug'] ?? false);
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'status'  => 'error',
        'message' => $isDev ? $e->getMessage() : 'Internal server error',
        'file'    => $isDev ? $e->getFile() . ':' . $e->getLine() : null,
    ]);
    exit;
});

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
