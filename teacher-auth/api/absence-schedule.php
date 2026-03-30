<?php

require_once __DIR__ . '/../public/bootstrap.php';

use App\Auth\Session;
use App\Model\User;

const CACHE_PATH = __DIR__ . '/../storage/declaration_schedule_cache.json';

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

if (!is_file(CACHE_PATH) || !is_readable(CACHE_PATH)) {
    jsonResponse([
        'success' => false,
        'error' => '予定キャッシュが未生成です',
        'error_code' => 'CACHE_NOT_READY'
    ], 503);
}

$cacheJson = @file_get_contents(CACHE_PATH);
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

$rows = [];
foreach ($decoded as $record) {
    if (!is_array($record)) {
        continue;
    }
    $teacherName = trim((string)($record['講師名'] ?? ''));
    $startDt = trim((string)($record['予定開始日時'] ?? ''));
    if ($teacherName === '' && $startDt === '') {
        continue;
    }

    $rows[] = [
        (string)($record['授業名'] ?? ''),
        $teacherName,
        $startDt,
        trim((string)($record['予定終了日時'] ?? '')),
        $record['予定業務No'] ?? '',
        (string)($record['コマ符号'] ?? ''),
        (string)($record['生徒名'] ?? ''),
        (string)($record['STATUS'] ?? ''),
        (string)($record['授業形態詳細'] ?? ''),
        (string)($record['授業実施校舎'] ?? ''),
    ];
}

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
