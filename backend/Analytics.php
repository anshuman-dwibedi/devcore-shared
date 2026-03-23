<?php
/**
 * DevCore Shared Library — Analytics.php
 * Reusable analytics queries used by all 4 projects
 */
class Analytics {
    private Database $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /**
     * Count rows grouped by date for the last N days
     * Returns: [['date' => '2024-01-01', 'count' => 12], ...]
     */
    public function countByDay(string $table, string $dateCol = 'created_at', int $days = 30): array {
        return $this->db->fetchAll("
            SELECT DATE($dateCol) as date, COUNT(*) as count
            FROM $table
            WHERE $dateCol >= DATE_SUB(NOW(), INTERVAL $days DAY)
            GROUP BY DATE($dateCol)
            ORDER BY date ASC
        ");
    }

    /**
     * Sum a numeric column grouped by date
     */
    public function sumByDay(string $table, string $col, string $dateCol = 'created_at', int $days = 30): array {
        return $this->db->fetchAll("
            SELECT DATE($dateCol) as date, SUM($col) as total
            FROM $table
            WHERE $dateCol >= DATE_SUB(NOW(), INTERVAL $days DAY)
            GROUP BY DATE($dateCol)
            ORDER BY date ASC
        ");
    }

    /**
     * Top N items by count (e.g. top products, top menu items)
     */
    public function topItems(string $table, string $groupCol, string $countCol = '*', int $limit = 10): array {
        return $this->db->fetchAll("
            SELECT $groupCol, COUNT($countCol) as count
            FROM $table
            GROUP BY $groupCol
            ORDER BY count DESC
            LIMIT $limit
        ");
    }

    /**
     * Quick KPI summary (total, today, this week, this month)
     */
    public function kpi(string $table, string $dateCol = 'created_at'): array {
        $row = $this->db->fetchOne("
            SELECT
                COUNT(*) as total,
                SUM(DATE($dateCol) = CURDATE()) as today,
                SUM($dateCol >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as this_week,
                SUM($dateCol >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as this_month
            FROM $table
        ");
        return $row ?: ['total'=>0,'today'=>0,'this_week'=>0,'this_month'=>0];
    }

    /**
     * Real-time count (last N minutes) — used for live dashboards
     */
    public function recentCount(string $table, string $dateCol = 'created_at', int $minutes = 5): int {
        $row = $this->db->fetchOne("
            SELECT COUNT(*) as cnt FROM $table
            WHERE $dateCol >= DATE_SUB(NOW(), INTERVAL $minutes MINUTE)
        ");
        return (int)($row['cnt'] ?? 0);
    }
}
