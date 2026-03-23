<?php
/**
 * DevCore Shared Library — Auth.php
 * Simple session-based auth shared across all projects
 */
class Auth {
    public static function login(array $user): void {
        $_SESSION['dc_user'] = [
            'id'    => $user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'] ?? 'user',
        ];
    }

    public static function logout(): void {
        unset($_SESSION['dc_user']);
        session_destroy();
    }

    public static function check(): bool {
        return isset($_SESSION['dc_user']);
    }

    public static function user(): ?array {
        return $_SESSION['dc_user'] ?? null;
    }

    public static function id(): ?int {
        return isset($_SESSION['dc_user']) ? (int)$_SESSION['dc_user']['id'] : null;
    }

    public static function role(): ?string {
        return $_SESSION['dc_user']['role'] ?? null;
    }

    public static function requireLogin(string $redirect = '/login.php'): void {
        if (!self::check()) {
            header("Location: $redirect");
            exit;
        }
    }

    public static function requireRole(string $role, string $redirect = '/login.php'): void {
        self::requireLogin($redirect);
        if (self::role() !== $role) {
            header("Location: $redirect");
            exit;
        }
    }

    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
}
