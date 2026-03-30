<?php
/**
 * 管理者API - スコープ管理
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

// URLからscope_idを取得（クエリパラメータまたはパス）
$targetScopeId = null;
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $targetScopeId = $_GET['id'];
} else {
    // 後方互換性のため、パスからもIDを取得を試みる
    $requestUri = $_SERVER['REQUEST_URI'];
    $pathParts = explode('/', parse_url($requestUri, PHP_URL_PATH));
    foreach ($pathParts as $i => $part) {
        if ($part === 'scopes' && isset($pathParts[$i + 1]) && is_numeric($pathParts[$i + 1])) {
            $targetScopeId = $pathParts[$i + 1];
            break;
        }
    }
}

try {
    switch ($method) {
        case 'GET':
            if ($targetScopeId) {
                // 単一スコープ取得
                $stmt = $db->prepare('
                    SELECT s.id, s.scope_type_id, s.name, s.display_name, s.description, st.name as type_name, st.display_name as type_display_name
                    FROM scopes s
                    JOIN scope_types st ON s.scope_type_id = st.id
                    WHERE s.id = ?
                ');
                $stmt->execute([$targetScopeId]);
                $scope = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$scope) {
                    http_response_code(404);
                    echo json_encode(['error' => 'スコープが見つかりません']);
                    exit;
                }

                echo json_encode($scope);
            } else {
                // スコープ一覧取得（タイプでフィルタ可能）
                $typeFilter = $_GET['type'] ?? null;

                $sql = '
                    SELECT s.id, s.scope_type_id, s.name, s.display_name, s.description, st.name as type_name, st.display_name as type_display_name
                    FROM scopes s
                    JOIN scope_types st ON s.scope_type_id = st.id
                ';
                $params = [];

                if ($typeFilter) {
                    $sql .= ' WHERE st.name = ?';
                    $params[] = $typeFilter;
                }

                $sql .= ' ORDER BY st.name, s.display_name';

                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            }
            break;

        case 'POST':
            // 新規スコープ作成
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (empty($input['name']) || empty($input['display_name']) || empty($input['scope_type_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'name, display_name, scope_type_idは必須です']);
                exit;
            }

            $stmt = $db->prepare('INSERT INTO scopes (scope_type_id, name, display_name, description) VALUES (?, ?, ?, ?)');
            $stmt->execute([
                $input['scope_type_id'],
                $input['name'],
                $input['display_name'],
                $input['description'] ?? null
            ]);
            $scopeId = $db->lastInsertId();

            echo json_encode(['success' => true, 'id' => $scopeId]);
            break;

        case 'PUT':
            if (!$targetScopeId) {
                http_response_code(400);
                echo json_encode(['error' => 'スコープIDが必要です']);
                exit;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            
            $updates = [];
            $params = [];

            if (isset($input['name'])) {
                $updates[] = 'name = ?';
                $params[] = $input['name'];
            }
            if (isset($input['display_name'])) {
                $updates[] = 'display_name = ?';
                $params[] = $input['display_name'];
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

            $params[] = $targetScopeId;
            $sql = 'UPDATE scopes SET ' . implode(', ', $updates) . ' WHERE id = ?';
            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            echo json_encode(['success' => true]);
            break;

        case 'DELETE':
            if (!$targetScopeId) {
                http_response_code(400);
                echo json_encode(['error' => 'スコープIDが必要です']);
                exit;
            }

            $stmt = $db->prepare('DELETE FROM scopes WHERE id = ?');
            $stmt->execute([$targetScopeId]);

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
