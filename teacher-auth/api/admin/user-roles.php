<?php
/**
 * 管理者API - ユーザーのロール管理
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
if (!$user || !$user->hasPermission('manage_users')) {
    http_response_code(403);
    echo json_encode(['error' => '管理者権限が必要です']);
    exit;
}

$db = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];

// URLからuser_idとrole_idを取得（クエリパラメータを優先）
$targetUserId = null;
$targetRoleId = null;

// クエリパラメータから取得
if (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
    $targetUserId = $_GET['user_id'];
}
if (isset($_GET['role_id']) && !empty($_GET['role_id'])) {
    $targetRoleId = $_GET['role_id'];
}

// 後方互換性のため、パスからも取得を試みる
if (!$targetUserId || !$targetRoleId) {
    $requestUri = $_SERVER['REQUEST_URI'];
    $pathParts = explode('/', parse_url($requestUri, PHP_URL_PATH));
    
    foreach ($pathParts as $i => $part) {
        if (!$targetUserId && $part === 'users' && isset($pathParts[$i + 1])) {
            $targetUserId = $pathParts[$i + 1];
        }
        if (!$targetRoleId && $part === 'roles' && isset($pathParts[$i + 1]) && is_numeric($pathParts[$i + 1])) {
            $targetRoleId = $pathParts[$i + 1];
        }
    }
    
    // user-roles.php/{roleId} 形式のパスからroleIdを取得
    if (!$targetRoleId) {
        foreach ($pathParts as $i => $part) {
            if (strpos($part, 'user-roles.php') !== false && isset($pathParts[$i + 1]) && is_numeric($pathParts[$i + 1])) {
                $targetRoleId = $pathParts[$i + 1];
                break;
            }
        }
    }
}

if (!$targetUserId) {
    http_response_code(400);
    echo json_encode(['error' => 'ユーザーIDが必要です（user_idパラメータを指定してください）']);
    exit;
}

if (in_array($method, ['POST', 'DELETE', 'PUT'], true) && $targetUserId === $userId) {
    http_response_code(403);
    echo json_encode(['error' => '自分自身の役職は変更できません']);
    exit;
}

try {
    switch ($method) {
        case 'GET':
            // ユーザーのロール一覧取得
            $stmt = $db->prepare('
                SELECT r.id, r.name, r.description
                FROM roles r
                JOIN user_roles ur ON r.id = ur.role_id
                WHERE ur.user_id = ?
            ');
            $stmt->execute([$targetUserId]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'POST':
            // ロールを割り当て
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (empty($input['role_id']) && empty($input['role_name'])) {
                http_response_code(400);
                echo json_encode(['error' => 'role_idまたはrole_nameが必要です']);
                exit;
            }

            $roleId = $input['role_id'] ?? null;
            
            if (!$roleId && !empty($input['role_name'])) {
                $stmt = $db->prepare('SELECT id FROM roles WHERE name = ?');
                $stmt->execute([$input['role_name']]);
                $role = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($role) {
                    $roleId = $role['id'];
                }
            }

            if (!$roleId) {
                http_response_code(400);
                echo json_encode(['error' => 'ロールが見つかりません']);
                exit;
            }

            $stmt = $db->prepare('
                INSERT INTO user_roles (user_id, role_id)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE user_id = user_id
            ');
            $stmt->execute([$targetUserId, $roleId]);

            echo json_encode(['success' => true]);
            break;

        case 'PUT':
            // 役職を置換（単一ロール運用）
            $input = json_decode(file_get_contents('php://input'), true);

            if (empty($input['role_id']) && empty($input['role_name'])) {
                http_response_code(400);
                echo json_encode(['error' => 'role_idまたはrole_nameが必要です']);
                exit;
            }

            $roleId = $input['role_id'] ?? null;

            if (!$roleId && !empty($input['role_name'])) {
                $stmt = $db->prepare('SELECT id FROM roles WHERE name = ?');
                $stmt->execute([$input['role_name']]);
                $role = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($role) {
                    $roleId = $role['id'];
                }
            }

            if (!$roleId) {
                http_response_code(400);
                echo json_encode(['error' => 'ロールが見つかりません']);
                exit;
            }

            try {
                $db->beginTransaction();

                $stmt = $db->prepare('DELETE FROM user_roles WHERE user_id = ?');
                $stmt->execute([$targetUserId]);

                $stmt = $db->prepare('
                    INSERT INTO user_roles (user_id, role_id)
                    VALUES (?, ?)
                ');
                $stmt->execute([$targetUserId, $roleId]);

                $db->commit();
            } catch (\Throwable $transactionError) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $transactionError;
            }

            echo json_encode(['success' => true]);
            break;

        case 'DELETE':
            if (!$targetRoleId) {
                http_response_code(400);
                echo json_encode(['error' => 'ロールIDが必要です']);
                exit;
            }

            $stmt = $db->prepare('DELETE FROM user_roles WHERE user_id = ? AND role_id = ?');
            $stmt->execute([$targetUserId, $targetRoleId]);

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
