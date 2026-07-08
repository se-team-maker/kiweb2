<?php
/**
 * 通常ユーザーAPI - ポータル内iframeアクセスログ記録
 */

require_once __DIR__ . '/../bootstrap.php';

use App\Auth\Session;
use App\Security\AccessLog;

header('Content-Type: application/json; charset=utf-8');

if (!Session::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => '認証が必要です'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => '許可されていないメソッドです'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$pageKey = trim((string) ($data['page_key'] ?? ''));
$pageLabel = trim((string) ($data['page_label'] ?? ''));
$pagePath = trim((string) ($data['page_path'] ?? ''));

if ($pageKey === '' || $pagePath === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'page_key と page_path が必要です'], JSON_UNESCAPED_UNICODE);
    exit;
}

$userId = Session::getUserId();
if (!$userId) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => '認証が必要です'], JSON_UNESCAPED_UNICODE);
    exit;
}

AccessLog::logIframeOpen(
    $userId,
    $pageKey,
    $pageLabel,
    $pagePath
);

echo json_encode([
    'success' => true
], JSON_UNESCAPED_UNICODE);
exit;
