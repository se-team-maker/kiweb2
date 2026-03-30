<?php
/**
 * 管理者API - スコープタイプ管理
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

try {
    switch ($method) {
        case 'GET':
            // スコープタイプ一覧取得
            $stmt = $db->query('SELECT id, name, display_name, description FROM scope_types ORDER BY name');
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'POST':
            // 新規スコープタイプ作成
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (empty($input['name']) || empty($input['display_name'])) {
                http_response_code(400);
                echo json_encode(['error' => 'nameとdisplay_nameは必須です']);
                exit;
            }

            $stmt = $db->prepare('INSERT INTO scope_types (name, display_name, description) VALUES (?, ?, ?)');
            $stmt->execute([
                $input['name'],
                $input['display_name'],
                $input['description'] ?? null
            ]);
            $typeId = $db->lastInsertId();

            echo json_encode(['success' => true, 'id' => $typeId]);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => '許可されていないメソッドです']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'サーバーエラー: ' . $e->getMessage()]);
}
