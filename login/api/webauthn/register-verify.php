<?php
/**
 * WebAuthn登録検証API
 */

require_once __DIR__ . '/../../public/bootstrap.php';

use App\Auth\Session;
use App\Config\Database;
use App\Security\AuditLog;

// POSTのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

// ログイン必須
if (!Session::isLoggedIn()) {
    errorResponse('ログインが必要です', 401);
}

$userId = Session::getUserId();
$db = Database::getConnection();

// JSONボディを取得
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['id'], $input['response'])) {
    errorResponse('無効なリクエストです');
}

// チャレンジIDを検証
$challengeId = $_SESSION['webauthn_challenge_id'] ?? '';
if (empty($challengeId)) {
    errorResponse('セッションが無効です');
}

// チャレンジを取得（PHPのタイムゾーンを使用）
$now = date('Y-m-d H:i:s');
$stmt = $db->prepare('
    SELECT challenge FROM webauthn_challenges
    WHERE id = ? AND user_id = ? AND type = "register" AND expires_at > ?
');
$stmt->execute([$challengeId, $userId, $now]);
$challengeRow = $stmt->fetch();

if (!$challengeRow) {
    errorResponse('チャレンジが無効または期限切れです');
}

// チャレンジを削除
$stmt = $db->prepare('DELETE FROM webauthn_challenges WHERE id = ?');
$stmt->execute([$challengeId]);
unset($_SESSION['webauthn_challenge_id']);

// clientDataJSONを検証
$clientDataJSON = base64_decode(strtr($input['response']['clientDataJSON'], '-_', '+/'));
$clientData = json_decode($clientDataJSON, true);

if (!$clientData) {
    errorResponse('clientDataJSONが無効です');
}

// typeの検証
if ($clientData['type'] !== 'webauthn.create') {
    errorResponse('無効な操作タイプです');
}

// challengeの検証
if ($clientData['challenge'] !== $challengeRow['challenge']) {
    errorResponse('チャレンジが一致しません');
}

// originの検証
$expectedOrigin = $_ENV['WEBAUTHN_ORIGIN'] ?? 'http://localhost:8080';
if ($clientData['origin'] !== $expectedOrigin) {
    errorResponse('オリジンが一致しません');
}

// attestationObjectをパース（簡易実装）
$attestationObject = base64_decode(strtr($input['response']['attestationObject'], '-_', '+/'));

// CBORをパースする簡易実装（実運用ではライブラリを使用）
// ここでは公開鍵を抽出せず、attestationObjectをそのまま保存
// 実際のプロダクションではlbuchs/webauthnライブラリを使用してください

// クレデンシャルを保存
$credentialId = $input['id'];
$deviceName = $input['deviceName'] ?? null;

try {
    $stmt = $db->prepare('
        INSERT INTO webauthn_credentials (id, user_id, public_key, device_name)
        VALUES (?, ?, ?, ?)
    ');
    $stmt->execute([
        $credentialId,
        $userId,
        base64_encode($attestationObject),
        $deviceName
    ]);

    AuditLog::log(AuditLog::PASSKEY_REGISTER, $userId, [
        'device_name' => $deviceName
    ]);

    jsonResponse(['success' => true]);
} catch (\PDOException $e) {
    errorResponse('パスキーの保存に失敗しました');
}
