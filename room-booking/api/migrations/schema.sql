CREATE TABLE IF NOT EXISTS rooms (
  room_id INT PRIMARY KEY,
  room_name VARCHAR(100) NOT NULL,
  capacity INT NOT NULL,
  equipment VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reservations (
  reservation_id CHAR(36) PRIMARY KEY,
  room_id INT NOT NULL,
  date DATE NOT NULL,
  start_time TIME NOT NULL,
  duration_minutes INT NOT NULL,
  meeting_name VARCHAR(255) NOT NULL,
  reserver_name VARCHAR(255) NOT NULL,
  visitor_name VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  room_name VARCHAR(100) NOT NULL,
  recurring_event_id CHAR(36) NOT NULL DEFAULT '',
  recurrence_start_date DATE DEFAULT NULL,
  INDEX idx_room_date (room_id, date),
  INDEX idx_date (date),
  INDEX idx_recurring (recurring_event_id),
  CONSTRAINT fk_reservations_room
    FOREIGN KEY (room_id) REFERENCES rooms(room_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sheets_sync_queue (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  action VARCHAR(50) NOT NULL,
  payload LONGTEXT NOT NULL,
  attempts INT NOT NULL DEFAULT 0,
  last_error TEXT NULL,
  available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  locked_at DATETIME NULL,
  locked_by VARCHAR(64) NULL,
  INDEX idx_queue_available (processed_at, available_at),
  INDEX idx_queue_action (action),
  INDEX idx_queue_locked (locked_at),
  INDEX idx_queue_locked_by (locked_by, processed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO rooms (room_id, room_name, capacity, equipment) VALUES
  (591, '5階執務室', 10, ''),
  (592, '5階応接A', 6, 'モニター'),
  (294, '2階応接B', 6, 'モニター'),
  (593, '5階OL面', 4, ''),
  (291, '面談A', 4, ''),
  (292, '面談B', 4, ''),
  (293, 'CSL', 8, 'プロジェクター'),
  (301, '3号館面3A', 4, ''),
  (302, '3号館面3B', 4, ''),
  (601, '六角5F', 10, 'プロジェクター')
ON DUPLICATE KEY UPDATE
  room_name = VALUES(room_name),
  capacity = VALUES(capacity),
  equipment = VALUES(equipment);
