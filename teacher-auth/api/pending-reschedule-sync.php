<?php

require_once __DIR__ . '/../public/bootstrap.php';

const PENDING_RESCHEDULE_SYNC_CACHE_PATH = __DIR__ . '/../storage/pending_reschedule_cache.json';
const PENDING_RESCHEDULE_SYNC_HEADER = 'X-Kiweb-Pending-Sync-Secret';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

$expectedSecret = (string)($_ENV['KIWEB_PENDING_SYNC_SECRET'] ?? (getenv('KIWEB_PENDING_SYNC_SECRET') ?: ''));
$providedSecret = getRequestHeaderValue(PENDING_RESCHEDULE_SYNC_HEADER);

if ($expectedSecret === '') {
    errorResponse('Sync secret is not configured', 500);
}

if ($providedSecret === '' || !hash_equals($expectedSecret, $providedSecret)) {
    errorResponse('Unauthorized', 403);
}

$rawInput = file_get_contents('php://input');
if ($rawInput === false || trim($rawInput) === '') {
    errorResponse('Invalid JSON payload', 400);
}

$decoded = json_decode($rawInput, true);
if (!is_array($decoded)) {
    errorResponse('Invalid JSON payload', 400);
}

$rows = normalizePendingRowsForSave($decoded);
$updatedAt = normalizeUpdatedAt($decoded['updated_at'] ?? $decoded['updatedAt'] ?? null);

$payload = [
    'updated_at' => $updatedAt,
    'rows' => $rows,
];

$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false) {
    errorResponse('Failed to encode cache JSON', 500);
}

$dir = dirname(PENDING_RESCHEDULE_SYNC_CACHE_PATH);
if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
    errorResponse('Failed to prepare cache directory', 500);
}

if (file_put_contents(PENDING_RESCHEDULE_SYNC_CACHE_PATH, $json, LOCK_EX) === false) {
    errorResponse('Failed to write cache file', 500);
}

jsonResponse([
    'success' => true,
    'rows_count' => count($rows),
    'updated_at' => $updatedAt,
]);

function getRequestHeaderValue(string $headerName): string
{
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $headerName));
    $value = $_SERVER[$serverKey] ?? '';
    if (is_string($value) && trim($value) !== '') {
        return trim($value);
    }

    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $name => $headerValue) {
                if (strcasecmp((string)$name, $headerName) === 0) {
                    return is_string($headerValue) ? trim($headerValue) : '';
                }
            }
        }
    }

    return '';
}

function normalizeUpdatedAt($value): string
{
    if (is_int($value) || is_float($value)) {
        $dt = new DateTimeImmutable('@' . (string)$value);
        return $dt->setTimezone(new DateTimeZone('Asia/Tokyo'))->format(DateTimeInterface::ATOM);
    }

    if (is_string($value) && trim($value) !== '') {
        return trim($value);
    }

    $dt = new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo'));
    return $dt->format(DateTimeInterface::ATOM);
}

function normalizePendingRowsForSave(array $decoded): array
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

        $rows[] = normalizePendingAssocRow($record);
    }

    return $rows;
}

function normalizeIndexedPendingRow(array $record): array
{
    return [
        '授業名' => (string)($record[0] ?? ''),
        '講師名' => (string)($record[1] ?? ''),
        '予定開始日時' => (string)($record[2] ?? ''),
        '予定終了日時' => (string)($record[3] ?? ''),
        '予定業務No' => (string)($record[4] ?? ''),
        '当初予定業務No' => (string)($record[10] ?? ''),
        'コマ符号' => (string)($record[5] ?? ''),
        '生徒名' => (string)($record[6] ?? ''),
        'STATUS' => (string)($record[7] ?? ''),
        '対応状況' => (string)($record[7] ?? ''),
        '実施' => (string)($record[7] ?? ''),
        '授業形態詳細' => (string)($record[8] ?? ''),
        '授業実施校舎' => (string)($record[9] ?? ''),
    ];
}

function normalizePendingAssocRow(array $record): array
{
    return [
        '授業名' => (string)($record['授業名'] ?? $record['className'] ?? ''),
        '講師名' => (string)($record['講師名'] ?? $record['teacherName'] ?? ''),
        '予定開始日時' => (string)($record['予定開始日時'] ?? $record['startDateTime'] ?? ''),
        '予定終了日時' => (string)($record['予定終了日時'] ?? $record['endDateTime'] ?? ''),
        '予定業務No' => (string)($record['予定業務No'] ?? $record['businessNo'] ?? ''),
        '当初予定業務No' => (string)($record['当初予定業務No'] ?? $record['originalBusinessNo'] ?? ''),
        'コマ符号' => (string)($record['コマ符号'] ?? $record['komaSymbol'] ?? ''),
        '生徒名' => (string)($record['生徒名'] ?? $record['studentName'] ?? $record['生徒情報(カンマ区切り)'] ?? ''),
        'STATUS' => (string)($record['STATUS'] ?? $record['status'] ?? $record['対応状況'] ?? $record['実施'] ?? ''),
        '対応状況' => (string)($record['対応状況'] ?? $record['実施'] ?? $record['STATUS'] ?? $record['status'] ?? ''),
        '実施' => (string)($record['実施'] ?? $record['対応状況'] ?? $record['STATUS'] ?? $record['status'] ?? ''),
        '授業形態詳細' => (string)($record['授業形態詳細'] ?? $record['lessonDetail'] ?? ''),
        '授業実施校舎' => (string)($record['授業実施校舎'] ?? $record['school'] ?? ''),
        '生徒情報(カンマ区切り)' => (string)($record['生徒情報(カンマ区切り)'] ?? $record['生徒名'] ?? $record['studentName'] ?? ''),
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
