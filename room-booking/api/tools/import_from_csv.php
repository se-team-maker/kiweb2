<?php
/**
 * CSVファイルから予約を一括インポート
 * 
 * CSV列構成:
 * 0: 行
 * 1: 記録日時 (created_at)
 * 2: 予約ID (reservation_id)
 * 3: 日付 (26/01/20(火))
 * 4: 開始時刻
 * 5: 終了時刻
 * 6: 部屋 (292面談B)
 * 7: 予約者
 * 8: 会議名
 * 9: 来客
 * 
 * 使用方法:
 * php import_from_csv.php [csvファイルパス]
 */

require __DIR__ . '/../bootstrap.php';
$config = require __DIR__ . '/../config.php';

$csvFile = $argv[1] ?? __DIR__ . '/import_data.csv';

if (!file_exists($csvFile)) {
    fwrite(STDERR, "CSV file not found: {$csvFile}\n");
    exit(1);
}

$db = new Database($config['db']);
$pdo = $db->pdo();
$roomsRepo = new RoomsRepository($pdo);

/**
 * 部屋文字列からroomIdとroomNameを抽出
 */
function parseRoom(string $roomStr): array
{
    if (preg_match('/^(\d+)(.*)$/', $roomStr, $matches)) {
        return [(int) $matches[1], trim($matches[2])];
    }
    return [0, $roomStr];
}

/**
 * 開始時刻と終了時刻から所要時間（分）を計算
 */
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

/**
 * 日付を正規化
 */
function normalizeUserDate(string $dateStr): string
{
    $dateStr = preg_replace('/\([^)]*\)/', '', $dateStr);
    $dateStr = trim($dateStr);

    // yy/mm/dd -> 20yy-mm-dd
    if (preg_match('/^(\d{2})\/(\d{2})\/(\d{2})$/', $dateStr, $m)) {
        return '20' . $m[1] . '-' . $m[2] . '-' . $m[3];
    }

    // yyyy/mm/dd -> yyyy-mm-dd
    if (preg_match('/^(\d{4})\/(\d{2})\/(\d{2})$/', $dateStr, $m)) {
        return $m[1] . '-' . $m[2] . '-' . $m[3];
    }

    return Utils::normalizeDate($dateStr);
}

// データ読み込み
$file = fopen($csvFile, 'r');
$headers = fgetcsv($file); // 1行目はヘッダーとしてスキップ

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

    // 部屋確保用
    $roomStmt = $pdo->prepare('INSERT IGNORE INTO rooms (room_id, room_name, capacity) VALUES (:id, :name, 4)');

    $imported = 0;
    $rowNum = 1;

    while (($row = fgetcsv($file)) !== false) {
        $rowNum++;

        // 必須列チェック
        if (count($row) < 10) {
            continue;
        }

        $createdAt = $row[1] ?? date('Y-m-d H:i:s');
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $createdAt)) {
            $createdAt .= ':00';
        }
        $reservationId = $row[2] ?? uniqid();
        $dateStr = $row[3] ?? '';
        $startTime = $row[4] ?? '';
        $endTime = $row[5] ?? '';
        $roomStr = $row[6] ?? '';
        $reserverName = $row[7] ?? '';
        $meetingName = $row[8] ?? '';
        $visitorName = $row[9] ?? '';

        $date = normalizeUserDate($dateStr);
        $normStartTime = Utils::normalizeTime($startTime) ?: '09:00';
        $normEndTime = Utils::normalizeTime($endTime) ?: '10:00';
        $duration = calculateDuration($normStartTime, $normEndTime);

        [$roomId, $roomName] = parseRoom($roomStr);

        if (!$roomId || !$date) {
            echo "Row {$rowNum}: Invalid data (roomId: {$roomId}, date: {$date})\n";
            continue;
        }

        // 部屋が存在しない場合は作成
        $roomStmt->execute([':id' => $roomId, ':name' => $roomName]);

        $insertStmt->execute([
            ':reservation_id' => $reservationId,
            ':room_id' => $roomId,
            ':date' => $date,
            ':start_time' => $normStartTime . ':00',
            ':duration_minutes' => $duration,
            ':meeting_name' => $meetingName,
            ':reserver_name' => $reserverName,
            ':visitor_name' => $visitorName,
            ':created_at' => $createdAt,
            ':room_name' => $roomName,
        ]);
        $imported++;
    }

    $pdo->commit();
    fwrite(STDOUT, "Import completed. {$imported} records imported.\n");

} catch (Exception $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Import failed: " . $e->getMessage() . "\n");
    exit(1);
}
