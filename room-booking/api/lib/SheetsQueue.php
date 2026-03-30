<?php

class SheetsQueue
{
    private $pdo;
    private $retrySeconds;
    private $maxAttempts;
    private $initialized = false;
    private $workerId;
    private $lockTtlSeconds = 600;

    public function __construct(PDO $pdo, int $retrySeconds = 30, int $maxAttempts = 10)
    {
        $this->pdo = $pdo;
        $this->retrySeconds = max(1, $retrySeconds);
        $this->maxAttempts = max(1, $maxAttempts);
        $host = function_exists('gethostname') ? (string)gethostname() : 'php';
        $pid = function_exists('getmypid') ? (int)getmypid() : 0;
        $rand = bin2hex(random_bytes(4));
        $this->workerId = substr($host . ':' . $pid . ':' . $rand, 0, 64);
    }

    public function enqueue(string $action, array $payload): void
    {
        try {
            $this->ensureTableExists();
            $stmt = $this->pdo->prepare(
                'INSERT INTO sheets_sync_queue (action, payload, available_at, created_at)
                 VALUES (:action, :payload, NOW(), NOW())'
            );
            $stmt->execute([
                ':action' => $action,
                ':payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (Exception $e) {
            error_log('[SheetsQueue:enqueue] ' . $e->getMessage());
        }
    }

    public function fetchBatch(int $limit): array
    {
        try {
            $this->ensureTableExists();
        } catch (Exception $e) {
            error_log('[SheetsQueue:fetchBatch] ' . $e->getMessage());
            return [];
        }
        $limit = max(1, (int)$limit);

        // 同時実行時に同じ行を複数プロセスが処理しないように「取得前にロック」する。
        // LOCKはTTLで自動回収できるようにする。
        $ttl = max(60, (int)$this->lockTtlSeconds);
        $claimSql = "
            UPDATE sheets_sync_queue
            SET locked_at = NOW(), locked_by = :worker
            WHERE processed_at IS NULL
              AND available_at <= NOW()
              AND (locked_at IS NULL OR locked_at < DATE_SUB(NOW(), INTERVAL {$ttl} SECOND))
            ORDER BY id ASC
            LIMIT :limit
        ";
        $claim = $this->pdo->prepare($claimSql);
        $claim->bindValue(':worker', $this->workerId);
        $claim->bindValue(':limit', $limit, PDO::PARAM_INT);
        $claim->execute();

        $stmt = $this->pdo->prepare(
            'SELECT id, action, payload, attempts
             FROM sheets_sync_queue
             WHERE processed_at IS NULL
               AND locked_by = :worker
             ORDER BY id ASC
             LIMIT :limit'
        );
        $stmt->bindValue(':worker', $this->workerId);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function markProcessed(int $id): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE sheets_sync_queue
                 SET processed_at = NOW(),
                     locked_at = NULL,
                     locked_by = NULL
                 WHERE id = :id'
            );
            $stmt->execute([':id' => $id]);
        } catch (Exception $e) {
            error_log('[SheetsQueue:markProcessed] ' . $e->getMessage());
        }
    }

    public function markFailed(int $id, string $error, int $attempts): void
    {
        $attempts = $attempts + 1;
        $delay = $this->retrySeconds * (int)pow(2, min($attempts - 1, 5));
        $delay = min($delay, 3600);

        if ($attempts >= $this->maxAttempts) {
            try {
                $stmt = $this->pdo->prepare(
                    'UPDATE sheets_sync_queue
                     SET attempts = :attempts,
                         last_error = :error,
                         processed_at = NOW(),
                         locked_at = NULL,
                         locked_by = NULL
                     WHERE id = :id'
                );
                $stmt->execute([
                    ':attempts' => $attempts,
                    ':error' => $error,
                    ':id' => $id,
                ]);
            } catch (Exception $e) {
                error_log('[SheetsQueue:markFailed] ' . $e->getMessage());
            }
            return;
        }

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE sheets_sync_queue
                 SET attempts = :attempts,
                     last_error = :error,
                     available_at = DATE_ADD(NOW(), INTERVAL :delay SECOND),
                     locked_at = NULL,
                     locked_by = NULL
                 WHERE id = :id'
            );
            $stmt->execute([
                ':attempts' => $attempts,
                ':error' => $error,
                ':delay' => $delay,
                ':id' => $id,
            ]);
        } catch (Exception $e) {
            error_log('[SheetsQueue:markFailed] ' . $e->getMessage());
        }
    }

    private function ensureTableExists(): void
    {
        if ($this->initialized) {
            return;
        }
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS sheets_sync_queue (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // 既存テーブルをアップグレード（列追加は失敗しても無視）
        $this->tryAlter('ALTER TABLE sheets_sync_queue ADD COLUMN locked_at DATETIME NULL');
        $this->tryAlter('ALTER TABLE sheets_sync_queue ADD COLUMN locked_by VARCHAR(64) NULL');
        $this->tryAlter('CREATE INDEX idx_queue_locked ON sheets_sync_queue (locked_at)');
        $this->tryAlter('CREATE INDEX idx_queue_locked_by ON sheets_sync_queue (locked_by, processed_at)');

        $this->initialized = true;
    }

    private function tryAlter(string $sql): void
    {
        try {
            $this->pdo->exec($sql);
        } catch (Exception $e) {
        }
    }

    public function getStatus(int $errorLimit = 10): array
    {
        try {
            $this->ensureTableExists();
            $pending = (int)$this->pdo->query(
                "SELECT COUNT(*) AS c FROM sheets_sync_queue WHERE processed_at IS NULL AND available_at <= NOW()"
            )->fetch()['c'];
            $delayed = (int)$this->pdo->query(
                "SELECT COUNT(*) AS c FROM sheets_sync_queue WHERE processed_at IS NULL AND available_at > NOW()"
            )->fetch()['c'];
            $failed = (int)$this->pdo->query(
                "SELECT COUNT(*) AS c FROM sheets_sync_queue WHERE processed_at IS NOT NULL AND last_error IS NOT NULL AND last_error <> ''"
            )->fetch()['c'];

            $stmt = $this->pdo->prepare(
                "SELECT id, action, attempts, last_error, created_at, processed_at
                 FROM sheets_sync_queue
                 WHERE last_error IS NOT NULL AND last_error <> ''
                 ORDER BY id DESC
                 LIMIT :limit"
            );
            $stmt->bindValue(':limit', max(1, $errorLimit), PDO::PARAM_INT);
            $stmt->execute();
            $errors = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'pending' => $pending,
                'delayed' => $delayed,
                'failed' => $failed,
                'recentErrors' => $errors,
            ];
        } catch (Exception $e) {
            return [
                'pending' => null,
                'delayed' => null,
                'failed' => null,
                'recentErrors' => [],
                'error' => $e->getMessage(),
            ];
        }
    }
}
