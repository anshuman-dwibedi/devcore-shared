<?php
/**
 * DevCore Shared Library — bootstrap.php
 * Include this ONE file at the top of every project entry point
 *
 * Usage:
 *   require_once '../../core/bootstrap.php';
 *   // Now Database, Api, Analytics, QrCode, Realtime are all available
 */

define('DEVCORE_ROOT', __DIR__);

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
    $isDev = (require dirname(__DIR__) . '/config.php')['debug'] ?? false;
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
