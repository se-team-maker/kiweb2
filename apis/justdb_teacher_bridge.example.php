<?php
/**
 * Example bridge for JUST.DB lookup.
 * Copy to justdb_teacher_bridge.php and replace the placeholder values.
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

define('JUSTDB_API_URL', 'https://example.just-db.com/sites/api/services/v1/tables/example/records/');
define('JUSTDB_API_KEY', getenv('JUSTDB_API_KEY') ?: 'REPLACE_WITH_JUSTDB_API_KEY');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $strName = trim($input['strName'] ?? '');
} else {
    $strName = trim($_GET['strName'] ?? '');
}

if ($strName === '') {
    http_response_code(400);
    echo json_encode(['error' => 'strName is required'], JSON_UNESCAPED_UNICODE);
    exit;
}

$url = JUSTDB_API_URL . '?' . http_build_query([
    '_field_1697693160' => $strName,
    'limit' => 1,
]);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . JUSTDB_API_KEY,
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    http_response_code(502);
    echo json_encode(['error' => 'JUST.DB request failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($httpCode !== 200) {
    http_response_code(502);
    echo json_encode(['error' => 'JUST.DB returned an error', 'status' => $httpCode], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode($response, true);

if (!is_array($data) || count($data) === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'Teacher not found'], JSON_UNESCAPED_UNICODE);
    exit;
}

$record = $data[0]['record'] ?? null;
if (!$record) {
    http_response_code(404);
    echo json_encode(['error' => 'Record payload missing'], JSON_UNESCAPED_UNICODE);
    exit;
}

$koshiNo = $record['field_1697693279'] ?? null;
if (is_array($koshiNo)) {
    $koshiNo = $koshiNo[3] ?? null;
}

$pin = $record['field_1768822189'] ?? null;
if (is_array($pin)) {
    $pin = $pin[0] ?? null;
}

echo json_encode([
    'koshiNo' => $koshiNo,
    'pin' => $pin,
], JSON_UNESCAPED_UNICODE);
