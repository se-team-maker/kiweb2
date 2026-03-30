<?php

require_once __DIR__ . '/../public/bootstrap.php';

use App\Auth\Session;
use App\Model\User;

const PENDING_RESCHEDULE_CACHE_PATH = __DIR__ . '/../storage/pending_reschedule_cache.json';

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

if (!is_file(PENDING_RESCHEDULE_CACHE_PATH) || !is_readable(PENDING_RESCHEDULE_CACHE_PATH)) {
    jsonResponse([
        'success' => false,
        'error' => '処理未定キャッシュが未生成です',
        'error_code' => 'CACHE_NOT_READY'
    ], 503);
}

$cacheJson = @file_get_contents(PENDING_RESCHEDULE_CACHE_PATH);
if ($cacheJson === false) {
    jsonResponse([
        'success' => false,
        'error' => '処理未定キャッシュの読み込みに失敗しました',
        'error_code' => 'CACHE_READ_FAILED'
    ], 503);
}

$decoded = json_decode($cacheJson, true);
if (!is_array($decoded)) {
    jsonResponse([
        'success' => false,
        'error' => '処理未定キャッシュの形式が不正です',
        'error_code' => 'CACHE_INVALID_JSON'
    ], 503);
}

$rows = normalizePendingRows($decoded);
$updatedAt = extractPendingUpdatedAt($decoded);

jsonResponse([
    'success' => true,
    'rows' => $rows,
    'updated_at' => $updatedAt,
]);

function normalizePendingRows(array $decoded): array
{
    if (array_key_exists('rows', $decoded) && is_array($decoded['rows'])) {
        $sourceRows = $decoded['rows'];
    } elseif (isListArray($decoded)) {
        $sourceRows = $decoded;
    } else {
        return [];
    }

    $rows = [];
    foreach ($sourceRows as $record) {
        if (!is_array($record)) {
            continue;
        }

        if (isListArray($record)) {
            $rows[] = normalizeIndexedPendingRow($record);
            continue;
        }

        $rows[] = normalizePendingRecordToRow($record);
    }

    return $rows;
}

function extractPendingUpdatedAt(array $decoded): string
{
    $updatedAt = $decoded['updated_at'] ?? $decoded['updatedAt'] ?? '';
    if (is_int($updatedAt) || is_float($updatedAt)) {
        $dt = new DateTimeImmutable('@' . (string)$updatedAt);
        return $dt->setTimezone(new DateTimeZone('Asia/Tokyo'))->format(DateTimeInterface::ATOM);
    }

    if (is_string($updatedAt) && trim($updatedAt) !== '') {
        return trim($updatedAt);
    }

    $mtime = @filemtime(PENDING_RESCHEDULE_CACHE_PATH);
    if ($mtime !== false) {
        $dt = new DateTimeImmutable('@' . $mtime);
        return $dt->setTimezone(new DateTimeZone('Asia/Tokyo'))->format(DateTimeInterface::ATOM);
    }

    return (new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo')))->format(DateTimeInterface::ATOM);
}

function normalizePendingRecordToRow(array $record): array
{
    return [
        'className' => (string)($record['授業名'] ?? $record['className'] ?? ''),
        'teacherName' => (string)($record['講師名'] ?? $record['teacherName'] ?? ''),
        'startDateTime' => (string)($record['予定開始日時'] ?? $record['startDateTime'] ?? ''),
        'endDateTime' => (string)($record['予定終了日時'] ?? $record['endDateTime'] ?? ''),
        'businessNo' => (string)($record['予定業務No'] ?? $record['businessNo'] ?? ''),
        'originalBusinessNo' => (string)($record['当初予定業務No'] ?? $record['originalBusinessNo'] ?? ''),
        'komaSymbol' => (string)($record['コマ符号'] ?? $record['komaSymbol'] ?? ''),
        'studentName' => (string)($record['生徒名'] ?? $record['studentName'] ?? $record['生徒情報(カンマ区切り)'] ?? ''),
        'status' => (string)($record['STATUS'] ?? $record['status'] ?? $record['対応状況'] ?? $record['実施'] ?? ''),
        'lessonDetail' => (string)($record['授業形態詳細'] ?? $record['lessonDetail'] ?? ''),
        'school' => (string)($record['授業実施校舎'] ?? $record['school'] ?? ''),
    ];
}

function normalizeIndexedPendingRow(array $record): array
{
    return [
        'className' => (string)($record[0] ?? ''),
        'teacherName' => (string)($record[1] ?? ''),
        'startDateTime' => (string)($record[2] ?? ''),
        'endDateTime' => (string)($record[3] ?? ''),
        'businessNo' => (string)($record[4] ?? ''),
        'originalBusinessNo' => (string)($record[10] ?? ''),
        'komaSymbol' => (string)($record[5] ?? ''),
        'studentName' => (string)($record[6] ?? ''),
        'status' => (string)($record[7] ?? ''),
        'lessonDetail' => (string)($record[8] ?? ''),
        'school' => (string)($record[9] ?? ''),
    ];
}

function isListArray(array $value): bool
{
    $expectedKey = 0;
    foreach (array_keys($value) as $key) {
        if ($key !== $expectedKey) {
            return false;
        }
        $expectedKey++;
    }

    return true;
}
