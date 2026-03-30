<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

// Keep API responses JSON-only even when php.ini has display_errors=On.
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    ts_json_response(['success' => true]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ts_error('Method not allowed', 405);
}

function ts_auth_via_bearer(string $authorizationHeader, array $authConfig): bool
{
    if (!preg_match('/^Bearer\s+(.+)$/i', $authorizationHeader, $matches)) {
        return false;
    }

    $token = trim((string)($matches[1] ?? ''));
    if ($token === '') {
        return false;
    }

    $staticToken = trim((string)($authConfig['bearer_token'] ?? ''));
    if ($staticToken !== '' && hash_equals($staticToken, $token)) {
        return true;
    }

    // Timestamp.SHA256(timestamp, secret) 形式も受け付ける（有効期限5分）
    if (!preg_match('/^(\d{10})\.([A-Fa-f0-9]{64})$/', $token, $parts)) {
        return false;
    }

    $timestamp = (int)$parts[1];
    $providedSignature = strtolower($parts[2]);
    $secret = (string)($authConfig['secret'] ?? '');
    $clockSkewSeconds = (int)($authConfig['clock_skew_seconds'] ?? 300);

    if ($secret === '' || strlen($secret) < 32) {
        return false;
    }
    if (abs(time() - $timestamp) > $clockSkewSeconds) {
        return false;
    }

    $expectedSignature = hash_hmac('sha256', (string)$timestamp, $secret);
    return hash_equals($expectedSignature, $providedSignature);
}

function ts_auth_via_hmac(array $authConfig): bool
{
    $timestamp = ts_get_header('X-Sync-Timestamp');
    $signature = ts_get_header('X-Sync-Signature');
    $secret = (string)($authConfig['secret'] ?? '');
    $clockSkewSeconds = (int)($authConfig['clock_skew_seconds'] ?? 300);

    if ($timestamp === '' || $signature === '' || $secret === '') {
        return false;
    }
    if (strlen($secret) < 32) {
        return false;
    }
    if (!preg_match('/^\d{10}$/', $timestamp)) {
        return false;
    }

    $timestampInt = (int)$timestamp;
    if (abs(time() - $timestampInt) > $clockSkewSeconds) {
        return false;
    }

    $expectedHex = hash_hmac('sha256', $timestamp, $secret);
    $expectedBase64 = base64_encode(hash_hmac('sha256', $timestamp, $secret, true));
    $provided = trim($signature);

    return hash_equals($expectedHex, strtolower($provided))
        || hash_equals($expectedBase64, $provided);
}

function ts_is_authenticated(array $authConfig): bool
{
    $authorizationHeader = ts_get_header('Authorization');
    if ($authorizationHeader !== '' && ts_auth_via_bearer($authorizationHeader, $authConfig)) {
        return true;
    }

    return ts_auth_via_hmac($authConfig);
}

function ts_parse_since(?string $since): ?string
{
    if ($since === null || trim($since) === '') {
        return null;
    }

    try {
        $date = new DateTimeImmutable($since);
    } catch (Throwable $error) {
        ts_error('Invalid since parameter. Use ISO8601 format.', 400);
    }

    return $date
        ->setTimezone(new DateTimeZone('Asia/Tokyo'))
        ->format('Y-m-d H:i:s');
}

function ts_use_teacher_only(?string $roleParam, array $authConfig): bool
{
    if ($roleParam !== null && trim($roleParam) !== '') {
        $normalized = strtolower(trim($roleParam));
        if ($normalized === 'teacher') {
            return true;
        }
        if ($normalized === 'all') {
            return false;
        }
        ts_error('Invalid role parameter. Use "teacher" or "all".', 400);
    }

    $defaultRoleFilter = strtolower(trim((string)($authConfig['default_role_filter'] ?? 'all')));
    return $defaultRoleFilter === 'teacher';
}

function ts_get_table_columns(PDO $pdo, string $table): array
{
    $statement = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');
    $columns = [];
    while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
        $field = strtolower((string)($row['Field'] ?? ''));
        if ($field !== '') {
            $columns[$field] = true;
        }
    }

    return $columns;
}

function ts_table_exists(PDO $pdo, string $table): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) AS cnt
         FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = :table_name'
    );
    $statement->bindValue(':table_name', $table);
    $statement->execute();
    $count = (int)$statement->fetchColumn();

    return $count > 0;
}

try {
    $config = ts_bootstrap();

    if (!ts_is_authenticated($config['auth'])) {
        ts_error('Unauthorized', 401);
    }

    $pdo = ts_pdo($config['db']);
    $userColumns = ts_get_table_columns($pdo, 'users');

    $since = ts_parse_since($_GET['since'] ?? null);
    $teacherOnly = ts_use_teacher_only($_GET['role'] ?? null, $config['auth']);

    $nameSelect = isset($userColumns['name'])
        ? "COALESCE(u.name, '') AS name"
        : "'' AS name";
    $statusSelect = isset($userColumns['status'])
        ? "COALESCE(u.status, 'active') AS status"
        : "'active' AS status";
    $timestampColumn = isset($userColumns['updated_at'])
        ? 'u.updated_at'
        : (isset($userColumns['created_at']) ? 'u.created_at' : null);
    $updatedAtSelect = $timestampColumn !== null
        ? "DATE_FORMAT($timestampColumn, '%Y-%m-%d %H:%i:%s') AS updated_at"
        : "'' AS updated_at";

    $hasRoles = ts_table_exists($pdo, 'user_roles') && ts_table_exists($pdo, 'roles');
    $hasScopes = ts_table_exists($pdo, 'user_scopes') && ts_table_exists($pdo, 'scopes') && ts_table_exists($pdo, 'scope_types');

    $rolesSelect = $hasRoles
        ? "GROUP_CONCAT(DISTINCT r.description ORDER BY r.name SEPARATOR ', ') AS roles"
        : "'' AS roles";
    $scopesSelect = $hasScopes
        ? "GROUP_CONCAT(DISTINCT s.display_name ORDER BY st.name, s.display_name SEPARATOR ', ') AS scopes"
        : "'' AS scopes";

    $sql = <<<SQL
SELECT
    u.id,
    u.email,
    $nameSelect,
    $statusSelect,
    $rolesSelect,
    $scopesSelect,
    $updatedAtSelect
FROM users u
SQL;

    if ($hasRoles) {
        $sql .= "\nLEFT JOIN user_roles ur ON ur.user_id = u.id";
        $sql .= "\nLEFT JOIN roles r ON r.id = ur.role_id";
    }
    if ($hasScopes) {
        $sql .= "\nLEFT JOIN user_scopes us ON us.user_id = u.id";
        $sql .= "\nLEFT JOIN scopes s ON s.id = us.scope_id";
        $sql .= "\nLEFT JOIN scope_types st ON st.id = s.scope_type_id";
    }

    $conditions = [];
    $params = [];

    if ($teacherOnly && $hasRoles) {
        $conditions[] = "u.id IN (SELECT ur2.user_id FROM user_roles ur2 JOIN roles r2 ON ur2.role_id = r2.id WHERE r2.name IN (:role_full_time, :role_part_time, :role_teacher_legacy))";
        $params[':role_full_time'] = 'full_time_teacher';
        $params[':role_part_time'] = 'part_time_teacher';
        $params[':role_teacher_legacy'] = 'teacher';
    }

    if ($since !== null && $timestampColumn !== null) {
        $conditions[] = $timestampColumn . ' >= :since';
        $params[':since'] = $since;
    }

    if ($conditions !== []) {
        $sql .= "\nWHERE " . implode(' AND ', $conditions);
    }

    $sql .= "\nGROUP BY u.id, u.email, u.name, u.status" . ($timestampColumn !== null ? ", $timestampColumn" : "");

    // ORDER BY は SELECT リスト内の列のみ（MySQL GROUP BY 互換）
    $sql .= $timestampColumn !== null
        ? "\nORDER BY updated_at ASC, u.id ASC"
        : "\nORDER BY u.id ASC";

    $statement = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $statement->bindValue($key, $value);
    }
    $statement->execute();

    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
    $data = array_map(static function (array $row): array {
        return [
            'id' => (string)$row['id'],
            'email' => (string)$row['email'],
            'name' => isset($row['name']) ? (string)$row['name'] : '',
            'status' => (string)$row['status'],
            'roles' => isset($row['roles']) ? (string)$row['roles'] : '',
            'scopes' => isset($row['scopes']) ? (string)$row['scopes'] : '',
            'updated_at' => (string)$row['updated_at'],
        ];
    }, $rows ?: []);

    ts_json_response([
        'success' => true,
        'data' => $data,
    ]);
} catch (PDOException $exception) {
    error_log('[teacher-sync] DB query failed: ' . $exception->getMessage());
    $payload = [
        'success' => false,
        'error' => 'Internal server error',
        'error_code' => 'DB_QUERY_FAILED',
    ];
    if (!empty($_ENV['TEACHER_SYNC_DEBUG'])) {
        $payload['debug'] = $exception->getMessage();
    }
    ts_json_response($payload, 500);
} catch (RuntimeException $exception) {
    error_log('[teacher-sync] Bootstrap failed: ' . $exception->getMessage());
    $errorCode = 'RUNTIME_FAILED';
    if (strpos($exception->getMessage(), 'DB_NAME is not configured') !== false) {
        $errorCode = 'CONFIG_DB_MISSING';
    } elseif (strpos($exception->getMessage(), 'Database connection failed') !== false) {
        $errorCode = 'DB_CONNECTION_FAILED';
    }
    ts_json_response([
        'success' => false,
        'error' => 'Internal server error',
        'error_code' => $errorCode,
    ], 500);
} catch (Throwable $exception) {
    error_log('[teacher-sync] Unexpected error: ' . $exception->getMessage());
    ts_json_response([
        'success' => false,
        'error' => 'Internal server error',
        'error_code' => 'UNEXPECTED_FAILED',
    ], 500);
}
