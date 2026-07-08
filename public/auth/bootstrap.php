<?php
/**
 * 初期化・ブートストラップ
 */

define('KIWEB_ROOT', dirname(__DIR__, 2));
define('AUTH_APP_ROOT', KIWEB_ROOT . '/app/Auth');
define('AUTH_STORAGE_ROOT', KIWEB_ROOT . '/storage/auth/runtime');
define('AUTH_PRIVATE_PDF_ROOT', KIWEB_ROOT . '/storage/auth/private-pdfs');
define('AUTH_ICT_MANUAL_ROOT', KIWEB_ROOT . '/app/Documents/ict-manual');
define('AUTH_SESSION_ROOT', AUTH_STORAGE_ROOT . '/sessions');

require_once AUTH_APP_ROOT . '/vendor/autoload.php';

use Dotenv\Dotenv;
use App\Auth\Session;
use App\Auth\EmailAuth;
use App\Security\RateLimiter;
use App\Security\AuditLog;
use App\Security\AccessLog;

// 環境変数を読み込み
$dotenv = Dotenv::createImmutable(AUTH_APP_ROOT);
$dotenv->safeLoad();

// ローカル/共有サーバーで標準の session.save_path が書けない場合があるため、
// アプリ配下の非公開ストレージをセッション保存先にする。
if (session_status() === PHP_SESSION_NONE) {
    if (!is_dir(AUTH_SESSION_ROOT)) {
        @mkdir(AUTH_SESSION_ROOT, 0775, true);
    }
    if (is_dir(AUTH_SESSION_ROOT) && is_writable(AUTH_SESSION_ROOT)) {
        ini_set('session.save_path', AUTH_SESSION_ROOT);
    }
}

// セッション開始
Session::init();

// 定期cronによるクリーンアップ
EmailAuth::cleanupExpiredTokens();
RateLimiter::cleanup();
AuditLog::cleanup();
AccessLog::cleanup();

/**
 * JSONレスポンスを返す
 */
function jsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * エラーレスポンスを返す
 */
function errorResponse(string $message, int $status = 400): void
{
    jsonResponse(['success' => false, 'error' => $message], $status);
}

/**
 * Check half-width ASCII only.
 */
function isHalfWidthAscii(string $value): bool
{
    return preg_match('/^[\x21-\x7E]+$/', $value) === 1;
}

/**
 * CSRFトークンを検証
 */
function validateCsrf(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!Session::validateCsrfToken($token)) {
        errorResponse('Invalid request', 403);
    }
}

/**
 * ログイン必須ページのガード
 */
function requireAuth(): void
{
    if (!Session::isLoggedIn()) {
        header('Location: /kiweb/public/auth/login.php');
        exit;
    }
}

/**
 * 未ログイン必須ページのガード（ログイン画面など）
 */
function requireGuest(): void
{
    if (Session::isLoggedIn()) {
        header('Location: /kiweb/kiweb2.html');
        exit;
    }
}

/**
 * ビューをレンダリング
 */
function render(string $view, array $data = []): void
{
    extract($data);
    require __DIR__ . '/views/' . $view . '.php';
}

/**
 * Normalize an internal redirect target and reject unsafe destinations.
 */
function normalizeInternalRedirect($candidate): ?string
{
    if ($candidate === null || is_array($candidate) || is_object($candidate)) {
        return null;
    }

    $value = trim((string) $candidate);
    if ($value === '') {
        return null;
    }

    $parts = parse_url($value);
    if ($parts === false) {
        return null;
    }

    // Only allow same-origin relative paths under /kiweb/.
    if (isset($parts['scheme']) || isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
        return null;
    }

    $path = $parts['path'] ?? '';
    if (!is_string($path) || $path === '' || strpos($path, '/kiweb/') !== 0) {
        return null;
    }

    // Avoid redirect loops back into auth entry points.
    $blockedPaths = [
        '/kiweb/public/auth/login.php',
        '/kiweb/public/auth/logout.php',
        '/kiweb/public/auth/signup.php',
        '/kiweb/public/auth/forgot-password.php',
        '/kiweb/public/auth/reset-password.php',
        '/kiweb/public/auth/verify-email.php',
        '/kiweb/public/auth/verify-code.php',
        '/kiweb/public/auth/api/login.php',
        '/kiweb/public/auth/api/logout.php',
    ];
    if (in_array($path, $blockedPaths, true)) {
        return null;
    }

    $redirect = $path;
    if (isset($parts['query']) && $parts['query'] !== '') {
        $redirect .= '?' . $parts['query'];
    }
    if (isset($parts['fragment']) && $parts['fragment'] !== '') {
        $redirect .= '#' . $parts['fragment'];
    }

    return $redirect;
}

/**
 * Resolve a post-auth redirect with a safe fallback.
 */
function resolveInternalRedirect($candidate, string $fallback = '/kiweb/kiweb2.html'): string
{
    $normalized = normalizeInternalRedirect($candidate);
    return $normalized !== null ? $normalized : $fallback;
}
