<?php
/**
 * Admin API - user management
 */

require_once __DIR__ . '/../../public/bootstrap.php';

use App\Auth\Session;
use App\Config\Database;
use App\Model\User;
use App\Service\UserSpreadsheetMirror;

if (!Session::isLoggedIn()) {
    jsonResponse(['error' => 'ログインが必要です'], 401);
}

$userId = Session::getUserId();
$user = User::findById($userId);

if (!$user || !$user->hasPermission('manage_users')) {
    jsonResponse(['error' => 'ユーザー管理権限が必要です'], 403);
}

$db = Database::getConnection();
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

function resolveTargetUserId(): ?string
{
    $queryId = trim((string) ($_GET['id'] ?? ''));
    if ($queryId !== '') {
        return $queryId;
    }

    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $path = parse_url($requestUri, PHP_URL_PATH);
    $pathParts = explode('/', (string) $path);

    foreach ($pathParts as $index => $part) {
        if ($part === 'users' && !empty($pathParts[$index + 1])) {
            return $pathParts[$index + 1];
        }
    }

    return null;
}

function buildUserSearchWhereClause(string $search): array
{
    $search = trim($search);
    if ($search === '') {
        return ['', []];
    }

    return [
        'WHERE u.email LIKE ? OR u.name LIKE ?',
        ["%$search%", "%$search%"],
    ];
}

function fetchUserRoles(PDO $db, string $targetUserId): array
{
    $stmt = $db->prepare(
        'SELECT r.id, r.name, r.description
         FROM roles r
         JOIN user_roles ur ON r.id = ur.role_id
         WHERE ur.user_id = ?'
    );
    $stmt->execute([$targetUserId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchUserScopes(PDO $db, string $targetUserId): array
{
    $stmt = $db->prepare(
        'SELECT s.id, s.name, s.display_name, st.name AS type_name, st.display_name AS type_display_name
         FROM scopes s
         JOIN scope_types st ON s.scope_type_id = st.id
         JOIN user_scopes us ON s.id = us.scope_id
         WHERE us.user_id = ?'
    );
    $stmt->execute([$targetUserId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function fetchUsersForCsv(PDO $db, string $search): array
{
    [$whereClause, $params] = buildUserSearchWhereClause($search);

    $stmt = $db->prepare(
        "SELECT
            u.id,
            u.name,
            u.email,
            u.status,
            u.email_verified_at,
            COALESCE(
                GROUP_CONCAT(DISTINCT COALESCE(NULLIF(r.description, ''), r.name) ORDER BY r.id SEPARATOR ' / '),
                ''
            ) AS roles,
            COALESCE(
                GROUP_CONCAT(DISTINCT s.display_name ORDER BY st.id, s.id SEPARATOR ' / '),
                ''
            ) AS scopes,
            u.created_at,
            u.updated_at
        FROM users u
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        LEFT JOIN user_scopes us ON u.id = us.user_id
        LEFT JOIN scopes s ON us.scope_id = s.id
        LEFT JOIN scope_types st ON s.scope_type_id = st.id
        $whereClause
        GROUP BY
            u.id,
            u.name,
            u.email,
            u.status,
            u.email_verified_at,
            u.created_at,
            u.updated_at
        ORDER BY u.created_at DESC"
    );
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function exportUsersCsv(array $users): void
{
    $filename = 'account-users-' . date('Ymd-His') . '.csv';
    $statusLabels = [
        'active' => '有効',
        'locked' => 'ロック',
        'deleted' => '削除',
    ];

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

    // Excel on Windows detects UTF-8 CSV more reliably with BOM.
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');
    fputcsv($output, [
        '管理ID',
        '氏名',
        'メールアドレス',
        'ステータス',
        'メール認証済み',
        '役職',
        'スコープ',
        '登録日',
        '更新日',
    ]);

    foreach ($users as $user) {
        fputcsv($output, [
            $user['id'] ?? '',
            $user['name'] ?? '',
            $user['email'] ?? '',
            $statusLabels[$user['status'] ?? ''] ?? ($user['status'] ?? ''),
            empty($user['email_verified_at']) ? '未' : '済',
            $user['roles'] ?? '',
            $user['scopes'] ?? '',
            $user['created_at'] ?? '',
            $user['updated_at'] ?? '',
        ]);
    }

    fclose($output);
    exit;
}

$targetUserId = resolveTargetUserId();

try {
    switch ($method) {
        case 'GET':
            if ($targetUserId !== null) {
                $stmt = $db->prepare(
                    'SELECT u.id, u.email, u.name, u.status, u.email_verified_at, u.created_at, u.updated_at
                     FROM users u
                     WHERE u.id = ?'
                );
                $stmt->execute([$targetUserId]);
                $userData = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$userData) {
                    jsonResponse(['error' => 'ユーザーが見つかりません'], 404);
                }

                $userData['roles'] = fetchUserRoles($db, $targetUserId);
                $userData['scopes'] = fetchUserScopes($db, $targetUserId);

                jsonResponse($userData);
            }

            $search = (string) ($_GET['search'] ?? '');
            if (($_GET['export'] ?? '') === 'csv') {
                exportUsersCsv(fetchUsersForCsv($db, $search));
            }

            $page = max(1, (int) ($_GET['page'] ?? 1));
            $limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;

            [$whereClause, $params] = buildUserSearchWhereClause($search);

            $countStmt = $db->prepare("SELECT COUNT(*) FROM users u $whereClause");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $stmt = $db->prepare(
                "SELECT u.id, u.email, u.name, u.status, u.email_verified_at, u.created_at
                 FROM users u
                 $whereClause
                 ORDER BY u.created_at DESC
                 LIMIT $limit OFFSET $offset"
            );
            $stmt->execute($params);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($users as &$listUser) {
                $roleStmt = $db->prepare(
                    'SELECT r.name
                     FROM roles r
                     JOIN user_roles ur ON r.id = ur.role_id
                     WHERE ur.user_id = ?'
                );
                $roleStmt->execute([$listUser['id']]);
                $listUser['roles'] = $roleStmt->fetchAll(PDO::FETCH_COLUMN);
            }
            unset($listUser);

            jsonResponse([
                'users' => $users,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => (int) ceil($total / $limit),
            ]);
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            $name = isset($input['name']) ? trim((string) $input['name']) : null;
            if ($name === '') {
                $name = null;
            }

            if (empty($input['email'])) {
                jsonResponse(['error' => 'メールアドレスは必須です'], 400);
            }

            if ($name !== null && User::isNameTaken($name)) {
                jsonResponse(['error' => '同じ名前のユーザーはすでに存在します'], 400);
            }

            $newUser = User::create(
                (string) $input['email'],
                $input['password'] ?? null,
                $name
            );

            if (!$newUser) {
                jsonResponse(['error' => 'ユーザーの作成に失敗しました。メールアドレスが重複している可能性があります'], 400);
            }

            if (!empty($input['roles']) && is_array($input['roles'])) {
                foreach ($input['roles'] as $roleName) {
                    $newUser->assignRole((string) $roleName);
                }
            }

            UserSpreadsheetMirror::mirrorCreatedUser($newUser);

            jsonResponse(['success' => true, 'id' => $newUser->id]);
            break;

        case 'PUT':
            if ($targetUserId === null) {
                jsonResponse(['error' => 'ユーザーIDが必要です'], 400);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $name = isset($input['name']) ? trim((string) $input['name']) : null;
            if ($name === '') {
                $name = null;
            }

            $updates = [];
            $params = [];

            if (array_key_exists('name', $input)) {
                if ($name !== null && User::isNameTaken($name, $targetUserId)) {
                    jsonResponse(['error' => '同じ名前のユーザーがすでに存在します'], 400);
                }
                $updates[] = 'name = ?';
                $params[] = $name;
            }

            if (array_key_exists('email', $input)) {
                $updates[] = 'email = ?';
                $params[] = $input['email'];
            }

            if (array_key_exists('status', $input)) {
                $updates[] = 'status = ?';
                $params[] = $input['status'];
            }

            if ($updates === []) {
                jsonResponse(['error' => '更新する項目がありません'], 400);
            }

            $params[] = $targetUserId;
            $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = ?';
            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            jsonResponse(['success' => true]);
            break;

        case 'DELETE':
            if ($targetUserId === null) {
                jsonResponse(['error' => 'ユーザーIDが必要です'], 400);
            }

            if ($targetUserId === $userId) {
                jsonResponse(['error' => '自分自身のアカウントは削除できません'], 400);
            }

            $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
            $stmt->execute([$targetUserId]);

            jsonResponse(['success' => true]);
            break;

        default:
            jsonResponse(['error' => '許可されていないメソッドです'], 405);
    }
} catch (\Throwable $e) {
    jsonResponse(['error' => 'サーバーエラー: ' . $e->getMessage()], 500);
}
