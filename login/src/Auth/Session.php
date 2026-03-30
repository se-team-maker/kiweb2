<?php
/**
 * セッション管理クラス
 */

namespace App\Auth;

class Session
{
    private static bool $initialized = false;

    /**
     * セッションを初期化
     */
    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        // セッション設定
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_path', '/');
        ini_set('session.cookie_samesite', 'Lax');
        $cookieLifetime = (int) ($_ENV['SESSION_COOKIE_LIFETIME'] ?? 0);
        if ($cookieLifetime <= 0) {
            $cookieLifetime = 60 * 60 * 24 * 365 * 10;
        }
        ini_set('session.cookie_lifetime', (string) $cookieLifetime);
        ini_set('session.gc_maxlifetime', (string) $cookieLifetime);

        // HTTPS環境ではSecureフラグを有効化
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            ini_set('session.cookie_secure', '1');
        }

        // セッション名を設定
        session_name($_ENV['SESSION_NAME'] ?? 'login_session');

        // セッション開始
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        self::$initialized = true;

        // タイムアウトチェック
        self::checkTimeout();
    }

    /**
     * セッションIDを再生成（ログイン成功時に呼び出し）
     */
    public static function regenerate(): void
    {
        session_regenerate_id(true);
        $_SESSION['_created_at'] = time();
        $_SESSION['_last_activity'] = time();
    }

    /**
     * タイムアウトをチェック
     * 値が0の場合は該当のタイムアウトを無効化（無制限）
     */
    private static function checkTimeout(): void
    {
        $idleTimeout = (int) ($_ENV['SESSION_LIFETIME'] ?? 0);
        $absoluteTimeout = (int) ($_ENV['SESSION_ABSOLUTE_LIFETIME'] ?? 0);

        // アイドルタイムアウト（0の場合は無制限）
        if ($idleTimeout > 0 && isset($_SESSION['_last_activity'])) {
            if (time() - $_SESSION['_last_activity'] > $idleTimeout) {
                self::destroy();
                return;
            }
        }

        // 絶対期限（0の場合は無制限）
        if ($absoluteTimeout > 0 && isset($_SESSION['_created_at'])) {
            if (time() - $_SESSION['_created_at'] > $absoluteTimeout) {
                self::destroy();
                return;
            }
        }

        // 最終アクティビティ更新
        $_SESSION['_last_activity'] = time();
    }

    /**
     * セッションを破棄（ログアウト時に呼び出し）
     */
    public static function destroy(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        self::$initialized = false;
    }

    /**
     * ユーザーIDを設定（ログイン時）
     */
    public static function setUser(string $userId): void
    {
        $_SESSION['user_id'] = $userId;
        $_SESSION['_created_at'] = time();
        $_SESSION['_last_activity'] = time();
    }

    /**
     * ユーザーIDを取得
     */
    public static function getUserId(): ?string
    {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * ログイン状態を確認
     */
    public static function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }

    /**
     * CSRFトークンを生成
     */
    public static function generateCsrfToken(): string
    {
        if (!empty($_SESSION['csrf_token'])) {
            return $_SESSION['csrf_token'];
        }

        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }

    /**
     * CSRFトークンを検証
     */
    public static function validateCsrfToken(string $token): bool
    {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * フラッシュメッセージを設定
     */
    public static function setFlash(string $key, string $message): void
    {
        $_SESSION['_flash'][$key] = $message;
    }

    /**
     * フラッシュメッセージを取得（取得後削除）
     */
    public static function getFlash(string $key): ?string
    {
        $message = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $message;
    }
}
