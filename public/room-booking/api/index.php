<?php

$config = require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    echo json_encode(['success' => true]);
    exit;
}

function jsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
}

function parseJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new ApiException('Invalid JSON', 400);
    }
    return $decoded ?: [];
}

try {
    $params = [];
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $params = $_GET;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $params = parseJsonBody();
    }

    $action = $params['action'] ?? null;
    if (!$action) {
        jsonResponse(['success' => false, 'error' => 'action parameter is required', 'status' => 400], 400);
    }

    if (isset($params['data']) && is_string($params['data'])) {
        $params['data'] = json_decode($params['data'], true);
    }
    if (isset($params['recurrence']) && is_string($params['recurrence'])) {
        $params['recurrence'] = json_decode($params['recurrence'], true);
    }

    $db = new Database($config['db']);
    $roomsRepo = new RoomsRepository($db->pdo());
    $reservationsRepo = new ReservationsRepository($db->pdo());
    $sheetsClient = null;
    $sheetsConfig = $config['sheets'] ?? [];
    if (!empty($sheetsConfig['enable_sync'])
        && !empty($sheetsConfig['spreadsheet_id'])
        && !empty($sheetsConfig['service_account_json'])
        && is_file($sheetsConfig['service_account_json'])
    ) {
        $sheetsClient = new SheetsClient($sheetsConfig);
    }
    $sheetsSync = new SheetsSync($sheetsClient, $sheetsConfig, $roomsRepo, $reservationsRepo);
    $syncMode = $sheetsConfig['sync_mode'] ?? 'inline';
    $queueBatch = (int)($sheetsConfig['queue_batch'] ?? 50);
    $queueRetry = (int)($sheetsConfig['queue_retry_seconds'] ?? 30);
    $queueMaxAttempts = (int)($sheetsConfig['queue_max_attempts'] ?? 10);
    $sheetsQueue = null;
    if ($syncMode === 'async') {
        $sheetsQueue = new SheetsQueue($db->pdo(), $queueRetry, $queueMaxAttempts);
    }
    $queueProcessor = null;
    if ($syncMode === 'async' && $sheetsQueue && $sheetsClient) {
        $queueProcessor = new SheetsQueueProcessor($sheetsQueue, $sheetsSync);
    }
    $service = new ReservationService(
        $db,
        $roomsRepo,
        $reservationsRepo,
        $sheetsSync,
        $sheetsQueue,
        $syncMode,
        $config['api']['max_candidates'] ?? null
    );

    // fastcgi_finish_request() がない環境では「レスポンス後に処理」ができないため、
    // 次のリクエスト（主にget系）でキューを少しずつ消化して追随させる。
    $runAfterResponse = !empty($sheetsConfig['queue_run_after_response']);
    $queueSeconds = (float)($sheetsConfig['queue_run_seconds'] ?? 0.5);
    $queueSeconds = max(0.05, min($queueSeconds, 2.0));
    $canFinishRequest = function_exists('fastcgi_finish_request');
    if ($queueProcessor && $syncMode === 'async' && $runAfterResponse && !$canFinishRequest) {
        if (in_array($action, ['getRooms', 'getReservations', 'getReservation'], true)
            || $action === 'processSheetsQueue') {
            try {
                $queueProcessor->processWithBudget(max(1, $queueBatch), min(0.2, $queueSeconds));
            } catch (Exception $e) {
                error_log('[SheetsQueue:processOnRequest] ' . $e->getMessage());
            }
        }
    }

    switch ($action) {
        case 'getRooms':
            $result = $service->getRooms();
            break;
        case 'getReservations':
            $date = $params['date'] ?? '';
            $result = $service->getReservations($date);
            break;
        case 'getReservation':
            $id = $params['id'] ?? '';
            if (!$id) {
                throw new ApiException('id parameter is required', 400);
            }
            $result = $service->getReservation($id);
            break;
        case 'createReservation':
            $data = $params['data'] ?? $params;
            $result = $service->createReservation($data);
            break;
        case 'createReservations':
            $data = $params['data'] ?? $params;
            $result = $service->createReservations($data);
            break;
        case 'createRecurringReservations':
            $data = $params['data'] ?? $params;
            $recurrence = $params['recurrence'] ?? [];
            $result = $service->createRecurringReservations($data, $recurrence);
            break;
        case 'updateReservation':
            $id = $params['id'] ?? '';
            if (!$id) {
                throw new ApiException('id parameter is required', 400);
            }
            $data = $params['data'] ?? $params;
            $result = $service->updateReservation($id, $data);
            break;
        case 'deleteReservation':
            $id = $params['id'] ?? '';
            if (!$id) {
                throw new ApiException('id parameter is required', 400);
            }
            $result = $service->deleteReservation($id, $params);
            break;
        case 'processSheetsQueue':
            if (!$queueProcessor) {
                $result = ['processed' => 0, 'enabled' => false];
                break;
            }
            $processed = 0;
            try {
                $processed = $queueProcessor->processWithBudget(max(1, $queueBatch), $queueSeconds);
            } catch (Exception $e) {
                error_log('[SheetsQueue:processSheetsQueue] ' . $e->getMessage());
            }
            $result = ['processed' => $processed, 'enabled' => true];
            break;
        case 'getSheetsSyncStatus':
            if (!$sheetsQueue) {
                $result = [
                    'enabled' => false,
                    'syncMode' => $syncMode,
                    'hasFastcgiFinish' => function_exists('fastcgi_finish_request'),
                ];
                break;
            }
            $result = [
                'enabled' => true,
                'syncMode' => $syncMode,
                'hasFastcgiFinish' => function_exists('fastcgi_finish_request'),
                'status' => $sheetsQueue->getStatus(10),
            ];
            break;
        case 'dedupeReservationsSheet':
            $result = $sheetsSync->dedupeReservationsSheet();
            break;
        case 'rebuildSheet':
            // DBからスプレッドシートを完全再構築
            if (!$sheetsSync->isEnabled()) {
                throw new ApiException('Sheets sync is not enabled', 400);
            }
            $result = $sheetsSync->rebuildAllSheets();
            break;
        default:
            throw new ApiException('Unknown action: ' . $action, 400);
    }

    jsonResponse(['success' => true, 'data' => $result]);

    if ($queueProcessor && $syncMode === 'async' && $runAfterResponse && $canFinishRequest) {
        fastcgi_finish_request();
        try {
            $queueProcessor->processWithBudget(max(1, $queueBatch), $queueSeconds);
        } catch (Exception $e) {
            error_log('[SheetsQueue:postResponse] ' . $e->getMessage());
        }
    }

    exit;
} catch (ApiException $e) {
    $payload = [
        'success' => false,
        'error' => $e->getMessage(),
        'status' => $e->status ?? 400,
    ];
    if ($e->details !== null) {
        $payload['details'] = $e->details;
    }
    jsonResponse($payload, $e->status ?? 400);
    exit;
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'error' => $e->getMessage(),
        'status' => 500,
    ], 500);
    exit;
}
