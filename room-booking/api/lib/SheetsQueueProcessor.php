<?php

class SheetsQueueProcessor
{
    private $queue;
    private $sync;

    public function __construct(SheetsQueue $queue, SheetsSync $sync)
    {
        $this->queue = $queue;
        $this->sync = $sync;
    }

    public function process(int $batchSize): int
    {
        $batchSize = max(1, $batchSize);
        $items = $this->queue->fetchBatch($batchSize);
        if (!$items) {
            return 0;
        }

        foreach ($items as $item) {
            $id = (int)$item['id'];
            $action = (string)$item['action'];
            $attempts = (int)$item['attempts'];
            $payload = json_decode($item['payload'] ?? '', true);
            if (!is_array($payload)) {
                $this->queue->markFailed($id, 'Invalid payload JSON', $attempts);
                continue;
            }

            try {
                $this->dispatch($action, $payload);
                $this->queue->markProcessed($id);
            } catch (Exception $e) {
                $this->queue->markFailed($id, $e->getMessage(), $attempts);
            }
        }

        return count($items);
    }

    public function processWithBudget(int $batchSize, float $maxSeconds): int
    {
        $start = microtime(true);
        $processed = 0;
        while ((microtime(true) - $start) < $maxSeconds) {
            $count = $this->process($batchSize);
            if ($count <= 0) {
                break;
            }
            $processed += $count;
            if ((microtime(true) - $start) >= $maxSeconds) {
                break;
            }
        }
        return $processed;
    }

    private function dispatch(string $action, array $payload): void
    {
        switch ($action) {
            case 'appendReservation':
                $this->sync->appendReservation($payload['reservation']);
                return;
            case 'updateReservation':
                $this->sync->updateReservation($payload['reservationId'], $payload['reservation']);
                return;
            case 'deleteReservation':
                $this->sync->deleteReservation($payload['reservationId']);
                return;
            case 'syncDateJson':
                $this->sync->syncDateJson($payload['date']);
                return;
            case 'syncRooms':
                $this->sync->syncRooms();
                return;
            default:
                throw new Exception('Unknown action: ' . $action);
        }
    }
}

