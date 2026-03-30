<?php
/**
 * セッション管理クラス
 */

namespace App\Auth;

class Session
{
    private static bool $initialized = false;
    private const REMEMBER_COOKIE = 'teacher_auth_remember';
    private const LOGOUT_ON_CLOSE_COOKIE = 'teacher_auth_logout_on_close';
    private const REMEMBER_LIFETIME = 2592000; // 30 days
    private const LEGACY_SESSION_COOKIE_PATH = '/kiweb/teacher-auth/';

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
        $cookiePath = $_ENV['SESSION_COOKIE_PATH'] ?? '/';
        if ($cookiePath === '/kiweb/teacher-auth/') {
            $cookiePath = '/kiweb/';
        }
        $requestRemember = self::resolveRememberPreferenceFromRequest();
        $remember = $requestRemember ?? self::shouldRememberByCookie();
        $defaultIdleLifetime = (int) ($_ENV['SESSION_LIFETIME'] ?? 1800);
        $defaultAbsoluteLifetime = (int) ($_ENV['SESSION_ABSOLUTE_LIFETIME'] ?? 604800);
        $gcMaxLifetime = $remember
            ? self::REMEMBER_LIFETIME
            : max($defaultIdleLifetime, $defaultAbsoluteLifetime);
        ini_set('session.cookie_path', $cookiePath);
        ini_set('session.cookie_samesite', 'Lax');
        ini_set('session.cookie_lifetime', $remember ? (string) self::REMEMBER_LIFETIME : '0');
        ini_set('session.gc_maxlifetime', (string) $gcMaxLifetime);

        // HTTPS環境ではSecureフラグを有効化
        if (self::isSecureRequest()) {
            ini_set('session.cookie_secure', '1');
        }

        // セッション名を設定
        session_name($_ENV['SESSION_NAME'] ?? 'login_session');

        if ($cookiePath === '/kiweb/') {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                '/kiweb/teacher-auth/',
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        // セッション開始
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        self::$initialized = true;

        // タイムアウトチェック
        self::checkTimeout();
    }

    /**
     * セッションIDを再生成（ログイン成功時に呼び出す）
     */
    public static function regenerate(): void
    {
        session_regenerate_id(true);
        $_SESSION['_created_at'] = time();
        $_SESSION['_last_activity'] = time();
    }

    /**
     * タイムアウトをチェック
     */
    private static function checkTimeout(): void
    {
        $remember = self::shouldRememberSession();
        $idleTimeout = $remember
            ? self::REMEMBER_LIFETIME
            : (int) ($_ENV['SESSION_LIFETIME'] ?? 1800);
        $absoluteTimeout = $remember
            ? self::REMEMBER_LIFETIME
            : (int) ($_ENV['SESSION_ABSOLUTE_LIFETIME'] ?? 604800);

        // アイドルタイムアウト
        if (isset($_SESSION['_last_activity'])) {
            if (time() - $_SESSION['_last_activity'] > $idleTimeout) {
                self::destroy();
                return;
            }
        }

        // 絶対期限
        if (isset($_SESSION['_created_at'])) {
            if (time() - $_SESSION['_created_at'] > $absoluteTimeout) {
                self::destroy();
                return;
            }
        }

        // 最終アクティビティ更新
        $_SESSION['_last_activity'] = time();
    }

    /**
     * セッションを破棄（ログアウト時に呼び出す）
     */
    public static function destroy(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            self::setCookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'] ?: '/',
                $params['domain'] ?? '',
                (bool) ($params['secure'] ?? false),
                (bool) ($params['httponly'] ?? true)
            );
        }
        self::clearPersistenceCookies();

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
     * ログイン維持ポリシーを反映
     */
    public static function applyLoginPersistence(bool $remember): void
    {
        $_SESSION['_remember_login'] = $remember;
        $cookiePath = self::getCookiePath();
        $rootPath = '/';
        $params = session_get_cookie_params();
        $domain = $params['domain'] ?? '';
        $secure = self::isSecureRequest();

        if ($remember) {
            if ($cookiePath !== $rootPath) {
                self::setCookie(self::REMEMBER_COOKIE, '', time() - 42000, $rootPath, $domain, $secure, true);
                self::setCookie(self::LOGOUT_ON_CLOSE_COOKIE, '', time() - 42000, $rootPath, $domain, $secure, false);
            }
            self::setCookie(
                self::REMEMBER_COOKIE,
                '1',
                time() + self::REMEMBER_LIFETIME,
                $cookiePath,
                $domain,
                $secure,
                true
            );
            self::setCookie(
                self::LOGOUT_ON_CLOSE_COOKIE,
                '',
                time() - 42000,
                $cookiePath,
                $domain,
                $secure,
                false
            );
        } else {
            self::setCookie(
                self::REMEMBER_COOKIE,
                '',
                time() - 42000,
                $cookiePath,
                $domain,
                $secure,
                true
            );
            if ($cookiePath !== $rootPath) {
                self::setCookie(self::REMEMBER_COOKIE, '', time() - 42000, $rootPath, $domain, $secure, true);
            }
            self::setCookie(
                self::LOGOUT_ON_CLOSE_COOKIE,
                '1',
                0,
                $cookiePath,
                $domain,
                $secure,
                false
            );
            if ($cookiePath !== $rootPath) {
                self::setCookie(self::LOGOUT_ON_CLOSE_COOKIE, '', time() - 42000, $rootPath, $domain, $secure, false);
            }
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $sessionPath = $params['path'] ?: $cookiePath;
            $sessionSecure = (bool) ($params['secure'] ?? false) || $secure;
            $sessionHttpOnly = (bool) ($params['httponly'] ?? true);
            self::setCookie(
                session_name(),
                session_id(),
                $remember ? (time() + self::REMEMBER_LIFETIME) : 0,
                $sessionPath,
                $domain,
                $sessionSecure,
                $sessionHttpOnly
            );

            if ($remember) {
                if ($sessionPath !== '/') {
                    self::setCookie(session_name(), '', time() - 42000, '/', $domain, $sessionSecure, $sessionHttpOnly);
                }
                if ($sessionPath !== self::LEGACY_SESSION_COOKIE_PATH) {
                    self::setCookie(
                        session_name(),
                        '',
                        time() - 42000,
                        self::LEGACY_SESSION_COOKIE_PATH,
                        $domain,
                        $sessionSecure,
                        $sessionHttpOnly
                    );
                }
            } else {
                if ($sessionPath !== '/') {
                    self::setCookie(session_name(), session_id(), 0, '/', $domain, $sessionSecure, $sessionHttpOnly);
                }
                if ($sessionPath !== self::LEGACY_SESSION_COOKIE_PATH) {
                    self::setCookie(
                        session_name(),
                        '',
                        time() - 42000,
                        self::LEGACY_SESSION_COOKIE_PATH,
                        $domain,
                        $sessionSecure,
                        $sessionHttpOnly
                    );
                }
            }
        }
    }

    /**
     * リクエスト入力からremember設定を解決
     */
    public static function resolveRememberPreference($input): bool
    {
        if ($input === null || $input === '') {
            return false;
        }

        if (is_bool($input)) {
            return $input;
        }

        if (is_int($input)) {
            return $input === 1;
        }

        if (!is_string($input)) {
            return false;
        }

        $normalized = strtolower(trim($input));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    private static function resolveRememberPreferenceFromRequest(): ?bool
    {
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return null;
        }

        if (!array_key_exists('remember', $_POST)) {
            return null;
        }

        return self::resolveRememberPreference($_POST['remember']);
    }

    /**
     * remember cookie の状態
     */
    public static function shouldRememberByCookie(): bool
    {
        return ($_COOKIE[self::REMEMBER_COOKIE] ?? '') === '1';
    }

    /**
     * ユーザーIDを取得
     */
    public static function getUserId(): ?string
    {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * ログイン状況を確認
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

    private static function shouldRememberSession(): bool
    {
        if (isset($_SESSION['_remember_login'])) {
            return (bool) $_SESSION['_remember_login'];
        }

        return self::shouldRememberByCookie();
    }

    private static function clearPersistenceCookies(): void
    {
        $cookiePath = self::getCookiePath();
        $rootPath = '/';
        $params = session_get_cookie_params();
        $domain = $params['domain'] ?? '';
        $secure = self::isSecureRequest();
        self::setCookie(self::REMEMBER_COOKIE, '', time() - 42000, $cookiePath, $domain, $secure, true);
        self::setCookie(self::LOGOUT_ON_CLOSE_COOKIE, '', time() - 42000, $cookiePath, $domain, $secure, false);
        if ($cookiePath !== $rootPath) {
            self::setCookie(self::REMEMBER_COOKIE, '', time() - 42000, $rootPath, $domain, $secure, true);
            self::setCookie(self::LOGOUT_ON_CLOSE_COOKIE, '', time() - 42000, $rootPath, $domain, $secure, false);
        }
    }

    private static function getCookiePath(): string
    {
        $cookiePath = $_ENV['SESSION_COOKIE_PATH'] ?? '/';
        if ($cookiePath === '/kiweb/teacher-auth/') {
            return '/kiweb/';
        }
        return $cookiePath;
    }

    private static function isSecureRequest(): bool
    {
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            return true;
        }

        return (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    }

    private static function setCookie(
        string $name,
        string $value,
        int $expires,
        string $path,
        string $domain,
        bool $secure,
        bool $httpOnly
    ): void {
        $options = [
            'expires' => $expires,
            'path' => $path,
            'secure' => $secure,
            'httponly' => $httpOnly,
            'samesite' => 'Lax'
        ];
        if ($domain !== '') {
            $options['domain'] = $domain;
        }
        setcookie($name, $value, $options);
    }
}
