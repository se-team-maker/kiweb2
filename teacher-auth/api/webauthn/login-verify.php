<?php
/**
 * WebAuthn繝ｭ繧ｰ繧､繝ｳ讀懆ｨｼAPI
 */

require_once __DIR__ . '/../../public/bootstrap.php';

use App\Auth\Session;
use App\Config\Database;
use App\Security\RateLimiter;
use App\Security\AuditLog;

// POST縺ｮ縺ｿ險ｱ蜿ｯ
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

$db = Database::getConnection();

// JSON繝懊ョ繧｣繧貞叙蠕・
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['id'], $input['response'])) {
    errorResponse('辟｡蜉ｹ縺ｪ繝ｪ繧ｯ繧ｨ繧ｹ繝医〒縺・');
}
$remember = Session::resolveRememberPreference($input['remember'] ?? null);

// 繝ｬ繝ｼ繝亥宛髯舌メ繧ｧ繝・け
$ip = RateLimiter::getClientIp();
if (RateLimiter::isBlocked($ip, 'ip')) {
    $remaining = RateLimiter::getRemainingBlockTime($ip, 'ip');
    errorResponse("縺励・繧峨￥縺励※縺九ｉ蜀崎ｩｦ陦後＠縺ｦ縺上□縺輔＞・・$remaining}遘貞ｾ鯉ｼ・", 429);
}

// 繝√Ε繝ｬ繝ｳ繧ｸID縺ｨ繝ｦ繝ｼ繧ｶ繝ｼID繧呈､懆ｨｼ
$challengeId = $_SESSION['webauthn_challenge_id'] ?? '';
$userId = $_SESSION['webauthn_user_id'] ?? '';

if (empty($challengeId) || empty($userId)) {
    errorResponse('繧ｻ繝・す繝ｧ繝ｳ縺檎┌蜉ｹ縺ｧ縺・');
}

// 繝√Ε繝ｬ繝ｳ繧ｸ繧貞叙蠕暦ｼ・HP縺ｮ繧ｿ繧､繝繧ｾ繝ｼ繝ｳ繧剃ｽｿ逕ｨ・・
$now = date('Y-m-d H:i:s');
$stmt = $db->prepare('
    SELECT challenge FROM webauthn_challenges
    WHERE id = ? AND user_id = ? AND type = "login" AND expires_at > ?
');
$stmt->execute([$challengeId, $userId, $now]);
$challengeRow = $stmt->fetch();

if (!$challengeRow) {
    RateLimiter::recordAttempt($ip, 'ip');
    errorResponse('繝√Ε繝ｬ繝ｳ繧ｸ縺檎┌蜉ｹ縺ｾ縺溘・譛滄剞蛻・ｌ縺ｧ縺・');
}

// 繝√Ε繝ｬ繝ｳ繧ｸ繧貞炎髯､
$stmt = $db->prepare('DELETE FROM webauthn_challenges WHERE id = ?');
$stmt->execute([$challengeId]);
unset($_SESSION['webauthn_challenge_id']);
unset($_SESSION['webauthn_user_id']);

// 繧ｯ繝ｬ繝・Φ繧ｷ繝｣繝ｫ繧呈､懆ｨｼ
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
    errorResponse('隱崎ｨｼ縺ｫ螟ｱ謨励＠縺ｾ縺励◆');
}

// 繝｡繝ｼ繝ｫ遒ｺ隱阪メ繧ｧ繝・け
$stmt = $db->prepare('SELECT email_verified_at FROM users WHERE id = ?');
$stmt->execute([$userId]);
$userRow = $stmt->fetch();

if ($userRow['email_verified_at'] === null) {
    AuditLog::log(AuditLog::LOGIN_FAILURE, $userId, [
        'method' => 'passkey',
        'reason' => 'email_not_verified'
    ]);
    errorResponse('繝｡繝ｼ繝ｫ遒ｺ隱阪′螳御ｺ・＠縺ｦ縺・∪縺帙ｓ縲ゅΓ繝ｼ繝ｫ繧偵＃遒ｺ隱阪￥縺縺輔＞縲・', 403);
}

// clientDataJSON繧呈､懆ｨｼ
$clientDataJSON = base64_decode(strtr($input['response']['clientDataJSON'], '-_', '+/'));
$clientData = json_decode($clientDataJSON, true);

if (!$clientData || $clientData['type'] !== 'webauthn.get') {
    RateLimiter::recordAttempt($ip, 'ip');
    errorResponse('隱崎ｨｼ縺ｫ螟ｱ謨励＠縺ｾ縺励◆');
}

// challenge縺ｮ讀懆ｨｼ
if ($clientData['challenge'] !== $challengeRow['challenge']) {
    RateLimiter::recordAttempt($ip, 'ip');
    errorResponse('隱崎ｨｼ縺ｫ螟ｱ謨励＠縺ｾ縺励◆');
}

// origin縺ｮ讀懆ｨｼ
$expectedOrigin = $_ENV['WEBAUTHN_ORIGIN'] ?? 'http://localhost:8080';
if ($clientData['origin'] !== $expectedOrigin) {
    RateLimiter::recordAttempt($ip, 'ip');
    errorResponse('隱崎ｨｼ縺ｫ螟ｱ謨励＠縺ｾ縺励◆');
}

// 鄂ｲ蜷阪・讀懆ｨｼ・育ｰ｡譏灘ｮ溯｣・- 螳滄°逕ｨ縺ｧ縺ｯ繝ｩ繧､繝悶Λ繝ｪ繧剃ｽｿ逕ｨ・・
// 螳滄圀縺ｮ繝励Ο繝繧ｯ繧ｷ繝ｧ繝ｳ縺ｧ縺ｯlbuchs/webauthn繝ｩ繧､繝悶Λ繝ｪ縺ｧ鄂ｲ蜷肴､懆ｨｼ縺悟ｿ・ｦ・

// 繧ｫ繧ｦ繝ｳ繧ｿ繝ｼ繧呈峩譁ｰ・・HP縺ｮ繧ｿ繧､繝繧ｾ繝ｼ繝ｳ繧剃ｽｿ逕ｨ・・
$nowUpdate = date('Y-m-d H:i:s');
$stmt = $db->prepare('
    UPDATE webauthn_credentials
    SET counter = counter + 1, last_used_at = ?
    WHERE id = ?
');
$stmt->execute([$nowUpdate, $credentialId]);

// 繝ｭ繧ｰ繧､繝ｳ謌仙粥
RateLimiter::reset($ip, 'ip');

Session::regenerate();
Session::setUser($userId);
Session::applyLoginPersistence($remember);

AuditLog::log(AuditLog::PASSKEY_LOGIN, $userId, [
    'credential_id' => $credentialId
]);

jsonResponse([
    'success' => true,
    'redirect' => '/kiweb/kiweb2.html'
]);
