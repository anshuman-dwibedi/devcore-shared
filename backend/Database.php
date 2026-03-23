<?php
/**
 * DevCore Shared Library — Database.php
 * Singleton PDO wrapper shared across all 4 projects
 */
class Database {
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct() {
        $config = require dirname(__DIR__, 2) . '/config.php';
        $dbName = self::resolveDatabaseName($config);
        $dsn = "mysql:host={$config['db_host']};dbname={$dbName};charset=utf8mb4";
        $this->pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    public static function getInstance(): Database {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public function query(string $sql, array $params = []): PDOStatement {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetchAll(string $sql, array $params = []): array {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchOne(string $sql, array $params = []): array|false {
        return $this->query($sql, $params)->fetch();
    }

    public function insert(string $table, array $data): string {
        $cols   = implode(',', array_keys($data));
        $ph     = implode(',', array_fill(0, count($data), '?'));
        $this->query("INSERT INTO $table ($cols) VALUES ($ph)", array_values($data));
        return $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $wp = []): int {
        $set  = implode(',', array_map(fn($k) => "$k=?", array_keys($data)));
        return $this->query("UPDATE $table SET $set WHERE $where", [...array_values($data), ...$wp])->rowCount();
    }

    public function delete(string $table, string $where, array $params = []): int {
        return $this->query("DELETE FROM $table WHERE $where", $params)->rowCount();
    }

    public function beginTransaction(): void { $this->pdo->beginTransaction(); }
    public function commit(): void          { $this->pdo->commit(); }
    public function rollback(): void        { $this->pdo->rollBack(); }

    private static function resolveDatabaseName(array $config): string {
        if (!empty($config['db_name'])) {
            return (string)$config['db_name'];
        }

        if (!empty($config['db_projects']) && is_array($config['db_projects'])) {
            $script = $_SERVER['SCRIPT_NAME'] ?? '';
            if (preg_match('~/projects/([^/]+)/~', $script, $m)) {
                $projectKey = $m[1];
                if (!empty($config['db_projects'][$projectKey])) {
                    return (string)$config['db_projects'][$projectKey];
                }
            }
        }

        if (!empty($config['db_default'])) {
            return (string)$config['db_default'];
        }

        throw new RuntimeException('Database name is not configured. Set db_name (single) or db_projects/db_default (multi).');
    }
}
