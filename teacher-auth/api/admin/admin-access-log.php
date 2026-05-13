<?php
/**
 * 管理画面API - 管理画面アクセスログ記録
 */

require_once __DIR__ . '/../../public/bootstrap.php';

use App\Auth\Session;
use App\Model\User;
use App\Security\AccessLog;

header('Content-Type: application/json; charset=utf-8');

if (!Session::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => '認証が必要です'], JSON_UNESCAPED_UNICODE);
    exit;
}

$currentUserId = Session::getUserId();
$currentUser = $currentUserId ? User::findById($currentUserId) : null;
$canManageUsers = $currentUser && $currentUser->hasPermission('manage_users');
$canViewAuditLogs = $currentUser && $currentUser->hasPermission('view_audit_logs');

if (!$currentUser || (!$canManageUsers && !$canViewAuditLogs)) {
    http_response_code(403);
    echo json_encode(['error' => '管理画面アクセスログ記録権限が必要です'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => '許可されていないメソッドです'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$pagePath = trim((string)($data['page_path'] ?? ''));

if ($pagePath === '') {
    http_response_code(400);
    echo json_encode(['error' => 'page_path は必須です'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    AccessLog::logAdmin((string)$currentUserId, $pagePath, 'GET');

    echo json_encode([
        'success' => true
    ], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'サーバーエラー: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
