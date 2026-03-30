<?php
/**
 * 繝代せ繝ｯ繝ｼ繝牙・險ｭ螳夊ｦ∵ｱ・PI
 */

require_once __DIR__ . '/../public/bootstrap.php';

use App\Auth\EmailAuth;
use App\Config\Database;
use App\Security\RateLimiter;
use App\Security\AuditLog;

// POST縺ｮ縺ｿ險ｱ蜿ｯ
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

// CSRF讀懆ｨｼ
validateCsrf();

$email = trim($_POST['email'] ?? '');

// 蜈･蜉帶､懆ｨｼ
if (empty($email)) {
    jsonResponse([
        'success' => false,
        'error' => '繝｡繝ｼ繝ｫ繧｢繝峨Ξ繧ｹ繧貞・蜉帙＠縺ｦ縺上□縺輔＞',
        'error_code' => 'MISSING_EMAIL'
    ]);
}

if (!isHalfWidthAscii($email)) {
    jsonResponse([
        'success' => false,
        'error' => '繝｡繝ｼ繝ｫ繧｢繝峨Ξ繧ｹ縺ｯ蜊願ｧ定恭謨ｰ險伜捷縺ｮ縺ｿ縺ｧ蜈･蜉帙＠縺ｦ縺上□縺輔＞',
        'error_code' => 'INVALID_EMAIL'
    ]);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse([
        'success' => false,
        'error' => '譛牙柑縺ｪ繝｡繝ｼ繝ｫ繧｢繝峨Ξ繧ｹ繧貞・蜉帙＠縺ｦ縺上□縺輔＞',
        'error_code' => 'INVALID_EMAIL'
    ]);
}

// 繝ｬ繝ｼ繝亥宛髯舌メ繧ｧ繝・け・・P + email蜊倅ｽ搾ｼ・
$ip = RateLimiter::getClientIp();

if (RateLimiter::isBlocked($ip, 'ip')) {
    $remaining = RateLimiter::getRemainingBlockTime($ip, 'ip');
    jsonResponse([
        'success' => false,
        'error' => "縺励・繧峨￥縺励※縺九ｉ蜀崎ｩｦ陦後＠縺ｦ縺上□縺輔＞・・$remaining}遘貞ｾ鯉ｼ・",
        'error_code' => 'RATE_LIMITED'
    ], 429);
}

if (RateLimiter::isBlocked($email, 'email')) {
    $remaining = RateLimiter::getRemainingBlockTime($email, 'email');
    jsonResponse([
        'success' => false,
        'error' => "縺励・繧峨￥縺励※縺九ｉ蜀崎ｩｦ陦後＠縺ｦ縺上□縺輔＞・・$remaining}遘貞ｾ鯉ｼ・",
        'error_code' => 'RATE_LIMITED'
    ], 429);
}

// 繝医・繧ｯ繝ｳ逋ｺ陦後ｒ隧ｦ陦鯉ｼ医Θ繝ｼ繧ｶ繝ｼ縺悟ｭ伜惠縺吶ｋ蝣ｴ蜷医・縺ｿ謌仙粥・・
$tokenData = EmailAuth::createToken($email, EmailAuth::PURPOSE_RESET, false);

// 逶｣譟ｻ繝ｭ繧ｰ・域・蜉溘・螟ｱ謨怜撫繧上★險倬鹸・・
AuditLog::log(AuditLog::PWD_RESET_REQUEST, $tokenData ? $tokenData['user_id'] : null, [
    'email' => $email,
    'exists' => $tokenData !== null
]);

// 繝ｬ繝ｼ繝亥宛髯舌ｒ險倬鹸・域・蜉溘・螟ｱ謨怜撫繧上★・・
RateLimiter::recordAttempt($ip, 'ip');
RateLimiter::recordAttempt($email, 'email');

if ($tokenData) {
    // 繝ｦ繝ｼ繧ｶ繝ｼ縺悟ｭ伜惠縺吶ｋ 竊・繝｡繝ｼ繝ｫ騾∽ｿ｡
    EmailAuth::sendResetEmail($email, $tokenData['token_id'], $tokenData['code']);

    jsonResponse([
        'success' => true,
        'message' => '繝代せ繝ｯ繝ｼ繝牙・險ｭ螳壹・繝｡繝ｼ繝ｫ繧帝∽ｿ｡縺励∪縺励◆',
        'redirect' => '/kiweb/teacher-auth/public/reset-password.php?id=' . $tokenData['token_id']
    ]);
} else {
    // 繝ｦ繝ｼ繧ｶ繝ｼ縺悟ｭ伜惠縺励↑縺・竊・繝繝溘・縺ｮ繝医・繧ｯ繝ｳID繧堤函謌舌＠縺ｦ蜷御ｸ繝ｬ繧ｹ繝昴Φ繧ｹ・亥・謖咎亟豁｢・・
    $dummyTokenId = Database::generateUUID();

    jsonResponse([
        'success' => true,
        'message' => '繝代せ繝ｯ繝ｼ繝牙・險ｭ螳壹・繝｡繝ｼ繝ｫ繧帝∽ｿ｡縺励∪縺励◆',
        'redirect' => '/kiweb/teacher-auth/public/reset-password.php?id=' . $dummyTokenId
    ]);
}
