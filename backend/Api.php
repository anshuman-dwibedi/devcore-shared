<?php
/**
 * DevCore Shared Library — Api.php
 * Standardized JSON response helper for all projects
 */
class Api {
    public static function success(mixed $data = null, string $message = 'OK', int $code = 200): void {
        self::send(['status' => 'success', 'message' => $message, 'data' => $data], $code);
    }

    public static function error(string $message = 'Error', int $code = 400, mixed $errors = null): void {
        self::send(['status' => 'error', 'message' => $message, 'errors' => $errors], $code);
    }

    public static function paginated(array $items, int $total, int $page, int $perPage): void {
        self::send([
            'status' => 'success',
            'data'   => $items,
            'meta'   => [
                'total'       => $total,
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => ceil($total / $perPage),
            ]
        ]);
    }

    private static function send(array $payload, int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /** Read JSON body from incoming request */
    public static function body(): array {
        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?? [];
    }

    /** Simple bearer token auth check */
    public static function requireAuth(): void {
        $configPath = dirname(__DIR__, 2) . '/config.php';
        $config = function_exists('devcore_config')
            ? devcore_config()
            : (is_file($configPath) ? (require $configPath) : []);

        if (!is_array($config)) {
            $config = [];
        }

        $secret = (string)($config['api_secret'] ?? '');

        // Treat empty or placeholder secrets as misconfiguration.
        if ($secret === '' || str_contains($secret, 'change-this')) {
            self::error('API secret is not configured', 500);
        }

        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $token = $headers['Authorization']
            ?? $headers['authorization']
            ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');

        if (!is_string($token) || !str_starts_with($token, 'Bearer ')) {
            self::error('Unauthorized', 401);
        }

        $provided = trim(substr($token, 7));
        if (!hash_equals($secret, $provided)) {
            self::error('Unauthorized', 401);
        }
    }
}
