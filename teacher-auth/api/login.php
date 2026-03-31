<?php
/**
 * パスワードログインAPI
 */

require_once __DIR__ . '/../public/bootstrap.php';

use App\Auth\Session;
use App\Auth\Password;
use App\Security\RateLimiter;
use App\Security\AuditLog;

// POSTのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

// CSRF検証
$token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!isset($_SESSION['csrf_token']) && $token !== '') {
    $_SESSION['csrf_token'] = $token;
}
if (!Session::validateCsrfToken($token)) {
    errorResponse('Invalid request', 403);
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$remember = Session::resolveRememberPreference($_POST['remember'] ?? null);
$requestedRedirect = normalizeInternalRedirect($_POST['return_to'] ?? null);
$postLoginRedirect = $requestedRedirect ?? '/kiweb/kiweb2.html';

// 入力検証
if (empty($email) || empty($password)) {
    errorResponse('メールアドレスとパスワードを入力してください');
}

if (!isHalfWidthAscii($email)) {
    errorResponse('メールアドレスは半角英数記号のみで入力してください');
}

if (!isHalfWidthAscii($password)) {
    errorResponse('パスワードは半角英数記号のみで入力してください');
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

// ログイン試行
$user = Password::login($email, $password);

// 未確認ユーザーの場合
if (is_array($user) && isset($user['error']) && $user['error'] === 'email_not_verified') {
    // 既存の verify トークンを生成（または再生成）して認証
    $tokenData = \App\Auth\EmailAuth::createTokenForUser(
        $user['user_id'],
        $user['email'],
        \App\Auth\EmailAuth::PURPOSE_VERIFY
    );

    // 確認メールを送信
    \App\Auth\EmailAuth::sendVerifyEmail($user['email'], $tokenData['token_id'], $tokenData['code']);

    AuditLog::log(AuditLog::LOGIN_FAILURE, $user['user_id'], [
        'email' => $email,
        'method' => 'password',
        'reason' => 'email_not_verified'
    ]);

    $redirectUrl = '/kiweb/teacher-auth/public/verify-email.php?id=' . $tokenData['token_id']
        . ($remember ? '&remember=1' : '');
    if ($requestedRedirect !== null) {
        $redirectUrl .= '&return_to=' . rawurlencode($requestedRedirect);
    }

    jsonResponse([
        'success' => false,
        'error' => 'メール確認が完了していません。確認コードを再送しました。',
        'error_code' => 'EMAIL_NOT_VERIFIED',
        'redirect' => $redirectUrl
    ]);
}

if (!$user || (is_array($user) && isset($user['error']))) {
    // 失敗を記録
    RateLimiter::recordAttempt($ip, 'ip');
    RateLimiter::recordAttempt($email, 'email');

    AuditLog::log(AuditLog::LOGIN_FAILURE, null, [
        'email' => $email,
        'method' => 'password'
    ]);

    // 不特定なエラーメッセージ（情報漏洩防止）
    errorResponse('メールアドレスまたはパスワードが正しくありません');
}

// 成功
RateLimiter::reset($ip, 'ip');
RateLimiter::reset($email, 'email');

// セッションID再生成・ログイン状況を設定
Session::regenerate();
Session::setUser($user['id']);
Session::applyLoginPersistence($remember);

AuditLog::log(AuditLog::LOGIN_SUCCESS, $user['id'], [
    'method' => 'password'
]);

jsonResponse([
    'success' => true,
    'redirect' => $postLoginRedirect
]);
