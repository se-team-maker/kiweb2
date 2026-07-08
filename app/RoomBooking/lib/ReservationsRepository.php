<?php

class ReservationsRepository
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getByDate(string $date): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM reservations WHERE date = :date ORDER BY start_time');
        $stmt->execute([':date' => $date]);
        $rows = $stmt->fetchAll();
        return array_map([$this, 'formatRow'], $rows);
    }

    /**
     * 全予約を取得
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM reservations ORDER BY date, start_time');
        $rows = $stmt->fetchAll();
        return array_map([$this, 'formatRow'], $rows);
    }

    /**
     * 予約がある日付の一覧を取得
     */
    public function getDistinctDates(): array
    {
        $stmt = $this->pdo->query('SELECT DISTINCT date FROM reservations ORDER BY date');
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function findById(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM reservations WHERE reservation_id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ? $this->formatRow($row) : null;
    }

    public function insert(array $data): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO reservations (
                reservation_id, room_id, date, start_time, duration_minutes,
                meeting_name, reserver_name, visitor_name,
                created_at, updated_at, room_name, recurring_event_id, recurrence_start_date
            ) VALUES (
                :reservation_id, :room_id, :date, :start_time, :duration_minutes,
                :meeting_name, :reserver_name, :visitor_name,
                :created_at, :updated_at, :room_name, :recurring_event_id, :recurrence_start_date
            )'
        );
        $stmt->execute([
            ':reservation_id' => $data['reservationId'],
            ':room_id' => $data['roomId'],
            ':date' => $data['date'],
            ':start_time' => $data['startTime'] . ':00',
            ':duration_minutes' => $data['durationMinutes'],
            ':meeting_name' => $data['meetingName'],
            ':reserver_name' => $data['reserverName'],
            ':visitor_name' => $data['visitorName'],
            ':created_at' => $data['createdAt'],
            ':updated_at' => $data['updatedAt'],
            ':room_name' => $data['roomName'],
            ':recurring_event_id' => $data['recurringEventId'] ?? '',
            ':recurrence_start_date' => $data['recurrenceStartDate'] ?: null,
        ]);
    }

    public function update(string $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE reservations SET
                room_id = :room_id,
                date = :date,
                start_time = :start_time,
                duration_minutes = :duration_minutes,
                meeting_name = :meeting_name,
                reserver_name = :reserver_name,
                visitor_name = :visitor_name,
                updated_at = :updated_at,
                room_name = :room_name
             WHERE reservation_id = :reservation_id'
        );
        $stmt->execute([
            ':reservation_id' => $id,
            ':room_id' => $data['roomId'],
            ':date' => $data['date'],
            ':start_time' => $data['startTime'] . ':00',
            ':duration_minutes' => $data['durationMinutes'],
            ':meeting_name' => $data['meetingName'],
            ':reserver_name' => $data['reserverName'],
            ':visitor_name' => $data['visitorName'],
            ':updated_at' => $data['updatedAt'],
            ':room_name' => $data['roomName'],
        ]);
    }

    public function deleteById(string $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM reservations WHERE reservation_id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function deleteByRecurring(string $recurringEventId, string $deleteType, ?string $targetDate): array
    {
        $params = [':rid' => $recurringEventId];
        $where = 'recurring_event_id = :rid';

        if ($deleteType === 'following' && $targetDate) {
            $where .= ' AND date >= :targetDate';
            $params[':targetDate'] = $targetDate;
        }

        $select = $this->pdo->prepare("SELECT reservation_id, date FROM reservations WHERE {$where}");
        $select->execute($params);
        $rows = $select->fetchAll();

        $delete = $this->pdo->prepare("DELETE FROM reservations WHERE {$where}");
        $delete->execute($params);

        $deletedIds = [];
        $dates = [];
        foreach ($rows as $row) {
            $deletedIds[] = $row['reservation_id'];
            $dates[] = $row['date'];
        }

        return [
            'deletedIds' => $deletedIds,
            'dates' => array_values(array_unique($dates)),
        ];
    }

    public function findConflict(int $roomId, string $date, string $startTime, int $durationMinutes, ?string $excludeId = null): ?array
    {
        $newStartSeconds = Utils::timeToMinutes($startTime) * 60;
        $newEndSeconds = $newStartSeconds + ($durationMinutes * 60);
        $sql = 'SELECT * FROM reservations
                WHERE room_id = :room_id
                  AND date = :date';
        $params = [
            ':room_id' => $roomId,
            ':date' => $date,
            ':new_start' => $newStartSeconds,
            ':new_end' => $newEndSeconds,
        ];
        if ($excludeId) {
            $sql .= ' AND reservation_id <> :exclude_id';
            $params[':exclude_id'] = $excludeId;
        }
        $sql .= ' AND NOT (
                    (TIME_TO_SEC(start_time) + duration_minutes * 60) <= :new_start
                    OR TIME_TO_SEC(start_time) >= :new_end
                  )
                  LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ? $this->formatRow($row) : null;
    }

    private function formatRow(array $row): array
    {
        return [
            'reservationId' => $row['reservation_id'],
            'roomId' => (int)$row['room_id'],
            'roomName' => $row['room_name'] ?? '',
            'date' => $row['date'],
            'startTime' => substr($row['start_time'], 0, 5),
            'durationMinutes' => (int)$row['duration_minutes'],
            'meetingName' => $row['meeting_name'],
            'reserverName' => $row['reserver_name'],
            'visitorName' => $row['visitor_name'] ?? '',
            'createdAt' => $row['created_at'] ?? null,
            'updatedAt' => $row['updated_at'] ?? null,
            'recurringEventId' => $row['recurring_event_id'] ?? '',
            'recurrenceStartDate' => $row['recurrence_start_date'] ?? '',
        ];
    }
}
