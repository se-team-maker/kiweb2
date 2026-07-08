<?php

return [
    'db' => [
        'host' => 'localhost',
        'name' => 'meeting_rooms',
        'user' => 'db_user',
        'pass' => 'db_password',
        'charset' => 'utf8mb4',
    ],
    'sheets' => [
        'spreadsheet_id' => 'YOUR_SPREADSHEET_ID',
        'service_account_json' => __DIR__ . '/credentials/service-account.json',
        'impersonate_user' => '',
        'enable_sync' => true,
        'sync_mode' => 'inline',
        'queue_batch' => 50,
        'queue_retry_seconds' => 30,
        'queue_max_attempts' => 10,
        'queue_run_after_response' => true,
        'queue_run_seconds' => 0.5,
    ],
    'tte' => [
        'api_base_url' => 'https://integration-gateway-286380150747.asia-northeast1.run.app',
        'integration_key' => getenv('TTE_INTEGRATION_KEY') ?: '',
    ],
    'api' => [
        'timezone' => 'Asia/Tokyo',
        'debug' => false,
        'max_candidates' => null,
    ],
];
