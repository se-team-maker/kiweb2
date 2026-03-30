<?php
/**
 * 管理者API - ロール管理
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

// URLからrole_idを取得（クエリパラメータまたはパス）
$targetRoleId = null;
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $targetRoleId = $_GET['id'];
} else {
    // 後方互換性のため、パスからもIDを取得を試みる
    $requestUri = $_SERVER['REQUEST_URI'];
    $pathParts = explode('/', parse_url($requestUri, PHP_URL_PATH));
    foreach ($pathParts as $i => $part) {
        if ($part === 'roles' && isset($pathParts[$i + 1]) && is_numeric($pathParts[$i + 1])) {
            $targetRoleId = $pathParts[$i + 1];
            break;
        }
    }
}

try {
    switch ($method) {
        case 'GET':
            if ($targetRoleId) {
                // 単一ロール取得
                $stmt = $db->prepare('SELECT id, name, description, created_at FROM roles WHERE id = ?');
                $stmt->execute([$targetRoleId]);
                $role = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$role) {
                    http_response_code(404);
                    echo json_encode(['error' => 'ロールが見つかりません']);
                    exit;
                }

                // 権限一覧を取得
                $stmt = $db->prepare('
                    SELECT p.id, p.name, p.description
                    FROM permissions p
                    JOIN role_permissions rp ON p.id = rp.permission_id
                    WHERE rp.role_id = ?
                ');
                $stmt->execute([$targetRoleId]);
                $role['permissions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // ユーザー数を取得
                $stmt = $db->prepare('SELECT COUNT(*) FROM user_roles WHERE role_id = ?');
                $stmt->execute([$targetRoleId]);
                $role['user_count'] = intval($stmt->fetchColumn());

                echo json_encode($role);
            } else {
                // ロール一覧取得
                $stmt = $db->query('
                    SELECT r.id, r.name, r.description, r.created_at,
                           (SELECT COUNT(*) FROM user_roles ur WHERE ur.role_id = r.id) as user_count
                    FROM roles r
                    ORDER BY r.name
                ');
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            }
            break;

        case 'POST':
            // 新規ロール作成
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (empty($input['name'])) {
                http_response_code(400);
                echo json_encode(['error' => 'ロール名は必須です']);
                exit;
            }

            $stmt = $db->prepare('INSERT INTO roles (name, description) VALUES (?, ?)');
            $stmt->execute([$input['name'], $input['description'] ?? null]);
            $roleId = $db->lastInsertId();

            echo json_encode(['success' => true, 'id' => $roleId]);
            break;

        case 'PUT':
            if (!$targetRoleId) {
                http_response_code(400);
                echo json_encode(['error' => 'ロールIDが必要です']);
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

            $params[] = $targetRoleId;
            $sql = 'UPDATE roles SET ' . implode(', ', $updates) . ' WHERE id = ?';
            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            echo json_encode(['success' => true]);
            break;

        case 'DELETE':
            if (!$targetRoleId) {
                http_response_code(400);
                echo json_encode(['error' => 'ロールIDが必要です']);
                exit;
            }

            // システムロールは削除不可
            $stmt = $db->prepare('SELECT name FROM roles WHERE id = ?');
            $stmt->execute([$targetRoleId]);
            $role = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($role && in_array($role['name'], ['admin', 'full_time_teacher', 'part_time_teacher', 'part_time_staff', 'teacher', 'student'])) {
                http_response_code(400);
                echo json_encode(['error' => 'システムロールは削除できません']);
                exit;
            }

            $stmt = $db->prepare('DELETE FROM roles WHERE id = ?');
            $stmt->execute([$targetRoleId]);

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
