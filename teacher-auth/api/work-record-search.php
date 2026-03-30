<?php

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
if ($requestedName !== '' && $user->hasPermission('manage_users')) {
    $name = $requestedName;
}
if ($name === '') {
    jsonResponse([
        'success' => false,
        'error' => 'ユーザー名が取得できません',
        'error_code' => 'EMPTY_USER_NAME'
    ], 401);
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
