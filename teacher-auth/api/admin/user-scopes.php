<?php
/**
 * 管理者API - ユーザーのスコープ管理
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

// URLからuser_idとscope_idを取得（クエリパラメータを優先）
$targetUserId = null;
$targetScopeId = null;

// クエリパラメータから取得
if (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
    $targetUserId = $_GET['user_id'];
}
if (isset($_GET['scope_id']) && !empty($_GET['scope_id'])) {
    $targetScopeId = $_GET['scope_id'];
}

// 後方互換性のため、パスからも取得を試みる
if (!$targetUserId || !$targetScopeId) {
    $requestUri = $_SERVER['REQUEST_URI'];
    $pathParts = explode('/', parse_url($requestUri, PHP_URL_PATH));
    
    foreach ($pathParts as $i => $part) {
        if (!$targetUserId && $part === 'users' && isset($pathParts[$i + 1])) {
            $targetUserId = $pathParts[$i + 1];
        }
        if (!$targetScopeId && $part === 'scopes' && isset($pathParts[$i + 1]) && is_numeric($pathParts[$i + 1])) {
            $targetScopeId = $pathParts[$i + 1];
        }
    }
    
    // user-scopes.php/{scopeId} 形式のパスからscopeIdを取得
    if (!$targetScopeId) {
        foreach ($pathParts as $i => $part) {
            if (strpos($part, 'user-scopes.php') !== false && isset($pathParts[$i + 1]) && is_numeric($pathParts[$i + 1])) {
                $targetScopeId = $pathParts[$i + 1];
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

try {
    switch ($method) {
        case 'GET':
            // ユーザーのスコープ一覧取得
            $stmt = $db->prepare('
                SELECT s.id, s.name, s.display_name, st.name as type_name, st.display_name as type_display_name
                FROM scopes s
                JOIN scope_types st ON s.scope_type_id = st.id
                JOIN user_scopes us ON s.id = us.scope_id
                WHERE us.user_id = ?
            ');
            $stmt->execute([$targetUserId]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'POST':
            // スコープを割り当て
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (empty($input['scope_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'scope_idが必要です']);
                exit;
            }

            $stmt = $db->prepare('
                INSERT INTO user_scopes (user_id, scope_id)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE user_id = user_id
            ');
            $stmt->execute([$targetUserId, $input['scope_id']]);

            echo json_encode(['success' => true]);
            break;

        case 'DELETE':
            if (!$targetScopeId) {
                http_response_code(400);
                echo json_encode(['error' => 'スコープIDが必要です']);
                exit;
            }

            $stmt = $db->prepare('DELETE FROM user_scopes WHERE user_id = ? AND scope_id = ?');
            $stmt->execute([$targetUserId, $targetScopeId]);

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
