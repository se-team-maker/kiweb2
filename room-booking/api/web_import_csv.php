<?php
/**
 * WebブラウザからCSVインポートを実行するためのスクリプト
 * 
 * 警告: このファイルは作業完了後に削除してください。
 */

header('Content-Type: text/html; charset=UTF-8');
require __DIR__ . '/bootstrap.php';
$config = require __DIR__ . '/config.php';

// 簡易的なアクセス制限（必要ならIP制限などを追加）
// if ($_SERVER['REMOTE_ADDR'] !== 'ALLOWED_IP') { die('Access denied'); }

$csvFile = __DIR__ . '/tools/import_data.csv';

if (!file_exists($csvFile)) {
    die("Error: CSV file not found at {$csvFile}");
}

// タイムアウト設定を延長
set_time_limit(300);

echo "<h1>Reservations Import</h1>";
echo "<p>Starting import...</p>";

$db = new Database($config['db']);
$pdo = $db->pdo();
$roomsRepo = new RoomsRepository($pdo);

// functions (from import_from_csv.php)
function parseRoom(string $roomStr): array
{
    if (preg_match('/^(\d+)(.*)$/', $roomStr, $matches)) {
        return [(int) $matches[1], trim($matches[2])];
    }
    return [0, $roomStr];
}

function calculateDuration(string $startTime, string $endTime): int
{
    $start = strtotime($startTime);
    $end = strtotime($endTime);
    if ($start === false || $end === false) {
        return 60;
    }
    $diffMinutes = ($end - $start) / 60;
    return $diffMinutes > 0 ? (int) $diffMinutes : 60;
}

function normalizeUserDate(string $dateStr): string
{
    $dateStr = preg_replace('/\([^)]*\)/', '', $dateStr);
    $dateStr = trim($dateStr);
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{2})$/', $dateStr, $m)) {
        return '20' . $m[1] . '-' . $m[2] . '-' . $m[3];
    }
    if (preg_match('/^(\d{4})\/(\d{2})\/(\d{2})$/', $dateStr, $m)) {
        return $m[1] . '-' . $m[2] . '-' . $m[3];
    }
    return Utils::normalizeDate($dateStr);
}

// データ読み込み
$file = fopen($csvFile, 'r');
$headers = fgetcsv($file); // Skip header

$pdo->beginTransaction();
try {
    $insertStmt = $pdo->prepare(
        'INSERT INTO reservations (
            reservation_id, room_id, date, start_time, duration_minutes,
            meeting_name, reserver_name, visitor_name, created_at, updated_at, room_name
        ) VALUES (
            :reservation_id, :room_id, :date, :start_time, :duration_minutes,
            :meeting_name, :reserver_name, :visitor_name, :created_at, NOW(), :room_name
        ) ON DUPLICATE KEY UPDATE
            room_id = VALUES(room_id),
            date = VALUES(date),
            start_time = VALUES(start_time),
            duration_minutes = VALUES(duration_minutes),
            meeting_name = VALUES(meeting_name),
            reserver_name = VALUES(reserver_name),
            visitor_name = VALUES(visitor_name),
            room_name = VALUES(room_name),
            updated_at = NOW()'
    );

    $roomStmt = $pdo->prepare('INSERT IGNORE INTO rooms (room_id, room_name, capacity) VALUES (:id, :name, 4)');

    $imported = 0;
    $rowNum = 1;

    echo "<ul>";

    while (($row = fgetcsv($file)) !== false) {
        $rowNum++;
        if (count($row) < 10) {
            continue;
        }

        $createdAt = $row[1] ?? date('Y-m-d H:i:s');
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $createdAt)) {
            $createdAt .= ':00';
        }
        $reservationId = $row[2] ?? uniqid();
        $date = normalizeUserDate($row[3] ?? '');
        $startTime = Utils::normalizeTime($row[4] ?? '') ?: '09:00';
        $endTime = Utils::normalizeTime($row[5] ?? '') ?: '10:00';
        $duration = calculateDuration($startTime, $endTime);
        [$roomId, $roomName] = parseRoom($row[6] ?? '');
        $reserverName = $row[7] ?? '';
        $meetingName = $row[8] ?? '';
        $visitorName = $row[9] ?? '';

        if (!$roomId || !$date) {
            echo "<li style='color:red'>Skipped Row {$rowNum}: Invalid data</li>";
            continue;
        }

        $roomStmt->execute([':id' => $roomId, ':name' => $roomName]);

        $insertStmt->execute([
            ':reservation_id' => $reservationId,
            ':room_id' => $roomId,
            ':date' => $date,
            ':start_time' => $startTime . ':00',
            ':duration_minutes' => $duration,
            ':meeting_name' => $meetingName,
            ':reserver_name' => $reserverName,
            ':visitor_name' => $visitorName,
            ':created_at' => $createdAt,
            ':room_name' => $roomName,
        ]);
        $imported++;
    }

    echo "</ul>";

    $pdo->commit();
    echo "<h2>Import completed!</h2>";
    echo "<p><strong>{$imported}</strong> records imported.</p>";

} catch (Exception $e) {
    $pdo->rollBack();
    echo "<h2 style='color:red'>Import failed</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
}
