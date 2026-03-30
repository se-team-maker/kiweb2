<?php
/**
 * パスワードログインAPI
 */

require_once __DIR__ . '/../public/bootstrap.php';

use App\Auth\Session;
use App\Auth\Password;
use App\Security\RateLimiter;
use App\Security\AuditLog;

$isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
if (!$isAjax) {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $isAjax = stripos($accept, 'application/json') !== false;
}

$sendError = function (string $message, int $status = 400) use ($isAjax): void {
    if ($isAjax) {
        errorResponse($message, $status);
    }
    Session::setFlash('error', $message);
    http_response_code($status);
    header('Location: /kiweb/login/public/login.php');
    exit;
};

$sendSuccess = function (string $redirect) use ($isAjax): void {
    if ($isAjax) {
        jsonResponse([
            'success' => true,
            'redirect' => $redirect
        ]);
    }
    header('Location: ' . $redirect);
    exit;
};

// POSTのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $sendError('Method not allowed', 405);
}

// CSRF検証
if ($isAjax) {
    validateCsrf();
} else {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!Session::validateCsrfToken($token)) {
        $sendError('不正なリクエストです', 403);
    }
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// 入力検証
if ($email === '' || $password === '') {
    $sendError('IDとパスワードを入力してください');
}

if (!isHalfWidthAscii($email)) {
    $sendError('IDは半角英数記号のみで入力してください');
}

// IDにメールアドレス形式でない場合、@internalを付加
if (strpos($email, '@') === false) {
    $email = $email . '@internal';
}



// レート制限チェック
$ip = RateLimiter::getClientIp();

if (RateLimiter::isBlocked($ip, 'ip')) {
    $remaining = RateLimiter::getRemainingBlockTime($ip, 'ip');
    $sendError("しばらくしてから再試行してください（{$remaining}秒後）", 429);
}

if (RateLimiter::isBlocked($email, 'email')) {
    $remaining = RateLimiter::getRemainingBlockTime($email, 'email');
    $sendError("しばらくしてから再試行してください（{$remaining}秒後）", 429);
}

// ログイン試行
$user = Password::login($email, $password);

// 未確認ユーザーの場合
if (is_array($user) && isset($user['error']) && $user['error'] === 'email_not_verified') {
    // 既存の verify トークンを発行（または再発行）して誘導
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

    $verifyRedirect = '/kiweb/login/public/verify-email.php?id=' . $tokenData['token_id'];
    if ($isAjax) {
        jsonResponse([
            'success' => false,
            'error' => 'メール確認が完了していません。確認コードを再送しました。',
            'error_code' => 'EMAIL_NOT_VERIFIED',
            'redirect' => $verifyRedirect
        ]);
    }

    Session::setFlash('error', 'メール確認が完了していません。確認コードを再送しました。');
    header('Location: ' . $verifyRedirect);
    exit;
}

if (!$user || (is_array($user) && isset($user['error']))) {
    // 失敗を記録
    RateLimiter::recordAttempt($ip, 'ip');
    RateLimiter::recordAttempt($email, 'email');

    AuditLog::log(AuditLog::LOGIN_FAILURE, null, [
        'email' => $email,
        'method' => 'password'
    ]);

    // 一般的なエラーメッセージ（情報漏洩防止）
    $sendError('メールアドレスまたはパスワードが正しくありません');
}

// 成功
RateLimiter::reset($ip, 'ip');
RateLimiter::reset($email, 'email');

// セッションID再生成・ログイン状態を設定
Session::regenerate();
Session::setUser($user['id']);

AuditLog::log(AuditLog::LOGIN_SUCCESS, $user['id'], [
    'method' => 'password'
]);

// セッションを確実に保存してからリダイレクト
session_write_close();

$sendSuccess('/kiweb/room-booking/room-booking.php');
