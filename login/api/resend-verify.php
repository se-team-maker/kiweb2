<?php
/**
 * 確認コード再送API
 */

require_once __DIR__ . '/../public/bootstrap.php';

use App\Auth\EmailAuth;
use App\Security\RateLimiter;
use App\Security\AuditLog;

// POSTのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

// CSRF検証
validateCsrf();

$tokenId = trim($_POST['token_id'] ?? '');

if (empty($tokenId)) {
    jsonResponse([
        'success' => false,
        'error' => '無効なリクエストです',
        'error_code' => 'MISSING_TOKEN'
    ]);
}

// レート制限チェック（IP単位）
$ip = RateLimiter::getClientIp();
$resendAttempts = (int) ($_ENV['RESEND_RATE_LIMIT_ATTEMPTS'] ?? 5);
$resendWindow = (int) ($_ENV['RESEND_RATE_LIMIT_WINDOW'] ?? 900);

if (RateLimiter::isBlocked($ip, 'ip')) {
    $remaining = RateLimiter::getRemainingBlockTime($ip, 'ip');
    jsonResponse([
        'success' => false,
        'error' => "再送回数が上限に達しました。（{$remaining}秒後に再試行可能）",
        'error_code' => 'RATE_LIMITED'
    ], 429);
}

// 既存トークンからユーザー情報を取得
$existingToken = EmailAuth::getToken($tokenId);

if (!$existingToken) {
    jsonResponse([
        'success' => false,
        'error' => '無効なリクエストです',
        'error_code' => 'INVALID_TOKEN'
    ]);
}

$userId = $existingToken['user_id'];
$email = $existingToken['email'];
$purpose = $existingToken['purpose'] ?? EmailAuth::PURPOSE_VERIFY;

// メール単位のレート制限チェック
if (RateLimiter::isBlocked($email, 'email')) {
    $remaining = RateLimiter::getRemainingBlockTime($email, 'email');
    jsonResponse([
        'success' => false,
        'error' => "再送回数が上限に達しました。（{$remaining}秒後に再試行可能）",
        'error_code' => 'RATE_LIMITED'
    ], 429);
}

// 新しいトークンを発行（既存トークンは自動で失効）
$newTokenData = EmailAuth::createTokenForUser($userId, $email, $purpose);

// purposeに応じてメールを送信
if ($purpose === EmailAuth::PURPOSE_VERIFY) {
    EmailAuth::sendVerifyEmail($email, $newTokenData['token_id'], $newTokenData['code']);
} elseif ($purpose === EmailAuth::PURPOSE_RESET) {
    EmailAuth::sendResetEmail($email, $newTokenData['token_id'], $newTokenData['code']);
} else {
    EmailAuth::sendLoginEmail($email, $newTokenData['token_id'], $newTokenData['code']);
}

// レート制限を記録
RateLimiter::recordAttempt($ip, 'ip');
RateLimiter::recordAttempt($email, 'email');

// 監査ログ
AuditLog::log(AuditLog::EMAIL_VERIFY_SUCCESS, $userId, [
    'email' => $email,
    'action' => 'resend',
    'purpose' => $purpose
]);

jsonResponse([
    'success' => true,
    'message' => '確認コードを再送しました',
    'token_id' => $newTokenData['token_id']
]);
