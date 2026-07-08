<?php

class RoomsRepository
{
    private $pdo;
    private $roomOrder;

    private $defaultRooms = [
        ['roomId' => 591, 'roomName' => '5階執務室', 'capacity' => 10, 'equipment' => ''],
        ['roomId' => 592, 'roomName' => '5階応接A', 'capacity' => 6, 'equipment' => 'モニター'],
        ['roomId' => 294, 'roomName' => '2階応接B', 'capacity' => 6, 'equipment' => 'モニター'],
        ['roomId' => 593, 'roomName' => '5階OL面', 'capacity' => 4, 'equipment' => ''],
        ['roomId' => 291, 'roomName' => '面談A', 'capacity' => 4, 'equipment' => ''],
        ['roomId' => 292, 'roomName' => '面談B', 'capacity' => 4, 'equipment' => ''],
        ['roomId' => 293, 'roomName' => 'CSL', 'capacity' => 8, 'equipment' => 'プロジェクター'],
        ['roomId' => 301, 'roomName' => '3号館面3A', 'capacity' => 4, 'equipment' => ''],
        ['roomId' => 302, 'roomName' => '3号館面3B', 'capacity' => 4, 'equipment' => ''],
        ['roomId' => 601, 'roomName' => '六角5F', 'capacity' => 10, 'equipment' => 'プロジェクター'],
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->roomOrder = array_map(function ($room) {
            return (int) $room['roomId'];
        }, $this->defaultRooms);
    }

    public function ensureSeeded(): void
    {
        $count = (int) $this->pdo->query('SELECT COUNT(*) AS c FROM rooms')->fetch()['c'];
        if ($count > 0) {
            return;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO rooms (room_id, room_name, capacity, equipment) VALUES (:id, :name, :capacity, :equipment)'
        );
        foreach ($this->defaultRooms as $room) {
            $stmt->execute([
                ':id' => $room['roomId'],
                ':name' => $room['roomName'],
                ':capacity' => $room['capacity'],
                ':equipment' => $room['equipment'],
            ]);
        }
    }

    public function getAll(): array
    {
        $this->ensureSeeded();
        $rows = $this->fetchOrderedRooms();
        if (!$rows) {
            return $this->defaultRooms;
        }
        return array_map(function ($row) {
            return [
                'roomId' => (int) $row['room_id'],
                'roomName' => $row['room_name'],
                'capacity' => (int) $row['capacity'],
                'equipment' => $row['equipment'] ?? '',
            ];
        }, $rows);
    }

    public function findById($roomId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT room_id, room_name, capacity, equipment FROM rooms WHERE room_id = :id');
        $stmt->execute([':id' => $roomId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        return [
            'roomId' => (int) $row['room_id'],
            'roomName' => $row['room_name'],
            'capacity' => (int) $row['capacity'],
            'equipment' => $row['equipment'] ?? '',
        ];
    }

    private function fetchOrderedRooms(): array
    {
        if ($this->roomOrder && count($this->roomOrder) > 0) {
            $placeholders = implode(',', array_fill(0, count($this->roomOrder), '?'));
            $sql = "SELECT room_id, room_name, capacity, equipment
                    FROM rooms
                    ORDER BY FIELD(room_id, {$placeholders}), room_id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($this->roomOrder);
            return $stmt->fetchAll();
        }

        return $this->pdo
            ->query('SELECT room_id, room_name, capacity, equipment FROM rooms ORDER BY room_id')
            ->fetchAll();
    }
}
