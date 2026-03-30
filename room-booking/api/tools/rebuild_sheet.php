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
$sheetsSync = new SheetsSync($client, $sheetsConfig, $roomsRepo, $reservationsRepo);

if (!$sheetsSync->isEnabled()) {
    fwrite(STDERR, "Sheets sync is disabled.\n");
    exit(1);
}

$spreadsheetId = $sheetsConfig['spreadsheet_id'];

function valuesPath(string $spreadsheetId, string $range): string
{
    return sprintf('spreadsheets/%s/values/%s', $spreadsheetId, rawurlencode($range));
}

function clearRange(SheetsClient $client, string $spreadsheetId, string $range): void
{
    $client->post(valuesPath($spreadsheetId, $range) . ':clear', []);
}

try {
    $rooms = $roomsRepo->getAll();
    $roomRows = array_map(function ($room) {
        return [$room['roomId'], $room['roomName'], $room['capacity'], $room['equipment']];
    }, $rooms);

    $roomsRange = "'Rooms'!A1:D";
    clearRange($client, $spreadsheetId, $roomsRange);
    $client->put(valuesPath($spreadsheetId, $roomsRange), [
        'range' => $roomsRange,
        'majorDimension' => 'ROWS',
        'values' => array_merge([['roomId', 'roomName', 'capacity', 'equipment']], $roomRows),
    ], ['valueInputOption' => 'USER_ENTERED']);

    $reservationsRange = "'Reservations'!A1:M";
    clearRange($client, $spreadsheetId, $reservationsRange);

    $reservations = $pdo->query('SELECT * FROM reservations ORDER BY date, start_time')->fetchAll();
    $reservationRows = array_map(function ($row) {
        return [
            $row['reservation_id'],
            $row['room_id'],
            $row['date'],
            substr($row['start_time'], 0, 5),
            $row['duration_minutes'],
            $row['meeting_name'],
            $row['reserver_name'],
            $row['visitor_name'],
            $row['created_at'],
            $row['updated_at'],
            $row['room_name'],
            $row['recurring_event_id'],
            $row['recurrence_start_date'],
        ];
    }, $reservations);

    $client->put(valuesPath($spreadsheetId, $reservationsRange), [
        'range' => $reservationsRange,
        'majorDimension' => 'ROWS',
        'values' => array_merge([
            [
                'reservationId', 'roomId', 'date', 'startTime', 'durationMinutes',
                'meetingName', 'reserverName', 'visitorName', 'createdAt', 'updatedAt',
                'roomName', 'recurringEventId', 'recurrenceStartDate'
            ]
        ], $reservationRows),
    ], ['valueInputOption' => 'USER_ENTERED']);

    $dateJsonRange = "'日別JSON'!A1:C";
    clearRange($client, $spreadsheetId, $dateJsonRange);
    $client->put(valuesPath($spreadsheetId, $dateJsonRange), [
        'range' => $dateJsonRange,
        'majorDimension' => 'ROWS',
        'values' => [['日付', 'JSON', '更新日時']],
    ], ['valueInputOption' => 'RAW']);

    $dates = $pdo->query('SELECT DISTINCT date FROM reservations ORDER BY date')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($dates as $date) {
        $sheetsSync->syncDateJson($date);
    }

    fwrite(STDOUT, "Rebuild completed.\n");
} catch (Exception $e) {
    fwrite(STDERR, "Rebuild failed: " . $e->getMessage() . "\n");
    exit(1);
}
