<?php
/**
 * サインアップ（アカウント作成）API
 */

require_once __DIR__ . '/../public/bootstrap.php';

use App\Auth\Session;
use App\Auth\EmailAuth;
use App\Model\User;
use App\Config\Database;
use App\Security\RateLimiter;
use App\Security\AuditLog;
use App\Service\UserSpreadsheetMirror;

// POSTのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

// CSRF検証
validateCsrf();

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$passwordConfirm = $_POST['password_confirm'] ?? '';
$name = trim($_POST['name'] ?? '') ?: null;
$scopeIds = $_POST['scope_ids'] ?? [];

if (!is_array($scopeIds)) {
    $scopeIds = [];
}

$scopeIds = array_values(array_filter($scopeIds, static function ($value) {
    return preg_match('/^\d+$/', (string) $value) === 1;
}));

// 入力検証
if (empty($email) || empty($password) || empty($passwordConfirm)) {
    jsonResponse([
        'success' => false,
        'error' => 'すべての項目を入力してください',
        'error_code' => 'MISSING_FIELDS'
    ]);
}

if (!isHalfWidthAscii($email)) {
    jsonResponse([
        'success' => false,
        'error' => 'メールアドレスは半角英数記号のみで入力してください',
        'error_code' => 'INVALID_EMAIL'
    ]);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse([
        'success' => false,
        'error' => '有効なメールアドレスを入力してください',
        'error_code' => 'INVALID_EMAIL'
    ]);
}

if (!isHalfWidthAscii($password) || !isHalfWidthAscii($passwordConfirm)) {
    jsonResponse([
        'success' => false,
        'error' => 'パスワードは半角英数記号のみで入力してください',
        'error_code' => 'INVALID_PASSWORD'
    ]);
}

if ($password !== $passwordConfirm) {
    jsonResponse([
        'success' => false,
        'error' => 'パスワードが一致しません',
        'error_code' => 'PASSWORD_MISMATCH'
    ]);
}

// レート制限チェック（IP単位）
$ip = RateLimiter::getClientIp();
$signupAttempts = (int) ($_ENV['SIGNUP_RATE_LIMIT_ATTEMPTS'] ?? 3);
$signupWindow = (int) ($_ENV['SIGNUP_RATE_LIMIT_WINDOW'] ?? 3600);

if (RateLimiter::isBlocked($ip, 'ip')) {
    $remaining = RateLimiter::getRemainingBlockTime($ip, 'ip');
    jsonResponse([
        'success' => false,
        'error' => "しばらくしてから再試行してください（{$remaining}秒後）",
        'error_code' => 'RATE_LIMITED'
    ], 429);
}

// 既存ユーザーチェック
$existingUser = User::findByEmail($email);

if ($existingUser) {
    if ($existingUser->isEmailVerified()) {
        // 確認済みユーザー → エラー（ログインを促す）
        jsonResponse([
            'success' => false,
            'error' => 'このメールアドレスは既に登録されています。ログインしてください。',
            'error_code' => 'EMAIL_EXISTS'
        ]);
    } else {
        // 未確認ユーザー → 確認コードを再送
        $tokenData = EmailAuth::createTokenForUser(
            $existingUser->id,
            $existingUser->email,
            EmailAuth::PURPOSE_VERIFY
        );

        EmailAuth::sendVerifyEmail($email, $tokenData['token_id'], $tokenData['code']);

        AuditLog::log(AuditLog::SIGNUP_SUCCESS, $existingUser->id, [
            'email' => $email,
            'type' => 'resend_verify'
        ]);

        jsonResponse([
            'success' => true,
            'message' => '確認コードを再送しました',
            'redirect' => '/kiweb/teacher-auth/public/verify-email.php?id=' . $tokenData['token_id']
        ]);
    }
}

// 新規ユーザー作成
if ($name !== null && User::isNameTaken($name)) {
    jsonResponse([
        'success' => false,
        'error' => '同姓同名のアカウントは登録できません。管理者に確認してください。',
        'error_code' => 'NAME_EXISTS'
    ]);
}

$user = User::create($email, $password, $name);

if (!$user) {
    // 作成失敗（レースコンディション等）
    RateLimiter::recordAttempt($ip, 'ip');
    jsonResponse([
        'success' => false,
        'error' => 'アカウントの作成に失敗しました。時間をおいて再試行してください。',
        'error_code' => 'CREATE_FAILED'
    ]);
}

if (!$user->assignRole('part_time_teacher') && !$user->assignRole('teacher')) {
    error_log('Failed to assign default role for user: ' . $user->id);
}

if (!empty($scopeIds)) {
    try {
        $db = Database::getConnection();
        $placeholders = implode(',', array_fill(0, count($scopeIds), '?'));
        $stmt = $db->prepare("
            SELECT s.id
            FROM scopes s
            JOIN scope_types st ON s.scope_type_id = st.id
            WHERE s.id IN ($placeholders) AND st.name IN ('campus', 'department')
        ");
        $stmt->execute($scopeIds);
        $validScopeIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        if (!empty($validScopeIds)) {
            $insert = $db->prepare('
                INSERT INTO user_scopes (user_id, scope_id)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE user_id = user_id
            ');
            foreach ($validScopeIds as $scopeId) {
                $insert->execute([$user->id, $scopeId]);
            }
        }
    } catch (\Throwable $e) {
        error_log('Failed to assign scopes on signup: ' . $e->getMessage());
    }
}

// メール確認トークン発行
$tokenData = EmailAuth::createTokenForUser($user->id, $email, EmailAuth::PURPOSE_VERIFY);

// 確認メール送信
UserSpreadsheetMirror::mirrorCreatedUser($user);

EmailAuth::sendVerifyEmail($email, $tokenData['token_id'], $tokenData['code']);

// 監査ログ
AuditLog::log(AuditLog::SIGNUP_SUCCESS, $user->id, [
    'email' => $email
]);

jsonResponse([
    'success' => true,
    'message' => 'アカウントを作成しました。メールをご確認ください。',
    'redirect' => '/kiweb/teacher-auth/public/verify-email.php?id=' . $tokenData['token_id']
]);
