<?php

class Utils
{
    public static function uuidv4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public static function normalizeDate($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }
        if (is_string($value)) {
            $trim = trim($value);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $trim)) {
                return $trim;
            }
            if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})/', $trim, $m)) {
                return sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
            }
        }
        try {
            $dt = new DateTime((string)$value);
            return $dt->format('Y-m-d');
        } catch (Exception $e) {
            return null;
        }
    }

    public static function normalizeTime($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i');
        }
        $time = trim((string)$value);
        if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $time)) {
            $parts = explode(':', $time);
            return sprintf('%02d:%02d', (int)$parts[0], (int)$parts[1]);
        }
        if (strpos($time, 'T') !== false) {
            try {
                $dt = new DateTime($time);
                return $dt->format('H:i');
            } catch (Exception $e) {
                return null;
            }
        }
        return null;
    }

    public static function timeToMinutes($value): int
    {
        $time = self::normalizeTime($value);
        if ($time === null) {
            return 0;
        }
        [$h, $m] = array_map('intval', explode(':', $time));
        return $h * 60 + $m;
    }

    public static function calculateEndTime(string $startTime, int $durationMinutes): string
    {
        $startMinutes = self::timeToMinutes($startTime);
        $end = $startMinutes + $durationMinutes;
        $hours = intdiv($end, 60) % 24;
        $minutes = $end % 60;
        return sprintf('%02d:%02d', $hours, $minutes);
    }

    public static function normalizeVisitorName($value): string
    {
        if ($value === null || $value === false || $value === true) {
            return '';
        }
        return (string)$value;
    }

    public static function buildReservationObject(array $data): array
    {
        $durationMinutes = (int)($data['durationMinutes'] ?? 0);
        $meetingTitle = $data['meetingTitle'] ?? ($data['meetingName'] ?? '');
        $meetingName = $data['meetingName'] ?? ($data['meetingTitle'] ?? '');
        $visitorName = self::normalizeVisitorName($data['visitorName'] ?? '');

        return [
            'reservationId' => $data['reservationId'] ?? null,
            'roomId' => $data['roomId'] ?? null,
            'roomName' => $data['roomName'] ?? '',
            'date' => $data['date'] ?? null,
            'startTime' => $data['startTime'] ?? null,
            'durationMinutes' => $durationMinutes,
            'duration' => $durationMinutes,
            'endTime' => self::calculateEndTime((string)($data['startTime'] ?? '00:00'), $durationMinutes),
            'meetingTitle' => $meetingTitle,
            'meetingName' => $meetingName,
            'reserverName' => $data['reserverName'] ?? '',
            'visitorName' => $visitorName,
            'guestName' => $visitorName,
            'hasVisitor' => $visitorName !== '',
            'recurringEventId' => $data['recurringEventId'] ?? '',
            'recurrenceStartDate' => $data['recurrenceStartDate'] ?? '',
            'createdAt' => $data['createdAt'] ?? null,
            'updatedAt' => $data['updatedAt'] ?? null,
        ];
    }
}
