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
                INSERT INTO access_logs (user_id, event_type, page_path, request_method, ip_address, user_agent, referer)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $userId,
                'page_view',
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

    public static function logIframeOpen(string $userId, string $pageKey, string $pageLabel, string $pagePath): void
    {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare('
                INSERT INTO access_logs (user_id, event_type, page_key, page_label, page_path, request_method, ip_address, user_agent, referer)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $userId,
                'iframe_open',
                self::truncate(self::normalizeKey($pageKey), 100),
                self::truncate(trim($pageLabel), 100),
                self::normalizePagePath($pagePath),
                'IFRAME',
                RateLimiter::getClientIp(),
                self::truncate($_SERVER['HTTP_USER_AGENT'] ?? null, 65535),
                self::truncate($_SERVER['HTTP_REFERER'] ?? null, 500),
            ]);
        } catch (\Throwable $e) {
            error_log('AccessLog::logIframeOpen failed: ' . $e->getMessage());
        }
    }    


    /**
     * 管理画面向け一般アクセスログ一覧取得
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

        $eventType = trim((string) ($filters['event_type'] ?? ''));
        if ($eventType !== '') {
            $where[] = 'al.event_type = ?';
            $params[] = $eventType;
        }

        $pageKey = trim((string) ($filters['page_key'] ?? ''));
        if ($pageKey !== '') {
            $where[] = 'al.page_key LIKE ?';
            $params[] = '%' . $pageKey . '%';
        }

        $pagePath = trim((string) ($filters['page_path'] ?? ''));
        if ($pagePath !== '') {
            $where[] = '(al.page_path LIKE ? OR al.page_label LIKE ?)';
            $params[] = '%' . $pagePath . '%';
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
                COALESCE(al.event_type, 'page_view') AS event_type,
                al.page_key,
                al.page_path,
                al.page_label,
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

    private static function normalizeKey(string $key): string
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', trim($key)) ?: 'unknown';
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
