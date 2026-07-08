<?php

class SheetsSync
{
    private $client;
    private $spreadsheetId;
    private $enabled;
    private $roomsRepo;
    private $reservationsRepo;
    private $sheetMap;

    private const ROOMS_SHEET = 'Rooms';
    private const RESERVATIONS_SHEET = 'Reservations';
    private const DATE_JSON_SHEET = '日別JSON';

    private const ROOM_HEADERS = ['roomId', 'roomName', 'capacity', 'equipment'];
    private const RESERVATION_HEADERS = [
        'reservationId', 'roomId', 'date', 'startTime', 'durationMinutes',
        'meetingName', 'reserverName', 'visitorName', 'createdAt', 'updatedAt',
        'roomName', 'recurringEventId', 'recurrenceStartDate'
    ];

    public function __construct(
        ?SheetsClient $client,
        array $config,
        RoomsRepository $roomsRepo,
        ReservationsRepository $reservationsRepo
    ) {
        $this->client = $client;
        $this->spreadsheetId = $config['spreadsheet_id'] ?? '';
        $this->enabled = ($config['enable_sync'] ?? false) && !empty($this->spreadsheetId);
        $this->roomsRepo = $roomsRepo;
        $this->reservationsRepo = $reservationsRepo;
        $this->sheetMap = null;
    }

    public function isEnabled(): bool
    {
        return $this->enabled && $this->client !== null;
    }

    public function syncRooms(): void
    {
        if (!$this->isEnabled()) {
            return;
        }
        $this->ensureSheet(self::ROOMS_SHEET, self::ROOM_HEADERS);
        $rooms = $this->roomsRepo->getAll();
        $rows = array_map(function ($room) {
            return [$room['roomId'], $room['roomName'], $room['capacity'], $room['equipment']];
        }, $rooms);

        $range = $this->range(self::ROOMS_SHEET, 'A2:D');
        $this->client->put($this->valuesPath($range), [
            'range' => $range,
            'majorDimension' => 'ROWS',
            'values' => $rows,
        ], ['valueInputOption' => 'USER_ENTERED']);
    }

    public function appendReservation(array $reservation): void
    {
        if (!$this->isEnabled()) {
            return;
        }
        $this->ensureSheet(self::RESERVATIONS_SHEET, self::RESERVATION_HEADERS);
        $reservationId = (string)($reservation['reservationId'] ?? '');
        if ($reservationId !== '') {
            $rowIndexes = $this->findRowIndexes(self::RESERVATIONS_SHEET, $reservationId, 'A2:A');
            if ($rowIndexes) {
                $this->updateReservationRow($rowIndexes[0], $reservation);
                if (count($rowIndexes) > 1) {
                    $this->deleteRows(self::RESERVATIONS_SHEET, array_slice($rowIndexes, 1));
                }
                return;
            }
        }
        $this->appendReservationRaw($reservation);
    }

    public function updateReservation(string $reservationId, array $reservation): void
    {
        if (!$this->isEnabled()) {
            return;
        }
        $this->ensureSheet(self::RESERVATIONS_SHEET, self::RESERVATION_HEADERS);
        $rowIndexes = $this->findRowIndexes(self::RESERVATIONS_SHEET, $reservationId, 'A2:A');
        if (!$rowIndexes) {
            $this->appendReservationRaw($reservation);
            return;
        }
        $this->updateReservationRow($rowIndexes[0], $reservation);
        if (count($rowIndexes) > 1) {
            $this->deleteRows(self::RESERVATIONS_SHEET, array_slice($rowIndexes, 1));
        }
    }

    public function deleteReservation(string $reservationId): void
    {
        if (!$this->isEnabled()) {
            return;
        }
        $this->ensureSheet(self::RESERVATIONS_SHEET, self::RESERVATION_HEADERS);
        $rowIndexes = $this->findRowIndexes(self::RESERVATIONS_SHEET, $reservationId, 'A2:A');
        if (!$rowIndexes) {
            return;
        }
        $this->deleteRows(self::RESERVATIONS_SHEET, $rowIndexes);
    }

    public function syncDateJson(string $date): void
    {
        if (!$this->isEnabled()) {
            return;
        }
        $this->ensureSheet(self::DATE_JSON_SHEET, ['日付', 'JSON', '更新日時']);
        $jsonData = $this->buildDateJson($date);
        $rowIndex = $this->findRowIndex(self::DATE_JSON_SHEET, $date, 'A2:A');
        $values = [[
            $date,
            json_encode($jsonData, JSON_UNESCAPED_UNICODE),
            date('Y-m-d H:i:s'),
        ]];
        if ($rowIndex === null) {
            $range = $this->range(self::DATE_JSON_SHEET, 'A2:C');
            $this->client->post($this->valuesPath($range) . ':append', [
                'range' => $range,
                'majorDimension' => 'ROWS',
                'values' => $values,
            ], ['valueInputOption' => 'RAW', 'insertDataOption' => 'INSERT_ROWS']);
            return;
        }
        $range = $this->range(self::DATE_JSON_SHEET, 'A' . $rowIndex . ':C' . $rowIndex);
        $this->client->put($this->valuesPath($range), [
            'range' => $range,
            'majorDimension' => 'ROWS',
            'values' => $values,
        ], ['valueInputOption' => 'RAW']);
    }

    public function dedupeReservationsSheet(): array
    {
        if (!$this->isEnabled()) {
            return ['enabled' => false, 'deletedRows' => 0];
        }
        $this->ensureSheet(self::RESERVATIONS_SHEET, self::RESERVATION_HEADERS);

        $range = $this->range(self::RESERVATIONS_SHEET, 'A2:A');
        $response = $this->client->get($this->valuesPath($range));
        $values = $response['values'] ?? [];

        $seen = [];
        $duplicates = [];
        foreach ($values as $index => $row) {
            $reservationId = (string)($row[0] ?? '');
            if ($reservationId === '') {
                continue;
            }
            if (isset($seen[$reservationId])) {
                $duplicates[] = $index + 2;
                continue;
            }
            $seen[$reservationId] = true;
        }

        if ($duplicates) {
            $this->deleteRows(self::RESERVATIONS_SHEET, $duplicates);
        }

        return [
            'enabled' => true,
            'deletedRows' => count($duplicates),
        ];
    }

    /**
     * DBからデータを取得してシートを完全再構築
     * @return array 処理結果
     */
    public function rebuildAllSheets(): array
    {
        if (!$this->isEnabled()) {
            return ['enabled' => false, 'message' => 'Sheets sync is disabled'];
        }

        $startTime = microtime(true);
        $result = [
            'enabled' => true,
            'rooms' => 0,
            'reservations' => 0,
            'dateJsons' => 0,
        ];

        // 1. Roomsシートを再構築
        $this->ensureSheet(self::ROOMS_SHEET, self::ROOM_HEADERS);
        $this->clearSheetData(self::ROOMS_SHEET, 'A2:D');
        $rooms = $this->roomsRepo->getAll();
        if (!empty($rooms)) {
            $roomRows = array_map(function ($room) {
                return [$room['roomId'], $room['roomName'], $room['capacity'], $room['equipment']];
            }, $rooms);
            $range = $this->range(self::ROOMS_SHEET, 'A2:D');
            $this->client->put($this->valuesPath($range), [
                'range' => $range,
                'majorDimension' => 'ROWS',
                'values' => $roomRows,
            ], ['valueInputOption' => 'USER_ENTERED']);
            $result['rooms'] = count($rooms);
        }

        // 2. Reservationsシートを再構築
        $this->ensureSheet(self::RESERVATIONS_SHEET, self::RESERVATION_HEADERS);
        $this->clearSheetData(self::RESERVATIONS_SHEET, 'A2:M');
        $reservations = $this->reservationsRepo->getAll();
        if (!empty($reservations)) {
            $reservationRows = array_map(function ($row) {
                return $this->buildReservationRow($row);
            }, $reservations);
            $range = $this->range(self::RESERVATIONS_SHEET, 'A2:M');
            $this->client->put($this->valuesPath($range), [
                'range' => $range,
                'majorDimension' => 'ROWS',
                'values' => $reservationRows,
            ], ['valueInputOption' => 'USER_ENTERED']);
            $result['reservations'] = count($reservations);
        }

        // 3. 日別JSONシートを再構築（完全同期は read を発生させない）
        $this->ensureSheet(self::DATE_JSON_SHEET, ['日付', 'JSON', '更新日時']);
        $this->clearSheetData(self::DATE_JSON_SHEET, 'A2:C');
        $dates = $this->reservationsRepo->getDistinctDates();
        $dates = is_array($dates) ? $dates : [];
        $result['dateJsons'] = count($dates);

        if (!empty($dates)) {
            $now = date('Y-m-d H:i:s');
            $rows = [];
            foreach ($dates as $date) {
                $jsonData = $this->buildDateJson((string)$date);
                $rows[] = [
                    (string)$date,
                    json_encode($jsonData, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                    $now,
                ];
            }

            // 200行ずつまとめ書き
            $chunkSize = 200;
            $startRow = 2;
            for ($i = 0; $i < count($rows); $i += $chunkSize) {
                $chunk = array_slice($rows, $i, $chunkSize);
                $endRow = $startRow + count($chunk) - 1;

                $range = $this->range(self::DATE_JSON_SHEET, "A{$startRow}:C{$endRow}");
                $this->client->put($this->valuesPath($range), [
                    'majorDimension' => 'ROWS',
                    'values' => $chunk,
                ], ['valueInputOption' => 'RAW']);

                $startRow = $endRow + 1;
            }
        }

        $result['executionTimeMs'] = round((microtime(true) - $startTime) * 1000);
        return $result;
    }

    /**
     * シートの指定範囲をクリア
     */
    private function clearSheetData(string $sheetName, string $range): void
    {
        $sheetRange = $this->range($sheetName, $range);
        $this->client->post($this->valuesPath($sheetRange) . ':clear', new stdClass());
    }

    private function buildDateJson(string $date): array
    {
        $reservations = $this->reservationsRepo->getByDate($date);
        $result = [
            'header' => [
                'reservationId', 'roomId', 'roomName', 'startTime',
                'durationMinutes', 'meetingName', 'reserverName', 'visitorName'
            ],
        ];
        foreach ($reservations as $reservation) {
            $roomName = $reservation['roomName'] ?? '';
            $key = $roomName . '_' . $reservation['startTime'];
            $result[$key] = [
                $reservation['reservationId'],
                $reservation['roomId'],
                $roomName,
                $reservation['startTime'],
                $reservation['durationMinutes'],
                $reservation['meetingName'] ?? '',
                $reservation['reserverName'] ?? '',
                $reservation['visitorName'] ?? '',
            ];
        }
        return $result;
    }

    private function buildReservationRow(array $reservation): array
    {
        return [
            $reservation['reservationId'] ?? '',
            $reservation['roomId'] ?? '',
            $reservation['date'] ?? '',
            $reservation['startTime'] ?? '',
            $reservation['durationMinutes'] ?? '',
            $reservation['meetingName'] ?? '',
            $reservation['reserverName'] ?? '',
            $reservation['visitorName'] ?? '',
            $reservation['createdAt'] ?? '',
            $reservation['updatedAt'] ?? '',
            $reservation['roomName'] ?? '',
            $reservation['recurringEventId'] ?? '',
            $reservation['recurrenceStartDate'] ?? '',
        ];
    }

    private function ensureSheet(string $name, array $headers): void
    {
        $sheetId = $this->getSheetId($name);
        if ($sheetId === null) {
            $path = sprintf('spreadsheets/%s:batchUpdate', $this->spreadsheetId);
            $this->client->post($path, [
                'requests' => [[
                    'addSheet' => [
                        'properties' => [
                            'title' => $name,
                        ],
                    ],
                ]],
            ]);
            $this->sheetMap = null;
            $sheetId = $this->getSheetId($name);
        }

        $range = $this->range($name, '1:1');
        $response = $this->client->get($this->valuesPath($range));
        $row = $response['values'][0] ?? [];
        $needsHeader = empty($row);
        if (!$needsHeader) {
            $existing = array_map('strval', $row);
            foreach ($headers as $header) {
                if (!in_array($header, $existing, true)) {
                    $row[] = $header;
                    $needsHeader = true;
                }
            }
        } else {
            $row = $headers;
        }
        if ($needsHeader) {
            $this->client->put($this->valuesPath($range), [
                'range' => $range,
                'majorDimension' => 'ROWS',
                'values' => [$row],
            ], ['valueInputOption' => 'RAW']);
        }
    }

    private function loadSheetMap(): void
    {
        if ($this->sheetMap !== null) {
            return;
        }
        $path = sprintf('spreadsheets/%s', $this->spreadsheetId);
        $response = $this->client->get($path, ['fields' => 'sheets.properties']);
        $map = [];
        foreach ($response['sheets'] ?? [] as $sheet) {
            $props = $sheet['properties'] ?? [];
            if (!empty($props['title'])) {
                $map[$props['title']] = $props['sheetId'];
            }
        }
        $this->sheetMap = $map;
    }

    private function getSheetId(string $name): ?int
    {
        $this->loadSheetMap();
        return $this->sheetMap[$name] ?? null;
    }

    private function findRowIndex(string $sheetName, string $searchValue, string $range): ?int
    {
        $sheetRange = $this->range($sheetName, $range);
        $response = $this->client->get($this->valuesPath($sheetRange));
        $values = $response['values'] ?? [];
        foreach ($values as $index => $row) {
            $value = $row[0] ?? '';
            if ((string)$value === (string)$searchValue) {
                return $index + 2;
            }
        }
        return null;
    }

    private function findRowIndexes(string $sheetName, string $searchValue, string $range): array
    {
        $sheetRange = $this->range($sheetName, $range);
        $response = $this->client->get($this->valuesPath($sheetRange));
        $values = $response['values'] ?? [];
        $rowIndexes = [];
        foreach ($values as $index => $row) {
            $value = $row[0] ?? '';
            if ((string)$value === (string)$searchValue) {
                $rowIndexes[] = $index + 2;
            }
        }
        return $rowIndexes;
    }

    private function updateReservationRow(int $rowIndex, array $reservation): void
    {
        $range = $this->range(self::RESERVATIONS_SHEET, 'A' . $rowIndex . ':M' . $rowIndex);
        $this->client->put($this->valuesPath($range), [
            'range' => $range,
            'majorDimension' => 'ROWS',
            'values' => [$this->buildReservationRow($reservation)],
        ], ['valueInputOption' => 'USER_ENTERED']);
    }

    private function appendReservationRaw(array $reservation): void
    {
        $range = $this->range(self::RESERVATIONS_SHEET, 'A2:M');
        $this->client->post($this->valuesPath($range) . ':append', [
            'range' => $range,
            'majorDimension' => 'ROWS',
            'values' => [$this->buildReservationRow($reservation)],
        ], ['valueInputOption' => 'USER_ENTERED', 'insertDataOption' => 'INSERT_ROWS']);
    }

    private function deleteRows(string $sheetName, array $rowIndexes): void
    {
        $rowIndexes = array_values(array_unique(array_map('intval', $rowIndexes)));
        $rowIndexes = array_values(array_filter($rowIndexes, function ($idx) {
            return $idx >= 2;
        }));
        if (!$rowIndexes) {
            return;
        }
        rsort($rowIndexes);

        $sheetId = $this->getSheetId($sheetName);
        if ($sheetId === null) {
            return;
        }

        $path = sprintf('spreadsheets/%s:batchUpdate', $this->spreadsheetId);
        $chunks = array_chunk($rowIndexes, 200);
        foreach ($chunks as $chunk) {
            $requests = [];
            foreach ($chunk as $rowIndex) {
                $requests[] = [
                    'deleteDimension' => [
                        'range' => [
                            'sheetId' => $sheetId,
                            'dimension' => 'ROWS',
                            'startIndex' => $rowIndex - 1,
                            'endIndex' => $rowIndex,
                        ],
                    ],
                ];
            }
            $this->client->post($path, ['requests' => $requests]);
        }
    }

    private function range(string $sheet, string $range): string
    {
        return "'" . $sheet . "'!" . $range;
    }

    private function valuesPath(string $range): string
    {
        return sprintf('spreadsheets/%s/values/%s', $this->spreadsheetId, rawurlencode($range));
    }

    private function logError(string $action, Exception $e): void
    {
        $message = sprintf('[SheetsSync:%s] %s', $action, $e->getMessage());
        error_log($message);
    }
}
