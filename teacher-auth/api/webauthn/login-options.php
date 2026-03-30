<?php
/**
 * WebAuthnログインオプション取得API
 */

require_once __DIR__ . '/../../public/bootstrap.php';

use App\Config\Database;
use App\Model\User;

// POSTのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

// JSONボディを取得
$input = json_decode(file_get_contents('php://input'), true);

$email = trim($input['email'] ?? '');
if (empty($email)) {
    errorResponse('メールアドレスを入力してください');
}

// ユーザーを取得
if (!isHalfWidthAscii($email)) {
    errorResponse('メールアドレスは半角英数記号のみで入力してください');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    errorResponse('有効なメールアドレスを入力してください');
}

$user = User::findByEmail($email);

// ユーザーが存在しなくてもダミーレスポンスを返す（情報漏洩防止）
if (!$user) {
    usleep(random_int(100000, 300000));
    errorResponse('パスキーが登録されていません');
}

$db = Database::getConnection();

// ユーザーのクレデンシャルを取得
$stmt = $db->prepare('SELECT id FROM webauthn_credentials WHERE user_id = ?');
$stmt->execute([$user->id]);
$credentials = $stmt->fetchAll();

if (empty($credentials)) {
    errorResponse('パスキーが登録されていません');
}

// チャレンジを生成
$challenge = random_bytes(32);
$challengeB64 = rtrim(strtr(base64_encode($challenge), '+/', '-_'), '=');

// チャレンジを保存
$challengeId = Database::generateUUID();
$expiresAt = date('Y-m-d H:i:s', time() + 300); // 5分
$stmt = $db->prepare('
    INSERT INTO webauthn_challenges (id, user_id, challenge, type, expires_at)
    VALUES (?, ?, ?, "login", ?)
');
$stmt->execute([$challengeId, $user->id, $challengeB64, $expiresAt]);

$rpId = $_ENV['WEBAUTHN_RP_ID'] ?? 'localhost';

$allowCredentials = array_map(function ($cred) {
    return [
        'type' => 'public-key',
        'id' => $cred['id']
    ];
}, $credentials);

$options = [
    'challenge' => $challengeB64,
    'rpId' => $rpId,
    'allowCredentials' => $allowCredentials,
    'userVerification' => 'preferred',
    'timeout' => 60000
];

// セッションにチャレンジIDを保存
$_SESSION['webauthn_challenge_id'] = $challengeId;
$_SESSION['webauthn_user_id'] = $user->id;

jsonResponse([
    'success' => true,
    'options' => $options
]);
