<?php
/**
 * メール認証コード検証API
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

$tokenId = trim($_POST['token_id'] ?? '');
$code = trim($_POST['code'] ?? '');

// 入力検証
if (empty($tokenId) || empty($code)) {
    errorResponse('認証コードを入力してください');
}

if (!preg_match('/^\d{6}$/', $code)) {
    errorResponse('6桁の数字を入力してください');
}

// レート制限チェック
$ip = RateLimiter::getClientIp();

if (RateLimiter::isBlocked($ip, 'ip')) {
    $remaining = RateLimiter::getRemainingBlockTime($ip, 'ip');
    errorResponse("しばらくしてから再試行してください（{$remaining}秒後）", 429);
}

// 検証
$user = EmailAuth::verifyAndLogin($tokenId, $code);

if (!$user) {
    RateLimiter::recordAttempt($ip, 'ip');
    RateLimiter::recordAttempt($tokenId, 'email');

    errorResponse('認証コードが正しくないか、有効期限が切れています');
}

// 成功
RateLimiter::reset($ip, 'ip');

// セッションID再生成・ログイン状態を設定
Session::regenerate();
Session::setUser($user['id']);

AuditLog::log(AuditLog::EMAIL_LOGIN_VERIFY, $user['id'], [
    'email' => $user['email']
]);

jsonResponse([
    'success' => true,
    'redirect' => '/dashboard.php'
]);
