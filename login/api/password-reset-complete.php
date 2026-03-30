<?php
/**
 * パスワード再設定完了API
 */

require_once __DIR__ . '/../public/bootstrap.php';

use App\Auth\Session;
use App\Auth\EmailAuth;
use App\Model\User;
use App\Security\AuditLog;

// POSTのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

// CSRF検証
validateCsrf();

$tokenId = trim($_POST['token_id'] ?? '');
$newPassword = $_POST['new_password'] ?? '';
$newPasswordConfirm = $_POST['new_password_confirm'] ?? '';
$code = trim($_POST['code'] ?? '');

// 入力検証
if ($tokenId === '' || $newPassword === '' || $newPasswordConfirm === '') {
    jsonResponse([
        'success' => false,
        'error' => 'すべての項目を入力してください',
        'error_code' => 'MISSING_FIELDS'
    ]);
}

if ($newPassword !== $newPasswordConfirm) {
    jsonResponse([
        'success' => false,
        'error' => 'パスワードが一致しません',
        'error_code' => 'PASSWORD_MISMATCH'
    ]);
}

// 検証済みトークンを取得（verified_at IS NOT NULL）
$token = EmailAuth::getVerifiedToken($tokenId, EmailAuth::PURPOSE_RESET);

if (!$token) {
    $sessionToken = $_SESSION['password_reset_verified_token'] ?? '';
    error_log("Password Reset Debug: tokenId=$tokenId, sessionToken=" . ($sessionToken ?? 'null'));
    if ($sessionToken && hash_equals($sessionToken, $tokenId)) {
        $fallbackToken = EmailAuth::getToken($tokenId);
        if ($fallbackToken) {
            $isExpired = strtotime($fallbackToken['expires_at']) <= time();
            $isUsed = !empty($fallbackToken['used']);
            $purpose = $fallbackToken['purpose'] ?? EmailAuth::PURPOSE_RESET;
            error_log("Fallback check: tokenId=$tokenId, isExpired=" . ($isExpired ? 'yes' : 'no') . ", isUsed=" . ($isUsed ? 'yes' : 'no') . ", purpose=$purpose");
            if (!$isExpired && !$isUsed && $purpose === EmailAuth::PURPOSE_RESET) {
                $token = $fallbackToken;
            }
        }
    }
}

if (!$token && $code !== '') {
    $verified = EmailAuth::verifyCode($tokenId, $code, EmailAuth::PURPOSE_RESET);
    if ($verified) {
        error_log("Final fallback success for tokenId=$tokenId");
        EmailAuth::markTokenVerified($tokenId);
        $token = $verified;
    }
}

if (!$token) {
    AuditLog::log(AuditLog::PWD_RESET_FAIL, null, [
        'reason' => 'invalid_token',
        'token_id' => $tokenId
    ]);

    jsonResponse([
        'success' => false,
        'error' => 'セッションが無効です。最初からやり直してください。',
        'error_code' => 'INVALID_TOKEN'
    ]);
}

$userId = $token['user_id'];
$email = $token['email'];

// パスワードを更新
Password::updatePassword($userId, $newPassword);

// 未確認ユーザーの場合は confirmed にする（要件定義書の決定事項4）
if ($token['email_verified_at'] === null) {
    User::markEmailVerifiedById($userId);
}

// トークンを使用済みに
EmailAuth::markTokenUsed($tokenId);
unset($_SESSION['password_reset_verified_token']);

// 自動ログイン
Session::regenerate();
Session::setUser($userId);

// 監査ログ
AuditLog::log(AuditLog::PWD_RESET_SUCCESS, $userId, [
    'email' => $email
]);

jsonResponse([
    'success' => true,
    'message' => 'パスワードを再設定しました',
    'redirect' => '/kiweb/room-booking/room-booking.php'

]);
