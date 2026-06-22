<?php
/**
 * 勤務記録検索の認証付きブリッジAPI。
 *
 * 非常勤はログイン本人、社員・管理者は指定した講師を検索対象にできる。
 * 認証中ユーザーと検索対象者を分けたまま、年月と講師名をGASへ転送する。
 */

require_once __DIR__ . '/../public/bootstrap.php';

use App\Auth\Session;
use App\Model\User;

const WORK_RECORD_SEARCH_GAS_URL = 'https://script.google.com/macros/s/AKfycbykklCZCo66cwqqJl71UInD1FiottaoXsVCA2cxSd9K6fm27-j5bjGikh3PZtBAA2rj/exec';
const WORK_RECORD_SEARCH_SOURCE = 'kiweb2';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed', 405);
}

if (!Session::isLoggedIn()) {
    jsonResponse([
        'success' => false,
        'error' => 'ログインが必要です',
        'error_code' => 'UNAUTHORIZED'
    ], 401);
}

$yearMonth = trim((string)($_GET['yearMonth'] ?? ''));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $yearMonth)) {
    jsonResponse([
        'success' => false,
        'error' => 'yearMonth must be in YYYY-MM format',
        'error_code' => 'INVALID_YEARMONTH'
    ], 400);
}

$requestedName = trim((string)($_GET['name'] ?? ''));

$userId = Session::getUserId();
$user = User::findById($userId);
if (!$user) {
    Session::destroy();
    jsonResponse([
        'success' => false,
        'error' => 'ユーザーが見つかりません',
        'error_code' => 'USER_NOT_FOUND'
    ], 401);
}

if (!$user->isActive()) {
    Session::destroy();
    jsonResponse([
        'success' => false,
        'error' => 'アカウントが無効です',
        'error_code' => 'ACCOUNT_INACTIVE'
    ], 403);
}

$name = trim((string)$user->name);

$roles = $user->getRoles();
$isFullTimeTeacher = in_array('full_time_teacher', $roles, true);

// クライアント側の入力可否に依存せず、代理検索を許可する利用者をAPI側で限定する。
$canSelectUser = $user->hasPermission('manage_users') || $isFullTimeTeacher;

if ($requestedName !== '' && $canSelectUser) {
    // $name は検索対象者。認証中の $user 自体は変更しない。
    $name = $requestedName;
}

if (strpos(WORK_RECORD_SEARCH_GAS_URL, 'REPLACE_WITH_NEW_GAS_DEPLOYMENT_ID') !== false) {
    jsonResponse([
        'success' => false,
        'error' => 'GAS URL is not configured',
        'error_code' => 'GAS_URL_NOT_CONFIGURED'
    ], 502);
}

$query = http_build_query([
    'name' => $name,
    'yearMonth' => $yearMonth,
    'source' => WORK_RECORD_SEARCH_SOURCE
], '', '&', PHP_QUERY_RFC3986);
$url = WORK_RECORD_SEARCH_GAS_URL . '?' . $query;

// GASのJSONレスポンスをそのまま画面へ返す、薄い認証付きブリッジとして動作する。
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 12,
        'ignore_errors' => true
    ]
]);

$responseBody = @file_get_contents($url, false, $context);
if ($responseBody === false) {
    jsonResponse([
        'success' => false,
        'error' => 'Failed to call GAS API',
        'error_code' => 'GAS_REQUEST_FAILED'
    ], 502);
}

$decoded = json_decode($responseBody, true);
if (!is_array($decoded)) {
    jsonResponse([
        'success' => false,
        'error' => 'Invalid JSON response from GAS API',
        'error_code' => 'GAS_INVALID_JSON'
    ], 502);
}

jsonResponse($decoded);
