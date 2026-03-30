<?php

require __DIR__ . '/../bootstrap.php';

$config = require __DIR__ . '/../config.php';
$sheetsConfig = $config['sheets'] ?? [];

$syncMode = $sheetsConfig['sync_mode'] ?? 'inline';
if ($syncMode !== 'async') {
    fwrite(STDOUT, "Sheets sync mode is not async.\n");
    exit(0);
}

$db = new Database($config['db']);
$pdo = $db->pdo();
$queueBatch = (int)($sheetsConfig['queue_batch'] ?? 50);
$queueRetry = (int)($sheetsConfig['queue_retry_seconds'] ?? 30);
$queueMaxAttempts = (int)($sheetsConfig['queue_max_attempts'] ?? 10);

$queue = new SheetsQueue($pdo, $queueRetry, $queueMaxAttempts);

if (empty($sheetsConfig['spreadsheet_id']) || empty($sheetsConfig['service_account_json'])) {
    fwrite(STDERR, "Sheets config is missing.\n");
    exit(1);
}

$client = new SheetsClient($sheetsConfig);
$roomsRepo = new RoomsRepository($pdo);
$reservationsRepo = new ReservationsRepository($pdo);
$sync = new SheetsSync($client, $sheetsConfig, $roomsRepo, $reservationsRepo);

$processor = new SheetsQueueProcessor($queue, $sync);
$processed = $processor->process($queueBatch);
if ($processed <= 0) {
    fwrite(STDOUT, "No queued items.\n");
    exit(0);
}
fwrite(STDOUT, "Processed " . $processed . " items.\n");
