<?php

$config = require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, OPTIONS');

function respondError(string $message, int $status, array $extra = []): void
{
    http_response_code($status);
    $payload = array_merge(['error' => $message], $extra);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respondError('Method not allowed', 405);
}

$from = trim((string)($_GET['from'] ?? ''));
$to = trim((string)($_GET['to'] ?? ''));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    respondError('from/to must be YYYY-MM-DD', 400);
}

$fromDate = DateTimeImmutable::createFromFormat('!Y-m-d', $from);
$toDate = DateTimeImmutable::createFromFormat('!Y-m-d', $to);
if (!$fromDate || !$toDate || $fromDate->format('Y-m-d') !== $from || $toDate->format('Y-m-d') !== $to) {
    respondError('Invalid date format', 400);
}

if ($fromDate > $toDate) {
    respondError('from must be less than or equal to to', 400);
}

$days = (int)$fromDate->diff($toDate)->days + 1;
if ($days > 5) {
    respondError('date range must be 5 days or less', 400);
}

$tteConfig = is_array($config['tte'] ?? null) ? $config['tte'] : [];
$baseUrl = trim((string)($tteConfig['api_base_url'] ?? ''));
if ($baseUrl === '') {
    $baseUrl = 'https://integration-gateway-286380150747.asia-northeast1.run.app';
}

$integrationKey = trim((string)($tteConfig['integration_key'] ?? ''));
if ($integrationKey === '') {
    $integrationKey = trim((string)(getenv('TTE_INTEGRATION_KEY') ?: ''));
}
if ($integrationKey === '') {
    respondError('TTE integration key is not configured', 500);
}

$url = rtrim($baseUrl, '/') . '/integrations/room-display/interviews?' . http_build_query([
    'from' => $from,
    'to' => $to,
], '', '&', PHP_QUERY_RFC3986);

if (!function_exists('curl_init')) {
    respondError('cURL extension is required', 500);
}

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'X-Integration-Key: ' . $integrationKey,
    ],
]);

$response = curl_exec($ch);
$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($response === false) {
    $error = curl_error($ch);
    curl_close($ch);
    respondError('Failed to call TTE API', 502, ['details' => $error]);
}

curl_close($ch);

if ($status <= 0) {
    $status = 502;
}

http_response_code($status);
if ($response === '' || $response === null) {
    echo json_encode(['error' => 'Empty response from TTE API'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo $response;
exit;

