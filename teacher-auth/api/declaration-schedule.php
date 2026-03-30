<?php

require_once __DIR__ . '/../public/bootstrap.php';

use App\Auth\Session;
use App\Model\User;

const DECLARATION_SCHEDULE_CACHE_PATH = __DIR__ . '/../storage/declaration_schedule_cache.json';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed', 405);
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!Session::isLoggedIn()) {
    jsonResponse([
        'success' => false,
        'error' => 'ログインが必要です',
        'error_code' => 'UNAUTHORIZED'
    ], 401);
}

$userId = Session::getUserId();
$user = $userId ? User::findById($userId) : null;
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

if (!is_file(DECLARATION_SCHEDULE_CACHE_PATH) || !is_readable(DECLARATION_SCHEDULE_CACHE_PATH)) {
    jsonResponse([
        'success' => false,
        'error' => '予定キャッシュが未生成です',
        'error_code' => 'CACHE_NOT_READY'
    ], 503);
}

$cacheJson = @file_get_contents(DECLARATION_SCHEDULE_CACHE_PATH);
if ($cacheJson === false) {
    jsonResponse([
        'success' => false,
        'error' => '予定キャッシュの読み込みに失敗しました',
        'error_code' => 'CACHE_READ_FAILED'
    ], 503);
}

$decoded = json_decode($cacheJson, true);
if (!is_array($decoded)) {
    jsonResponse([
        'success' => false,
        'error' => '予定キャッシュの形式が不正です',
        'error_code' => 'CACHE_INVALID_JSON'
    ], 503);
}

jsonResponse($decoded);
