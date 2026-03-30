<?php

require __DIR__ . '/../bootstrap.php';

$config = require __DIR__ . '/../config.php';
$sheetsConfig = $config['sheets'] ?? [];

if (empty($sheetsConfig['spreadsheet_id']) || empty($sheetsConfig['service_account_json'])) {
    fwrite(STDERR, "Sheets config is missing.\n");
    exit(1);
}

$db = new Database($config['db']);
$pdo = $db->pdo();
$roomsRepo = new RoomsRepository($pdo);
$reservationsRepo = new ReservationsRepository($pdo);
$client = new SheetsClient($sheetsConfig);

function fetchValues(SheetsClient $client, string $spreadsheetId, string $range): array
{
    $path = sprintf('spreadsheets/%s/values/%s', $spreadsheetId, rawurlencode($range));
    $response = $client->get($path, [
        'valueRenderOption' => 'FORMATTED_VALUE',
        'majorDimension' => 'ROWS',
    ]);
    return $response['values'] ?? [];
}

$spreadsheetId = $sheetsConfig['spreadsheet_id'];

$roomRows = fetchValues($client, $spreadsheetId, "'Rooms'!A2:D");
$reservationRows = fetchValues($client, $spreadsheetId, "'Reservations'!A2:M");

$pdo->beginTransaction();
try {
    $pdo->exec('DELETE FROM reservations');
    $pdo->exec('DELETE FROM rooms');

    $roomStmt = $pdo->prepare(
        'INSERT INTO rooms (room_id, room_name, capacity, equipment) VALUES (:id, :name, :capacity, :equipment)'
    );
    foreach ($roomRows as $row) {
        if (empty($row[0])) {
            continue;
        }
        $roomStmt->execute([
            ':id' => (int)$row[0],
            ':name' => $row[1] ?? '',
            ':capacity' => (int)($row[2] ?? 0),
            ':equipment' => $row[3] ?? '',
        ]);
    }

    $roomsRepo->ensureSeeded();

    $resStmt = $pdo->prepare(
        'INSERT INTO reservations (
            reservation_id, room_id, date, start_time, duration_minutes,
            meeting_name, reserver_name, visitor_name, created_at, updated_at,
            room_name, recurring_event_id, recurrence_start_date
        ) VALUES (
            :reservation_id, :room_id, :date, :start_time, :duration_minutes,
            :meeting_name, :reserver_name, :visitor_name, :created_at, :updated_at,
            :room_name, :recurring_event_id, :recurrence_start_date
        )'
    );

    foreach ($reservationRows as $row) {
        $reservationId = $row[0] ?? '';
        if (!$reservationId) {
            continue;
        }
        $roomId = (int)($row[1] ?? 0);
        $date = Utils::normalizeDate($row[2] ?? '');
        $startTime = Utils::normalizeTime($row[3] ?? '') ?: '00:00';
        $duration = (int)($row[4] ?? 60);
        $meetingName = $row[5] ?? '';
        $reserverName = $row[6] ?? '';
        $visitorName = $row[7] ?? '';
        $createdAt = $row[8] ?? date('Y-m-d H:i:s');
        $updatedAt = $row[9] ?? $createdAt;
        $roomName = $row[10] ?? ($roomsRepo->findById($roomId)['roomName'] ?? '');
        $recurringEventId = $row[11] ?? '';
        $recurrenceStartDate = Utils::normalizeDate($row[12] ?? '');

        $resStmt->execute([
            ':reservation_id' => $reservationId,
            ':room_id' => $roomId,
            ':date' => $date,
            ':start_time' => $startTime . ':00',
            ':duration_minutes' => $duration,
            ':meeting_name' => $meetingName,
            ':reserver_name' => $reserverName,
            ':visitor_name' => $visitorName,
            ':created_at' => $createdAt,
            ':updated_at' => $updatedAt,
            ':room_name' => $roomName,
            ':recurring_event_id' => $recurringEventId,
            ':recurrence_start_date' => $recurrenceStartDate ?: null,
        ]);
    }

    $pdo->commit();
    fwrite(STDOUT, "Import completed.\n");
} catch (Exception $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Import failed: " . $e->getMessage() . "\n");
    exit(1);
}
