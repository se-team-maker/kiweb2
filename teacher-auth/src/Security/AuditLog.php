<?php
/**
 * 監査ログクラス
 */

namespace App\Security;

use App\Config\Database;

class AuditLog
{
    // イベントタイプ定数
    const LOGIN_SUCCESS = 'login_success';
    const LOGIN_FAILURE = 'login_failure';
    const LOGOUT = 'logout';
    const EMAIL_LOGIN_REQUEST = 'email_login_request';
    const EMAIL_LOGIN_VERIFY = 'email_login_verify';
    const PASSKEY_REGISTER = 'passkey_register';
    const PASSKEY_DELETE = 'passkey_delete';
    const PASSKEY_LOGIN = 'passkey_login';
    const PASSWORD_CHANGE = 'password_change';
    const EMAIL_CHANGE = 'email_change';
    const ACCOUNT_LOCKED = 'account_locked';

    // アカウント機能拡張用イベントタイプ
    const SIGNUP_SUCCESS = 'signup_success';
    const EMAIL_VERIFY_SUCCESS = 'email_verify_success';
    const EMAIL_VERIFY_FAIL = 'email_verify_fail';
    const PWD_RESET_REQUEST = 'pwd_reset_request';
    const PWD_RESET_SUCCESS = 'pwd_reset_success';
    const PWD_RESET_FAIL = 'pwd_reset_fail';

    /**
     * 監査ログを記録
     * 
     * @param string $eventType イベントタイプ
     * @param string|null $userId ユーザーID（未認証時はnull）
     * @param array $details 詳細情報（機密情報は含めない）
     */
    public static function log(string $eventType, ?string $userId = null, array $details = []): void
    {
        $db = Database::getConnection();

        // 機密情報を除去
        $safeDetails = self::sanitizeDetails($details);

        $stmt = $db->prepare('
            INSERT INTO audit_logs (user_id, event_type, ip_address, user_agent, details)
            VALUES (?, ?, ?, ?, ?)
        ');

        $stmt->execute([
            $userId,
            $eventType,
            RateLimiter::getClientIp(),
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            json_encode($safeDetails, JSON_UNESCAPED_UNICODE)
        ]);
    }

    /**
     * 詳細情報から機密情報を除去
     */
    private static function sanitizeDetails(array $details): array
    {
        $sensitiveKeys = [
            'password',
            'token',
            'code',
            'secret',
            'key',
            'hash',
            'credential',
            'challenge',
            'pepper'
        ];

        $sanitized = [];

        foreach ($details as $key => $value) {
            $lowerKey = strtolower($key);
            $isSensitive = false;

            foreach ($sensitiveKeys as $sensitiveKey) {
                if (strpos($lowerKey, $sensitiveKey) !== false) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = self::sanitizeDetails($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * ユーザーのログイン履歴を取得
     */
    public static function getLoginHistory(string $userId, int $limit = 10): array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT event_type, ip_address, user_agent, created_at
            FROM audit_logs
            WHERE user_id = ?
              AND event_type IN (?, ?, ?)
            ORDER BY created_at DESC
            LIMIT ?
        ');

        $stmt->execute([
            $userId,
            self::LOGIN_SUCCESS,
            self::PASSKEY_LOGIN,
            self::EMAIL_LOGIN_VERIFY,
            $limit
        ]);

        return $stmt->fetchAll();
    }

    /**
     * 最近の失敗ログインを取得（管理者用）
     */
    public static function getRecentFailures(int $limit = 50): array
    {
        $db = Database::getConnection();

        $stmt = $db->prepare('
            SELECT al.*, u.email
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE al.event_type = ?
            ORDER BY al.created_at DESC
            LIMIT ?
        ');

        $stmt->execute([self::LOGIN_FAILURE, $limit]);

        return $stmt->fetchAll();
    }

    /**
     * 古いログを削除（疑似cron）
     * デフォルトで90日以上前のログを削除
     */
    public static function cleanup(int $daysToKeep = 90): void
    {
        // 1%の確率で実行
        if (random_int(1, 100) !== 1) {
            return;
        }

        $db = Database::getConnection();

        // PHPのタイムゾーンを使用
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));
        $stmt = $db->prepare('
            DELETE FROM audit_logs
            WHERE created_at < ?
        ');
        $stmt->execute([$cutoffDate]);
    }
}
