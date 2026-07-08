<?php

class ReservationService
{
    private $db;
    private $roomsRepo;
    private $reservationsRepo;
    private $sheetsSync;
    private $sheetsQueue;
    private $syncMode;
    private $maxCandidates;

    public function __construct(
        Database $db,
        RoomsRepository $roomsRepo,
        ReservationsRepository $reservationsRepo,
        SheetsSync $sheetsSync,
        ?SheetsQueue $sheetsQueue = null,
        string $syncMode = 'inline',
        ?int $maxCandidates = null
    ) {
        $this->db = $db;
        $this->roomsRepo = $roomsRepo;
        $this->reservationsRepo = $reservationsRepo;
        $this->sheetsSync = $sheetsSync;
        $this->sheetsQueue = $sheetsQueue;
        $this->syncMode = $syncMode;
        $this->maxCandidates = $maxCandidates ? (int)$maxCandidates : null;
    }

    public function getRooms(): array
    {
        return $this->roomsRepo->getAll();
    }

    public function getReservations(string $date): array
    {
        $normalizedDate = Utils::normalizeDate($date);
        if (!$normalizedDate) {
            throw new ApiException('date parameter is required', 400);
        }
        $rows = $this->reservationsRepo->getByDate($normalizedDate);
        return array_map(function ($row) {
            return Utils::buildReservationObject($row);
        }, $rows);
    }

    public function getReservation(string $id): array
    {
        $row = $this->reservationsRepo->findById($id);
        if (!$row) {
            throw new ApiException('Reservation not found', 404);
        }
        return Utils::buildReservationObject($row);
    }

    public function createReservation(array $data): array
    {
        $includeReservations = !empty($data['includeReservations']);
        $normalized = $this->normalizeReservationInput($data);
        $this->validateReservationData($normalized, ['allowCandidates' => true]);

        $candidates = $this->buildCandidates($normalized);
        if (!$candidates) {
            throw new ApiException('部屋を選択してください', 400);
        }

        $reservation = $this->db->withLock('reservation_lock', 10, function () use ($normalized, $candidates, $includeReservations) {
            $confirmed = null;
            $conflicts = [];

            foreach ($candidates as $candidate) {
                $this->validateCandidate($candidate);
                $conflict = $this->reservationsRepo->findConflict(
                    (int)$candidate['roomId'],
                    $candidate['date'],
                    $candidate['startTime'],
                    (int)$candidate['durationMinutes']
                );
                if (!$conflict) {
                    $confirmed = $candidate;
                    break;
                }
                $conflicts[] = [
                    'candidate' => [
                        'roomId' => $candidate['roomId'],
                        'roomName' => $this->resolveRoomName($candidate['roomId'], $candidate['roomName'] ?? ''),
                        'date' => $candidate['date'],
                        'startTime' => $candidate['startTime'],
                        'durationMinutes' => (int)$candidate['durationMinutes'],
                    ],
                    'reason' => 'conflict',
                    'conflict' => Utils::buildReservationObject($conflict),
                ];
            }

            if (!$confirmed) {
                throw new ApiException(
                    'すべての候補が競合しています。別の時間帯をお試しください。',
                    409,
                    ['candidates' => $conflicts]
                );
            }

            $now = date('Y-m-d H:i:s');
            $reservationId = Utils::uuidv4();
            $roomName = $this->resolveRoomName($confirmed['roomId'], $confirmed['roomName'] ?? '');
            $visitorName = Utils::normalizeVisitorName($normalized['visitorName'] ?? '');

            $record = [
                'reservationId' => $reservationId,
                'roomId' => (int)$confirmed['roomId'],
                'roomName' => $roomName,
                'date' => $confirmed['date'],
                'startTime' => $confirmed['startTime'],
                'durationMinutes' => (int)$confirmed['durationMinutes'],
                'meetingName' => $normalized['meetingName'],
                'reserverName' => $normalized['reserverName'],
                'visitorName' => $visitorName,
                'createdAt' => $now,
                'updatedAt' => $now,
                'recurringEventId' => $normalized['recurringEventId'] ?? '',
                'recurrenceStartDate' => $normalized['recurrenceStartDate'] ?? '',
            ];

            $this->reservationsRepo->insert($record);
            $reservation = Utils::buildReservationObject($record);
            if ($includeReservations) {
                $reservation['reservations'] = $this->getReservations($confirmed['date']);
            }
            return $reservation;
        });

        $this->dispatchSheetsAction('appendReservation', ['reservation' => $reservation]);
        $this->dispatchSheetsAction('syncDateJson', ['date' => $reservation['date']]);

        return $reservation;
    }

    public function createReservations(array $payload): array
    {
        $items = $this->normalizeBatchRequest($payload);
        $created = [];
        $errors = [];
        foreach ($items as $index => $item) {
            try {
                $reservation = $this->createReservation($item);
                $created[] = [
                    'index' => $index,
                    'reservation' => $reservation,
                ];
            } catch (ApiException $e) {
                $errors[] = [
                    'index' => $index,
                    'error' => $e->getMessage(),
                    'status' => $e->status ?? 400,
                ];
            }
        }
        return [
            'total' => count($items),
            'created' => $created,
            'errors' => $errors,
        ];
    }

    public function createRecurringReservations(array $data, array $recurrence): array
    {
        $normalized = $this->normalizeReservationInput($data);
        $this->validateReservationData($normalized, ['allowCandidates' => true]);

        $frequency = strtolower((string)($recurrence['frequency'] ?? ''));
        $untilDate = $recurrence['untilDate'] ?? null;
        if (!$frequency) {
            throw new ApiException('frequency is required', 400);
        }
        if (!$untilDate) {
            throw new ApiException('untilDate is required', 400);
        }

        $startDate = Utils::normalizeDate($normalized['date'] ?? null);
        $until = Utils::normalizeDate($untilDate);
        if (!$startDate) {
            throw new ApiException('date is invalid', 400);
        }
        if (!$until) {
            throw new ApiException('untilDate is invalid', 400);
        }

        $dates = $this->calculateRecurringDates($startDate, $frequency, $until);
        $recurringEventId = Utils::uuidv4();
        $recurrenceStartDate = $startDate;
        $created = [];
        $errors = [];

        foreach ($dates as $dateStr) {
            try {
                $payload = array_merge($data, [
                    'date' => $dateStr,
                    'recurringEventId' => $recurringEventId,
                    'recurrenceStartDate' => $recurrenceStartDate,
                ]);
                $created[] = $this->createReservation($payload);
            } catch (ApiException $e) {
                $errors[] = [
                    'date' => $dateStr,
                    'error' => $e->getMessage(),
                    'status' => $e->status ?? 400,
                ];
            }
        }

        return [
            'total' => count($dates),
            'created' => $created,
            'errors' => $errors,
            'recurringEventId' => $recurringEventId,
        ];
    }

    public function updateReservation(string $reservationId, array $data): array
    {
        $includeReservations = !empty($data['includeReservations']);
        $normalized = $this->normalizeReservationInput($data);
        $this->validateReservationData($normalized, ['allowCandidates' => false]);

        $existing = $this->reservationsRepo->findById($reservationId);
        if (!$existing) {
            throw new ApiException('Reservation not found', 404);
        }

        $conflict = $this->reservationsRepo->findConflict(
            (int)$normalized['roomId'],
            $normalized['date'],
            $normalized['startTime'],
            (int)$normalized['durationMinutes'],
            $reservationId
        );
        if ($conflict) {
            throw new ApiException('指定された時間帯は既に予約されています。', 409, [
                'conflict' => Utils::buildReservationObject($conflict),
            ]);
        }

        $now = date('Y-m-d H:i:s');
        $roomName = $this->resolveRoomName($normalized['roomId'], $normalized['roomName'] ?? '');
        $visitorName = Utils::normalizeVisitorName($normalized['visitorName'] ?? '');

        $record = [
            'roomId' => (int)$normalized['roomId'],
            'date' => $normalized['date'],
            'startTime' => $normalized['startTime'],
            'durationMinutes' => (int)$normalized['durationMinutes'],
            'meetingName' => $normalized['meetingName'],
            'reserverName' => $normalized['reserverName'],
            'visitorName' => $visitorName,
            'updatedAt' => $now,
            'roomName' => $roomName,
        ];

        $this->reservationsRepo->update($reservationId, $record);

        $reservation = Utils::buildReservationObject(array_merge($existing, $record, [
            'reservationId' => $reservationId,
            'roomName' => $roomName,
            'updatedAt' => $now,
        ]));

        if ($includeReservations) {
            $reservation['reservations'] = $this->getReservations($normalized['date']);
        }

        $this->dispatchSheetsAction('updateReservation', [
            'reservationId' => $reservationId,
            'reservation' => $reservation,
        ]);
        $this->dispatchSheetsAction('syncDateJson', ['date' => $normalized['date']]);
        if ($existing['date'] !== $normalized['date']) {
            $this->dispatchSheetsAction('syncDateJson', ['date' => $existing['date']]);
        }

        return $reservation;
    }

    public function deleteReservation(string $reservationId, array $options = []): array
    {
        $includeReservations = !empty($options['includeReservations']);
        $refreshDate = Utils::normalizeDate(
            $options['refreshDate'] ?? $options['targetDate'] ?? $options['date'] ?? $options['startDate'] ?? null
        );
        $deleteType = strtolower((string)($options['deleteType'] ?? 'single'));
        if (!in_array($deleteType, ['single', 'following', 'all'], true)) {
            throw new ApiException('deleteType is invalid', 400);
        }

        if ($deleteType === 'single') {
            $existing = $this->reservationsRepo->findById($reservationId);
            if (!$existing) {
                throw new ApiException('Reservation not found', 404);
            }
            $deleted = $this->reservationsRepo->deleteById($reservationId);
            if (!$deleted) {
                throw new ApiException('Reservation not found', 404);
            }

            $result = [
                'deleted' => true,
                'reservationId' => $reservationId,
                'deleteType' => 'single',
            ];
            if ($includeReservations) {
                $targetDate = $refreshDate ?: $existing['date'];
                $result['reservations'] = $this->getReservations($targetDate);
            }

            $this->dispatchSheetsAction('deleteReservation', ['reservationId' => $reservationId]);
            $this->dispatchSheetsAction('syncDateJson', ['date' => $existing['date']]);

            return $result;
        }

        $recurringEventId = (string)($options['recurringEventId'] ?? '');
        if (!$recurringEventId) {
            throw new ApiException('recurringEventId is required', 400);
        }

        $targetDate = null;
        if ($deleteType === 'following') {
            $targetDate = Utils::normalizeDate($options['targetDate'] ?? null);
            if (!$targetDate) {
                throw new ApiException('targetDate is required', 400);
            }
        }

        $deletedInfo = $this->reservationsRepo->deleteByRecurring($recurringEventId, $deleteType, $targetDate);
        $deletedIds = $deletedInfo['deletedIds'];
        $touchedDates = $deletedInfo['dates'];

        $result = [
            'deleted' => count($deletedIds) > 0,
            'deletedCount' => count($deletedIds),
            'deletedReservationIds' => $deletedIds,
            'deleteType' => $deleteType,
            'recurringEventId' => $recurringEventId,
        ];
        if ($includeReservations && $refreshDate) {
            $result['reservations'] = $this->getReservations($refreshDate);
        }

        foreach ($deletedIds as $id) {
            $this->dispatchSheetsAction('deleteReservation', ['reservationId' => $id]);
        }
        foreach ($touchedDates as $date) {
            $this->dispatchSheetsAction('syncDateJson', ['date' => $date]);
        }

        return $result;
    }

    private function normalizeReservationInput(array $data): array
    {
        $normalized = $data;
        if (!empty($normalized['resourceId']) && empty($normalized['roomId'])) {
            $normalized['roomId'] = $normalized['resourceId'];
        }
        if (array_key_exists('guestName', $normalized)
            && (!isset($normalized['visitorName']) || trim((string)$normalized['visitorName']) === '')
        ) {
            $normalized['visitorName'] = $normalized['guestName'];
        }
        if (array_key_exists('duration', $normalized) && !isset($normalized['durationMinutes'])) {
            $normalized['durationMinutes'] = $normalized['duration'];
        }
        if (!isset($normalized['meetingTitle']) && isset($normalized['meetingName'])) {
            $normalized['meetingTitle'] = $normalized['meetingName'];
        }
        if (!isset($normalized['meetingName']) && isset($normalized['meetingTitle'])) {
            $normalized['meetingName'] = $normalized['meetingTitle'];
        }

        $durationValue = $normalized['durationMinutes'] ?? null;
        $normalized['durationMinutes'] = ($durationValue === null || $durationValue === '')
            ? 60
            : (int)$durationValue;

        if (isset($normalized['date'])) {
            $normalized['date'] = Utils::normalizeDate($normalized['date']);
        }
        if (isset($normalized['startTime'])) {
            $normalized['startTime'] = Utils::normalizeTime($normalized['startTime']);
        }

        return $normalized;
    }

    private function normalizeBatchRequest(array $params): array
    {
        $common = $params['common'] ?? [];
        $items = $params['items'] ?? [];
        $normalizedItems = [];
        foreach ($items as $item) {
            $tuple = is_array($item) ? $item : [];
            $data = $common;
            $data['roomId'] = $tuple[0] ?? null;
            $data['date'] = $tuple[1] ?? null;
            $data['startTime'] = $tuple[2] ?? null;
            $data['durationMinutes'] = $tuple[3] ?? null;
            $meetingName = $tuple[4] ?? null;
            $visitorName = $tuple[5] ?? null;
            if ($meetingName !== null && $meetingName !== '') {
                $data['meetingName'] = $meetingName;
            }
            if ($visitorName !== null && $visitorName !== '') {
                $data['visitorName'] = $visitorName;
            }
            if (($meetingName === null || $meetingName === '') && array_key_exists('meetingName', $common)) {
                $data['meetingName'] = $common['meetingName'];
            }
            if (($visitorName === null || $visitorName === '') && array_key_exists('visitorName', $common)) {
                $data['visitorName'] = $common['visitorName'];
            }
            $normalizedItems[] = $this->normalizeReservationInput($data);
        }
        return $normalizedItems;
    }

    private function buildCandidates(array $data): array
    {
        if (!empty($data['candidates']) && is_array($data['candidates'])) {
            $items = array_map(function ($candidate) use ($data) {
                return $this->normalizeCandidate($candidate, $data);
            }, $data['candidates']);
            return $this->limitCandidates($items);
        }

        if (!empty($data['roomPreferences']) && is_array($data['roomPreferences'])) {
            $items = array_map(function ($pref) use ($data) {
                $roomId = is_array($pref) ? ($pref['roomId'] ?? $pref['resourceId'] ?? null) : $pref;
                return $this->normalizeCandidate(['roomId' => $roomId], $data);
            }, $data['roomPreferences']);
            return $this->limitCandidates($items);
        }

        if (!empty($data['roomId']) || !empty($data['resourceId'])) {
            return [$this->normalizeCandidate([
                'roomId' => $data['roomId'] ?? $data['resourceId'],
                'roomName' => $data['roomName'] ?? '',
            ], $data)];
        }

        return [];
    }

    private function limitCandidates(array $items): array
    {
        if (!$this->maxCandidates) {
            return $items;
        }
        return array_slice($items, 0, $this->maxCandidates);
    }

    private function normalizeCandidate(array $candidate, array $common): array
    {
        $normalized = $candidate;
        if (!empty($normalized['resourceId']) && empty($normalized['roomId'])) {
            $normalized['roomId'] = $normalized['resourceId'];
        }
        if (array_key_exists('duration', $normalized) && !isset($normalized['durationMinutes'])) {
            $normalized['durationMinutes'] = $normalized['duration'];
        }
        $normalized['date'] = $normalized['date'] ?? $common['date'] ?? null;
        $normalized['startTime'] = $normalized['startTime'] ?? $common['startTime'] ?? null;
        $durationValue = $normalized['durationMinutes'] ?? null;
        $normalized['durationMinutes'] = ($durationValue === null || $durationValue === '')
            ? ($common['durationMinutes'] ?? 60)
            : (int)$durationValue;
        $normalized['roomName'] = $normalized['roomName'] ?? $common['roomName'] ?? '';
        $normalized['date'] = Utils::normalizeDate($normalized['date']);
        $normalized['startTime'] = Utils::normalizeTime($normalized['startTime']);
        return $normalized;
    }

    private function validateReservationData(array $data, array $options): void
    {
        if (empty($data)) {
            throw new ApiException('予約データが不正です', 400);
        }
        $this->validateReservationCommon($data);
        $allowCandidates = $options['allowCandidates'] ?? true;
        if ($allowCandidates && !empty($data['candidates']) && is_array($data['candidates'])) {
            return;
        }
        if (empty($data['date'])) {
            throw new ApiException('日付は必須です', 400);
        }
        if (empty($data['startTime'])) {
            throw new ApiException('開始時間は必須です', 400);
        }
        if (empty($data['roomId']) && empty($data['roomPreferences'])) {
            throw new ApiException('部屋を選択してください', 400);
        }
        $this->validateTimeRules($data['startTime'], $data['durationMinutes']);
    }

    private function validateReservationCommon(array $data): void
    {
        if (empty($data['meetingName'])) {
            throw new ApiException('会議名は必須です', 400);
        }
        if (empty($data['reserverName'])) {
            throw new ApiException('予約者名は必須です', 400);
        }
    }

    private function validateCandidate(array $candidate): void
    {
        if (empty($candidate['roomId'])) {
            throw new ApiException('部屋を選択してください', 400);
        }
        if (empty($candidate['date'])) {
            throw new ApiException('日付は必須です', 400);
        }
        if (empty($candidate['startTime'])) {
            throw new ApiException('開始時間は必須です', 400);
        }
        $this->validateTimeRules($candidate['startTime'], $candidate['durationMinutes']);
    }

    private function validateTimeRules(string $startTime, int $durationMinutes): void
    {
        if (!preg_match('/^\d{2}:\d{2}$/', $startTime)) {
            throw new ApiException('startTimeは15分刻み（00/15/30/45）のみ許可されています', 400);
        }
        [$hours, $minutes] = array_map('intval', explode(':', $startTime));
        if ($hours < 0 || $hours > 23 || $minutes < 0 || $minutes > 59) {
            throw new ApiException('startTimeは15分刻み（00/15/30/45）のみ許可されています', 400);
        }
        if (!in_array($minutes, [0, 15, 30, 45], true)) {
            throw new ApiException('startTimeは15分刻み（00/15/30/45）のみ許可されています', 400);
        }
        if ($durationMinutes <= 0 || $durationMinutes > 1440) {
            throw new ApiException('durationMinutesは1-1440の整数で指定してください', 400);
        }
        if ((Utils::timeToMinutes($startTime) + $durationMinutes) > 1440) {
            throw new ApiException('durationMinutesは日跨ぎを許可しません', 400);
        }
    }

    private function resolveRoomName($roomId, string $fallback): string
    {
        $room = $this->roomsRepo->findById($roomId);
        if ($room && !empty($room['roomName'])) {
            return $room['roomName'];
        }
        return $fallback ?: '';
    }

    private function calculateRecurringDates(string $startDate, string $frequency, string $untilDate): array
    {
        $base = new DateTime($startDate);
        $limit = new DateTime($untilDate);
        if ($base > $limit) {
            return [];
        }
        $dates = [];
        $current = clone $base;
        while ($current <= $limit) {
            $dates[] = $current->format('Y-m-d');
            $current = $this->addByFrequency($current, $frequency);
        }
        return $dates;
    }

    private function addByFrequency(DateTime $date, string $frequency): DateTime
    {
        switch ($frequency) {
            case 'daily':
                return $this->addDays($date, 1);
            case 'weekly':
                return $this->addDays($date, 7);
            case 'biweekly':
                return $this->addDays($date, 14);
            case 'monthly':
                return $this->addMonths($date, 1);
            default:
                throw new ApiException('Unsupported frequency: ' . $frequency, 400);
        }
    }

    private function addDays(DateTime $date, int $days): DateTime
    {
        $clone = clone $date;
        $clone->modify('+' . $days . ' days');
        return $clone;
    }

    private function addMonths(DateTime $date, int $months): DateTime
    {
        $day = (int)$date->format('j');
        $clone = clone $date;
        $clone->setDate((int)$clone->format('Y'), (int)$clone->format('n'), 1);
        $clone->modify('+' . $months . ' months');
        $lastDay = (int)$clone->format('t');
        $clone->setDate((int)$clone->format('Y'), (int)$clone->format('n'), min($day, $lastDay));
        return $clone;
    }

    private function dispatchSheetsAction(string $action, array $payload): void
    {
        if ($this->syncMode === 'async') {
            if ($this->sheetsQueue) {
                $this->sheetsQueue->enqueue($action, $payload);
            }
            return;
        }

        try {
            switch ($action) {
                case 'appendReservation':
                    $this->sheetsSync->appendReservation($payload['reservation']);
                    break;
                case 'updateReservation':
                    $this->sheetsSync->updateReservation($payload['reservationId'], $payload['reservation']);
                    break;
                case 'deleteReservation':
                    $this->sheetsSync->deleteReservation($payload['reservationId']);
                    break;
                case 'syncDateJson':
                    $this->sheetsSync->syncDateJson($payload['date']);
                    break;
                case 'syncRooms':
                    $this->sheetsSync->syncRooms();
                    break;
                default:
                    break;
            }
        } catch (Exception $e) {
            error_log('[SheetsSync:inline] ' . $action . ' ' . $e->getMessage());
        }
    }
}
