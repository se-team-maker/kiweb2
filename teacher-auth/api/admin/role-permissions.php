<?php
/**
 * 管理者API - ロールの権限管理
 */

require_once __DIR__ . '/../../public/bootstrap.php';

use App\Auth\Session;
use App\Model\User;
use App\Config\Database;

header('Content-Type: application/json');

// 認証チェック
if (!Session::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => '認証が必要です']);
    exit;
}

$userId = Session::getUserId();
$user = User::findById($userId);

// 管理者権限チェック
if (!$user || !$user->hasPermission('manage_roles')) {
    http_response_code(403);
    echo json_encode(['error' => 'ロール管理権限が必要です']);
    exit;
}

$db = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];

// URLからrole_idとpermission_idを取得（クエリパラメータを優先）
$targetRoleId = null;
$targetPermissionId = null;

// クエリパラメータから取得
if (isset($_GET['role_id']) && !empty($_GET['role_id'])) {
    $targetRoleId = $_GET['role_id'];
}
if (isset($_GET['permission_id']) && !empty($_GET['permission_id'])) {
    $targetPermissionId = $_GET['permission_id'];
}

// 後方互換性のため、パスからも取得を試みる
if (!$targetRoleId || !$targetPermissionId) {
    $requestUri = $_SERVER['REQUEST_URI'];
    $pathParts = explode('/', parse_url($requestUri, PHP_URL_PATH));
    
    foreach ($pathParts as $i => $part) {
        if (!$targetRoleId && $part === 'roles' && isset($pathParts[$i + 1]) && is_numeric($pathParts[$i + 1])) {
            $targetRoleId = $pathParts[$i + 1];
        }
        if (!$targetPermissionId && $part === 'permissions' && isset($pathParts[$i + 1]) && is_numeric($pathParts[$i + 1])) {
            $targetPermissionId = $pathParts[$i + 1];
        }
    }
    
    // role-permissions.php/{permissionId} 形式のパスからpermissionIdを取得
    if (!$targetPermissionId) {
        foreach ($pathParts as $i => $part) {
            if (strpos($part, 'role-permissions.php') !== false && isset($pathParts[$i + 1]) && is_numeric($pathParts[$i + 1])) {
                $targetPermissionId = $pathParts[$i + 1];
                break;
            }
        }
    }
}

if (!$targetRoleId) {
    http_response_code(400);
    echo json_encode(['error' => 'ロールIDが必要です（role_idパラメータを指定してください）']);
    exit;
}

try {
    switch ($method) {
        case 'GET':
            // ロールの権限一覧取得
            $stmt = $db->prepare('
                SELECT p.id, p.name, p.description
                FROM permissions p
                JOIN role_permissions rp ON p.id = rp.permission_id
                WHERE rp.role_id = ?
            ');
            $stmt->execute([$targetRoleId]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'POST':
            // 権限を割り当て
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (empty($input['permission_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'permission_idが必要です']);
                exit;
            }

            $stmt = $db->prepare('
                INSERT INTO role_permissions (role_id, permission_id)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE role_id = role_id
            ');
            $stmt->execute([$targetRoleId, $input['permission_id']]);

            echo json_encode(['success' => true]);
            break;

        case 'DELETE':
            if (!$targetPermissionId) {
                http_response_code(400);
                echo json_encode(['error' => '権限IDが必要です']);
                exit;
            }

            $stmt = $db->prepare('DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?');
            $stmt->execute([$targetRoleId, $targetPermissionId]);

            echo json_encode(['success' => true]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => '許可されていないメソッドです']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'サーバーエラー: ' . $e->getMessage()]);
}
