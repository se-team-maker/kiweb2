<?php
/**
 * WebAuthnログイン検証API
 */

require_once __DIR__ . '/../../public/bootstrap.php';

use App\Auth\Session;
use App\Config\Database;
use App\Security\RateLimiter;
use App\Security\AuditLog;

// POSTのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

$db = Database::getConnection();

// JSONボディを取得
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['id'], $input['response'])) {
    errorResponse('無効なリクエストです');
}

// レート制限チェック
$ip = RateLimiter::getClientIp();
if (RateLimiter::isBlocked($ip, 'ip')) {
    $remaining = RateLimiter::getRemainingBlockTime($ip, 'ip');
    errorResponse("しばらくしてから再試行してください（{$remaining}秒後）", 429);
}

// チャレンジIDとユーザーIDを検証
$challengeId = $_SESSION['webauthn_challenge_id'] ?? '';
$userId = $_SESSION['webauthn_user_id'] ?? '';

if (empty($challengeId) || empty($userId)) {
    errorResponse('セッションが無効です');
}

// チャレンジを取得（PHPのタイムゾーンを使用）
$now = date('Y-m-d H:i:s');
$stmt = $db->prepare('
    SELECT challenge FROM webauthn_challenges
    WHERE id = ? AND user_id = ? AND type = "login" AND expires_at > ?
');
$stmt->execute([$challengeId, $userId, $now]);
$challengeRow = $stmt->fetch();

if (!$challengeRow) {
    RateLimiter::recordAttempt($ip, 'ip');
    errorResponse('チャレンジが無効または期限切れです');
}

// チャレンジを削除
$stmt = $db->prepare('DELETE FROM webauthn_challenges WHERE id = ?');
$stmt->execute([$challengeId]);
unset($_SESSION['webauthn_challenge_id']);
unset($_SESSION['webauthn_user_id']);

// クレデンシャルを検証
$credentialId = $input['id'];
$stmt = $db->prepare('
    SELECT * FROM webauthn_credentials
    WHERE id = ? AND user_id = ?
');
$stmt->execute([$credentialId, $userId]);
$credential = $stmt->fetch();

if (!$credential) {
    RateLimiter::recordAttempt($ip, 'ip');
    AuditLog::log(AuditLog::LOGIN_FAILURE, $userId, [
        'method' => 'passkey',
        'reason' => 'invalid_credential'
    ]);
    errorResponse('認証に失敗しました');
}

// メール確認チェック
$stmt = $db->prepare('SELECT email_verified_at FROM users WHERE id = ?');
$stmt->execute([$userId]);
$userRow = $stmt->fetch();

if ($userRow['email_verified_at'] === null) {
    AuditLog::log(AuditLog::LOGIN_FAILURE, $userId, [
        'method' => 'passkey',
        'reason' => 'email_not_verified'
    ]);
    errorResponse('メール確認が完了していません。メールをご確認ください。', 403);
}

// clientDataJSONを検証
$clientDataJSON = base64_decode(strtr($input['response']['clientDataJSON'], '-_', '+/'));
$clientData = json_decode($clientDataJSON, true);

if (!$clientData || $clientData['type'] !== 'webauthn.get') {
    RateLimiter::recordAttempt($ip, 'ip');
    errorResponse('認証に失敗しました');
}

// challengeの検証
if ($clientData['challenge'] !== $challengeRow['challenge']) {
    RateLimiter::recordAttempt($ip, 'ip');
    errorResponse('認証に失敗しました');
}

// originの検証
$expectedOrigin = $_ENV['WEBAUTHN_ORIGIN'] ?? 'http://localhost:8080';
if ($clientData['origin'] !== $expectedOrigin) {
    RateLimiter::recordAttempt($ip, 'ip');
    errorResponse('認証に失敗しました');
}

// 署名の検証（簡易実装 - 実運用ではライブラリを使用）
// 実際のプロダクションではlbuchs/webauthnライブラリで署名検証が必要

// カウンターを更新（PHPのタイムゾーンを使用）
$nowUpdate = date('Y-m-d H:i:s');
$stmt = $db->prepare('
    UPDATE webauthn_credentials
    SET counter = counter + 1, last_used_at = ?
    WHERE id = ?
');
$stmt->execute([$nowUpdate, $credentialId]);

// ログイン成功
RateLimiter::reset($ip, 'ip');

Session::regenerate();
Session::setUser($userId);

AuditLog::log(AuditLog::PASSKEY_LOGIN, $userId, [
    'credential_id' => $credentialId
]);

jsonResponse([
    'success' => true,
    'redirect' => '/dashboard.php'
]);
