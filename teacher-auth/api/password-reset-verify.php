<?php
/**
 * パスワード再設定コード検証API
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
$code = trim($_POST['code'] ?? '');

// 入力検証
if (empty($tokenId) || empty($code)) {
    jsonResponse([
        'success' => false,
        'error' => '認証コードを入力してください',
        'error_code' => 'MISSING_FIELDS'
    ]);
}

// コードは6桁数字のみ
if (!preg_match('/^\d{6}$/', $code)) {
    jsonResponse([
        'success' => false,
        'error' => '認証コードは6桁の数字で入力してください',
        'error_code' => 'INVALID_CODE_FORMAT'
    ]);
}

// レート制限チェック（IP + token単位）
$ip = RateLimiter::getClientIp();

if (RateLimiter::isBlocked($ip, 'ip')) {
    $remaining = RateLimiter::getRemainingBlockTime($ip, 'ip');
    jsonResponse([
        'success' => false,
        'error' => "しばらくしてから再試行してください（{$remaining}秒後）",
        'error_code' => 'RATE_LIMITED'
    ], 429);
}

if (RateLimiter::isBlocked($tokenId, 'token')) {
    $remaining = RateLimiter::getRemainingBlockTime($tokenId, 'token');
    jsonResponse([
        'success' => false,
        'error' => "認証コードの入力回数が上限に達しました。（{$remaining}秒後に再試行可能）",
        'error_code' => 'RATE_LIMITED'
    ], 429);
}

// コード検証
$result = EmailAuth::verifyCode($tokenId, $code, EmailAuth::PURPOSE_RESET);

if (!$result) {
    // 失敗を記録
    RateLimiter::recordAttempt($ip, 'ip');
    RateLimiter::recordAttempt($tokenId, 'token');

    // トークン情報を取得してログに記録
    $tokenInfo = EmailAuth::getToken($tokenId);
    $userId = $tokenInfo ? $tokenInfo['user_id'] : null;

    AuditLog::log(AuditLog::PWD_RESET_FAIL, $userId, [
        'reason' => 'invalid_code',
        'token_id' => $tokenId
    ]);

    jsonResponse([
        'success' => false,
        'error' => '認証コードが正しくないか、有効期限が切れています',
        'error_code' => 'INVALID_CODE'
    ]);
}

// コード検証成功 → トークンを「検証済み」としてマーク
EmailAuth::markTokenVerified($tokenId);
$_SESSION['password_reset_verified_token'] = $tokenId;

// レート制限をリセット
RateLimiter::reset($ip, 'ip');
RateLimiter::reset($tokenId, 'token');

jsonResponse([
    'success' => true,
    'message' => '認証コードを確認しました',
    'next_step' => true
]);
