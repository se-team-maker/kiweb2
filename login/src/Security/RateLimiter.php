<?php
/**
 * レート制限クラス（MySQLベース）
 */

namespace App\Security;

use App\Config\Database;
use PDO;

class RateLimiter
{
    /**
     * レート制限をチェック
     * 
     * @return bool ブロックされている場合はtrue
     */
    public static function isBlocked(string $identifier, string $type = 'ip'): bool
    {
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT blocked_until
            FROM login_attempts
            WHERE identifier = ? AND identifier_type = ?
        ');
        $stmt->execute([$identifier, $type]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            return false;
        }

        if ($record['blocked_until'] === null) {
            return false;
        }

        return strtotime($record['blocked_until']) > time();
    }

    /**
     * 試行を記録
     */
    public static function recordAttempt(string $identifier, string $type = 'ip'): void
    {
        $db = Database::getConnection();
        $maxAttempts = (int) ($_ENV['RATE_LIMIT_ATTEMPTS'] ?? 5);
        $window = (int) ($_ENV['RATE_LIMIT_WINDOW'] ?? 900);
        $blockDuration = (int) ($_ENV['RATE_LIMIT_BLOCK_DURATION'] ?? 1800);

        // 既存のレコードを取得
        $stmt = $db->prepare('
            SELECT id, attempt_count, first_attempt_at
            FROM login_attempts
            WHERE identifier = ? AND identifier_type = ?
        ');
        $stmt->execute([$identifier, $type]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record) {
            // 新規レコード
            $stmt = $db->prepare('
                INSERT INTO login_attempts (identifier, identifier_type, attempt_count)
                VALUES (?, ?, 1)
            ');
            $stmt->execute([$identifier, $type]);
            return;
        }

        // ウィンドウ期間を過ぎていたらリセット
        if (strtotime($record['first_attempt_at']) + $window < time()) {
            $now = date('Y-m-d H:i:s');
            $stmt = $db->prepare('
                UPDATE login_attempts
                SET attempt_count = 1, first_attempt_at = ?, blocked_until = NULL
                WHERE id = ?
            ');
            $stmt->execute([$now, $record['id']]);
            return;
        }

        // 試行回数をインクリメント
        $newCount = $record['attempt_count'] + 1;

        if ($newCount >= $maxAttempts) {
            // ブロック
            $blockedUntil = date('Y-m-d H:i:s', time() + $blockDuration);
            $stmt = $db->prepare('
                UPDATE login_attempts
                SET attempt_count = ?, blocked_until = ?
                WHERE id = ?
            ');
            $stmt->execute([$newCount, $blockedUntil, $record['id']]);
        } else {
            $stmt = $db->prepare('
                UPDATE login_attempts
                SET attempt_count = ?
                WHERE id = ?
            ');
            $stmt->execute([$newCount, $record['id']]);
        }
    }

    /**
     * 成功時にリセット
     */
    public static function reset(string $identifier, string $type = 'ip'): void
    {
        $db = Database::getConnection();

        $stmt = $db->prepare('
            DELETE FROM login_attempts
            WHERE identifier = ? AND identifier_type = ?
        ');
        $stmt->execute([$identifier, $type]);
    }

    /**
     * 残りブロック時間を取得（秒）
     */
    public static function getRemainingBlockTime(string $identifier, string $type = 'ip'): int
    {
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT blocked_until
            FROM login_attempts
            WHERE identifier = ? AND identifier_type = ?
        ');
        $stmt->execute([$identifier, $type]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$record || $record['blocked_until'] === null) {
            return 0;
        }

        $remaining = strtotime($record['blocked_until']) - time();
        return max(0, $remaining);
    }

    /**
     * クライアントIPを取得
     */
    public static function getClientIp(): string
    {
        $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // カンマ区切りの場合は最初のIPを使用
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                // 有効なIPかチェック
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * 古いレコードを削除（疑似cron）
     */
    public static function cleanup(): void
    {
        // 1%の確率で実行
        if (random_int(1, 100) !== 1) {
            return;
        }

        $db = Database::getConnection();

        // 24時間以上前のレコードを削除
        $stmt = $db->prepare('
            DELETE FROM login_attempts
            WHERE last_attempt_at < ?
        ');
        $stmt->execute([date('Y-m-d H:i:s', strtotime('-24 hours'))]);
    }
}
