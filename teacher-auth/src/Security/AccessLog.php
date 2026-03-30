<?php
/**
 * アクセスログクラス
 */

namespace App\Security;

use App\Config\Database;
use DateTimeImmutable;
use PDO;

class AccessLog
{
    /**
     * ポータルアクセスログを記録
     * 記録失敗は呼び出し元動作に影響させない
     */
    public static function log(string $userId, string $pagePath, string $requestMethod = 'GET'): void
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare('
                INSERT INTO access_logs (user_id, page_path, request_method, ip_address, user_agent, referer)
                VALUES (?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $userId,
                self::normalizePagePath($pagePath),
                self::normalizeRequestMethod($requestMethod),
                RateLimiter::getClientIp(),
                self::truncate($_SERVER['HTTP_USER_AGENT'] ?? null, 65535),
                self::truncate($_SERVER['HTTP_REFERER'] ?? null, 500),
            ]);
        } catch (\Throwable $e) {
            error_log('AccessLog::log failed: ' . $e->getMessage());
        }
    }

    /**
     * 管理画面向けアクセスログ一覧
     *
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, per_page: int, pages: int}
     */
    public static function getLogs(array $filters = [], int $page = 1, int $perPage = 50): array
    {
        $db = Database::getConnection();
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = [];
        $params = [];

        $dateFrom = trim((string) ($filters['date_from'] ?? ''));
        if (self::isValidDate($dateFrom)) {
            $where[] = 'al.created_at >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));
        if (self::isValidDate($dateTo)) {
            $nextDate = date('Y-m-d', strtotime($dateTo . ' +1 day'));
            $where[] = 'al.created_at < ?';
            $params[] = $nextDate . ' 00:00:00';
        }

        $userSearch = trim((string) ($filters['user_search'] ?? ''));
        if ($userSearch !== '') {
            $where[] = '(u.name LIKE ? OR u.email LIKE ?)';
            $needle = '%' . $userSearch . '%';
            $params[] = $needle;
            $params[] = $needle;
        }

        $pagePath = trim((string) ($filters['page_path'] ?? ''));
        if ($pagePath !== '') {
            $where[] = 'al.page_path LIKE ?';
            $params[] = '%' . $pagePath . '%';
        }

        $whereSql = empty($where) ? '' : ('WHERE ' . implode(' AND ', $where));

        $countStmt = $db->prepare("
            SELECT COUNT(*)
            FROM access_logs al
            LEFT JOIN users u ON al.user_id = u.id
            {$whereSql}
        ");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $dataStmt = $db->prepare("
            SELECT
                al.id,
                al.created_at,
                al.user_id,
                COALESCE(u.name, '') AS user_name,
                COALESCE(u.email, '') AS user_email,
                al.page_path,
                al.request_method,
                al.ip_address,
                al.user_agent
            FROM access_logs al
            LEFT JOIN users u ON al.user_id = u.id
            {$whereSql}
            ORDER BY al.created_at DESC, al.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $dataStmt->execute($params);

        return [
            'items' => $dataStmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * 古いログを削除（疑似cron）
     * デフォルトで90日以上前のログを削除
     */
    public static function cleanup(int $daysToKeep = 90): void
    {
        if (random_int(1, 100) !== 1) {
            return;
        }

        $db = Database::getConnection();
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));
        $stmt = $db->prepare('
            DELETE FROM access_logs
            WHERE created_at < ?
        ');
        $stmt->execute([$cutoffDate]);
    }

    private static function normalizePagePath(string $pagePath): string
    {
        $trimmed = trim($pagePath);
        if ($trimmed === '') {
            return '/';
        }

        return self::truncate($trimmed, 500) ?? '/';
    }

    private static function normalizeRequestMethod(string $requestMethod): string
    {
        $method = strtoupper(trim($requestMethod));
        if ($method === '') {
            $method = 'GET';
        }
        return self::truncate($method, 10) ?? 'GET';
    }

    private static function truncate(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        if (strlen($value) <= $maxLength) {
            return $value;
        }

        return substr($value, 0, $maxLength);
    }

    private static function isValidDate(string $date): bool
    {
        if ($date === '') {
            return false;
        }

        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }
}
