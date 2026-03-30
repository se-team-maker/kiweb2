<?php
/**
 * メールログイン開始API
 */

require_once __DIR__ . '/../public/bootstrap.php';

use App\Auth\Session;
use App\Auth\EmailAuth;
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
    errorResponse('メールアドレスを入力してください');
}

if (!isHalfWidthAscii($email)) {
    errorResponse('メールアドレスは半角英数記号のみで入力してください');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    errorResponse('有効なメールアドレスを入力してください');
}

// レート制限チェック
$ip = RateLimiter::getClientIp();

if (RateLimiter::isBlocked($ip, 'ip')) {
    $remaining = RateLimiter::getRemainingBlockTime($ip, 'ip');
    errorResponse("しばらくしてから再試行してください（{$remaining}秒後）", 429);
}

if (RateLimiter::isBlocked($email, 'email')) {
    $remaining = RateLimiter::getRemainingBlockTime($email, 'email');
    errorResponse("しばらくしてから再試行してください（{$remaining}秒後）", 429);
}

// トークン生成
$tokenData = EmailAuth::createToken($email);

// ユーザーが存在しなくても同じレスポンスを返す（情報漏洩防止）
if (!$tokenData) {
    RateLimiter::recordAttempt($ip, 'ip');

    // ダミーの遅延
    usleep(random_int(100000, 300000));

    // 成功したように見せる
    jsonResponse([
        'success' => true,
        'token_id' => 'dummy-' . bin2hex(random_bytes(8)),
        'message' => '認証コードを送信しました'
    ]);
}

// メール送信
EmailAuth::sendLoginEmail($email, $tokenData['token_id'], $tokenData['code']);

AuditLog::log(AuditLog::EMAIL_LOGIN_REQUEST, $tokenData['user_id'], [
    'email' => $email
]);

jsonResponse([
    'success' => true,
    'token_id' => $tokenData['token_id'],
    'message' => '認証コードを送信しました'
]);
