<?php
/**
 * パスワード再設定要求API
 */

require_once __DIR__ . '/../public/bootstrap.php';

use App\Auth\EmailAuth;
use App\Config\Database;
use App\Security\RateLimiter;
use App\Security\AuditLog;

// POSTのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

// CSRF検証
validateCsrf();

$email = trim($_POST['email'] ?? '');

// 入力検証
if (empty($email)) {
    jsonResponse([
        'success' => false,
        'error' => 'メールアドレスを入力してください',
        'error_code' => 'MISSING_EMAIL'
    ]);
}

if (!isHalfWidthAscii($email)) {
    jsonResponse([
        'success' => false,
        'error' => 'メールアドレスは半角英数記号のみで入力してください',
        'error_code' => 'INVALID_EMAIL'
    ]);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse([
        'success' => false,
        'error' => '有効なメールアドレスを入力してください',
        'error_code' => 'INVALID_EMAIL'
    ]);
}

// レート制限チェック（IP + email単位）
$ip = RateLimiter::getClientIp();

if (RateLimiter::isBlocked($ip, 'ip')) {
    $remaining = RateLimiter::getRemainingBlockTime($ip, 'ip');
    jsonResponse([
        'success' => false,
        'error' => "しばらくしてから再試行してください（{$remaining}秒後）",
        'error_code' => 'RATE_LIMITED'
    ], 429);
}

if (RateLimiter::isBlocked($email, 'email')) {
    $remaining = RateLimiter::getRemainingBlockTime($email, 'email');
    jsonResponse([
        'success' => false,
        'error' => "しばらくしてから再試行してください（{$remaining}秒後）",
        'error_code' => 'RATE_LIMITED'
    ], 429);
}

// トークン発行を試行（ユーザーが存在する場合のみ成功）
$tokenData = EmailAuth::createToken($email, EmailAuth::PURPOSE_RESET, false);

// 監査ログ（成功・失敗問わず記録）
AuditLog::log(AuditLog::PWD_RESET_REQUEST, $tokenData ? $tokenData['user_id'] : null, [
    'email' => $email,
    'exists' => $tokenData !== null
]);

// レート制限を記録（成功・失敗問わず）
RateLimiter::recordAttempt($ip, 'ip');
RateLimiter::recordAttempt($email, 'email');

if ($tokenData) {
    // ユーザーが存在する → メール送信
    EmailAuth::sendResetEmail($email, $tokenData['token_id'], $tokenData['code']);

    jsonResponse([
        'success' => true,
        'message' => 'パスワード再設定のメールを送信しました',
        'redirect' => '/reset-password.php?id=' . $tokenData['token_id']
    ]);
} else {
    // ユーザーが存在しない → ダミーのトークンIDを生成して同一レスポンス（列挙防止）
    $dummyTokenId = Database::generateUUID();

    jsonResponse([
        'success' => true,
        'message' => 'パスワード再設定のメールを送信しました',
        'redirect' => '/reset-password.php?id=' . $dummyTokenId
    ]);
}
