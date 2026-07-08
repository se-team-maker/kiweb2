<?php

$localConfig = __DIR__ . '/config.local.php';
if (is_file($localConfig)) {
    return require $localConfig;
}

$sample = require __DIR__ . '/config.sample.php';
$sample['db']['host'] = getenv('MEETING_DB_HOST') ?: $sample['db']['host'];
$sample['db']['name'] = getenv('MEETING_DB_NAME') ?: $sample['db']['name'];
$sample['db']['user'] = getenv('MEETING_DB_USER') ?: $sample['db']['user'];
$sample['db']['pass'] = getenv('MEETING_DB_PASS') ?: $sample['db']['pass'];
$sample['db']['charset'] = getenv('MEETING_DB_CHARSET') ?: $sample['db']['charset'];

$sample['sheets']['spreadsheet_id'] = getenv('MEETING_SHEETS_ID') ?: $sample['sheets']['spreadsheet_id'];
$sample['sheets']['service_account_json'] = getenv('MEETING_SA_JSON') ?: $sample['sheets']['service_account_json'];
$sample['sheets']['impersonate_user'] = getenv('MEETING_SA_IMPERSONATE') ?: $sample['sheets']['impersonate_user'];
$sample['sheets']['enable_sync'] = getenv('MEETING_SHEETS_SYNC') !== false
    ? filter_var(getenv('MEETING_SHEETS_SYNC'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $sample['sheets']['enable_sync']
    : $sample['sheets']['enable_sync'];
$sample['sheets']['sync_mode'] = getenv('MEETING_SHEETS_SYNC_MODE') ?: $sample['sheets']['sync_mode'];
$sample['sheets']['queue_batch'] = getenv('MEETING_SHEETS_QUEUE_BATCH') ?: $sample['sheets']['queue_batch'];
$sample['sheets']['queue_retry_seconds'] = getenv('MEETING_SHEETS_QUEUE_RETRY') ?: $sample['sheets']['queue_retry_seconds'];
$sample['sheets']['queue_max_attempts'] = getenv('MEETING_SHEETS_QUEUE_MAX_ATTEMPTS') ?: $sample['sheets']['queue_max_attempts'];
$sample['sheets']['queue_run_after_response'] = filter_var(getenv('MEETING_SHEETS_QUEUE_AFTER_RESPONSE'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $sample['sheets']['queue_run_after_response'];
$sample['sheets']['queue_run_seconds'] = getenv('MEETING_SHEETS_QUEUE_SECONDS') ?: $sample['sheets']['queue_run_seconds'];

if (!isset($sample['tte']) || !is_array($sample['tte'])) {
    $sample['tte'] = [
        'api_base_url' => 'https://integration-gateway-286380150747.asia-northeast1.run.app',
        'integration_key' => '',
    ];
}
$sample['tte']['api_base_url'] = getenv('TTE_API_BASE_URL') ?: ($sample['tte']['api_base_url'] ?? 'https://integration-gateway-286380150747.asia-northeast1.run.app');
$sample['tte']['integration_key'] = getenv('TTE_INTEGRATION_KEY') ?: ($sample['tte']['integration_key'] ?? '');

$sample['api']['timezone'] = getenv('MEETING_TZ') ?: $sample['api']['timezone'];
$sample['api']['debug'] = filter_var(getenv('MEETING_DEBUG'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $sample['api']['debug'];
$sample['api']['max_candidates'] = getenv('MEETING_MAX_CANDIDATES') ?: $sample['api']['max_candidates'];

return $sample;
