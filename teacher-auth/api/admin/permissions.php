<?php
/**
 * 管理者API - 権限管理
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

// URLからpermission_idを取得（クエリパラメータまたはパス）
$targetPermissionId = null;
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $targetPermissionId = $_GET['id'];
} else {
    // 後方互換性のため、パスからもIDを取得を試みる
    $requestUri = $_SERVER['REQUEST_URI'];
    $pathParts = explode('/', parse_url($requestUri, PHP_URL_PATH));
    foreach ($pathParts as $i => $part) {
        if ($part === 'permissions' && isset($pathParts[$i + 1]) && is_numeric($pathParts[$i + 1])) {
            $targetPermissionId = $pathParts[$i + 1];
            break;
        }
    }
}

try {
    switch ($method) {
        case 'GET':
            if ($targetPermissionId) {
                // 単一権限取得
                $stmt = $db->prepare('SELECT id, name, description, created_at FROM permissions WHERE id = ?');
                $stmt->execute([$targetPermissionId]);
                $permission = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$permission) {
                    http_response_code(404);
                    echo json_encode(['error' => '権限が見つかりません']);
                    exit;
                }

                echo json_encode($permission);
            } else {
                // 権限一覧取得
                $stmt = $db->query('SELECT id, name, description, created_at FROM permissions ORDER BY name');
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            }
            break;

        case 'POST':
            // 新規権限作成
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (empty($input['name'])) {
                http_response_code(400);
                echo json_encode(['error' => '権限名は必須です']);
                exit;
            }

            $stmt = $db->prepare('INSERT INTO permissions (name, description) VALUES (?, ?)');
            $stmt->execute([$input['name'], $input['description'] ?? null]);
            $permissionId = $db->lastInsertId();

            echo json_encode(['success' => true, 'id' => $permissionId]);
            break;

        case 'PUT':
            if (!$targetPermissionId) {
                http_response_code(400);
                echo json_encode(['error' => '権限IDが必要です']);
                exit;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            
            $updates = [];
            $params = [];

            if (isset($input['name'])) {
                $updates[] = 'name = ?';
                $params[] = $input['name'];
            }
            if (isset($input['description'])) {
                $updates[] = 'description = ?';
                $params[] = $input['description'];
            }

            if (empty($updates)) {
                http_response_code(400);
                echo json_encode(['error' => '更新する項目がありません']);
                exit;
            }

            $params[] = $targetPermissionId;
            $sql = 'UPDATE permissions SET ' . implode(', ', $updates) . ' WHERE id = ?';
            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            echo json_encode(['success' => true]);
            break;

        case 'DELETE':
            if (!$targetPermissionId) {
                http_response_code(400);
                echo json_encode(['error' => '権限IDが必要です']);
                exit;
            }

            $stmt = $db->prepare('DELETE FROM permissions WHERE id = ?');
            $stmt->execute([$targetPermissionId]);

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
