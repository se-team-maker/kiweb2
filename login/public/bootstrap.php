<?php
/**
 * 初期化・ブートストラップ
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use App\Auth\Session;
use App\Auth\EmailAuth;
use App\Security\RateLimiter;
use App\Security\AuditLog;

// 環境変数を読み込み
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// セッション開始
Session::init();

// 疑似cronによるクリーンアップ
EmailAuth::cleanupExpiredTokens();
RateLimiter::cleanup();
AuditLog::cleanup();

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
        errorResponse('不正なリクエストです', 403);
    }
}

/**
 * ログイン必須ページのガード
 */
function requireAuth(): void
{
    if (!Session::isLoggedIn()) {
        header('Location: /kiweb/login/public/login.php');
        exit;
    }
}

/**
 * 未ログイン必須ページのガード（ログイン画面など）
 */
function requireGuest(): void
{
    if (Session::isLoggedIn()) {
        header('Location: /kiweb/room-booking/room-booking.php');

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
