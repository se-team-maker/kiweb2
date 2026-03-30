<?php
/**
 * パスキー削除API
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

$credentialId = $input['credential_id'] ?? '';
if (empty($credentialId)) {
    errorResponse('パスキーIDが必要です');
}

// 自分のパスキーかどうか確認
$stmt = $db->prepare('SELECT device_name FROM webauthn_credentials WHERE id = ? AND user_id = ?');
$stmt->execute([$credentialId, $userId]);
$credential = $stmt->fetch();

if (!$credential) {
    errorResponse('パスキーが見つかりません', 404);
}

// 削除
$stmt = $db->prepare('DELETE FROM webauthn_credentials WHERE id = ? AND user_id = ?');
$stmt->execute([$credentialId, $userId]);

AuditLog::log(AuditLog::PASSKEY_DELETE, $userId, [
    'device_name' => $credential['device_name']
]);

jsonResponse(['success' => true]);
