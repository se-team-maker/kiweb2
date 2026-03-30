<?php
/**
 * WebAuthn登録オプション取得API
 */

require_once __DIR__ . '/../../public/bootstrap.php';

use App\Auth\Session;
use App\Config\Database;
use App\Model\User;

// POSTのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

// ログイン必須
if (!Session::isLoggedIn()) {
    errorResponse('ログインが必要です', 401);
}

$userId = Session::getUserId();
$user = User::findById($userId);

if (!$user) {
    errorResponse('ユーザーが見つかりません', 404);
}

$db = Database::getConnection();

// チャレンジを生成
$challenge = random_bytes(32);
$challengeB64 = rtrim(strtr(base64_encode($challenge), '+/', '-_'), '=');

// チャレンジを保存
$challengeId = Database::generateUUID();
$expiresAt = date('Y-m-d H:i:s', time() + 300); // 5分
$stmt = $db->prepare('
    INSERT INTO webauthn_challenges (id, user_id, challenge, type, expires_at)
    VALUES (?, ?, ?, "register", ?)
');
$stmt->execute([$challengeId, $userId, $challengeB64, $expiresAt]);

// 既存のクレデンシャルを除外リストに追加
$stmt = $db->prepare('SELECT id FROM webauthn_credentials WHERE user_id = ?');
$stmt->execute([$userId]);
$existingCredentials = $stmt->fetchAll();

$excludeCredentials = array_map(function ($cred) {
    return [
        'type' => 'public-key',
        'id' => $cred['id']
    ];
}, $existingCredentials);

// ユーザーIDをBase64エンコード
$userIdB64 = rtrim(strtr(base64_encode($userId), '+/', '-_'), '=');

$rpName = $_ENV['WEBAUTHN_RP_NAME'] ?? '京都医塾';
$rpId = $_ENV['WEBAUTHN_RP_ID'] ?? 'localhost';

$options = [
    'challenge' => $challengeB64,
    'rp' => [
        'name' => $rpName,
        'id' => $rpId
    ],
    'user' => [
        'id' => $userIdB64,
        'name' => $user->email,
        'displayName' => $user->email
    ],
    'pubKeyCredParams' => [
        ['type' => 'public-key', 'alg' => -7],   // ES256
        ['type' => 'public-key', 'alg' => -257]  // RS256
    ],
    'authenticatorSelection' => [
        'residentKey' => 'preferred',
        'userVerification' => 'preferred'
    ],
    'timeout' => 60000,
    'attestation' => 'none'
];

if (!empty($excludeCredentials)) {
    $options['excludeCredentials'] = $excludeCredentials;
}

// セッションにチャレンジIDを保存
$_SESSION['webauthn_challenge_id'] = $challengeId;

jsonResponse([
    'success' => true,
    'options' => $options
]);
