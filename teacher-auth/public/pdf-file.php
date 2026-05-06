<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use App\Auth\Session;
use App\Config\Database;
use App\Model\User;

function requirePdfUser(): array
{
    if (!Session::isLoggedIn()) {
        http_response_code(401);
        exit('Unauthorized');
    }

    $userId = Session::getUserId();
    $user = $userId ? User::findById($userId) : null;

    if (!$user || !$user->isActive()) {
        Session::destroy();
        http_response_code(401);
        exit('Unauthorized');
    }

    return [$user, (string) $userId];
}

function privatePdfDir(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'private-pdfs';
}

function isSafePdfFileName(string $fileName): bool
{
    return preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\.pdf\z/i', $fileName) === 1
        && strpos($fileName, '..') === false;
}

function resolvePrivatePdfPath(string $fileName): ?string
{
    if (!isSafePdfFileName($fileName)) {
        return null;
    }

    $baseDir = realpath(privatePdfDir());
    if ($baseDir === false) {
        return null;
    }

    $path = realpath($baseDir . DIRECTORY_SEPARATOR . $fileName);
    if ($path === false || !is_file($path)) {
        return null;
    }

    $basePrefix = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (strpos($path, $basePrefix) !== 0) {
        return null;
    }

    return $path;
}

function normalizeRoleNames(mixed $rawRoles): array
{
    if (!is_array($rawRoles)) {
        return [];
    }

    $roles = [];
    foreach ($rawRoles as $role) {
        if (is_string($role)) {
            $roles[] = $role;
            continue;
        }
        if (is_array($role)) {
            foreach (['name', 'role_name', 'code', 'slug', 'key'] as $key) {
                if (!empty($role[$key]) && is_string($role[$key])) {
                    $roles[] = $role[$key];
                    break;
                }
            }
        }
    }

    return array_values(array_unique(array_filter(array_map('strval', $roles))));
}

function getUserRoleNames(User $user, PDO $db, string $userId): array
{
    foreach (['getRoles', 'getRoleNames'] as $method) {
        if (method_exists($user, $method)) {
            $roles = normalizeRoleNames($user->{$method}());
            if ($roles !== []) {
                return $roles;
            }
        }
    }

    $queries = [
        'SELECT r.name FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?',
        'SELECT r.role_name FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?',
        'SELECT r.code FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?',
        'SELECT r.slug FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?',
        'SELECT ur.role FROM user_roles ur WHERE ur.user_id = ?',
    ];

    foreach ($queries as $sql) {
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute([$userId]);
            $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if ($roles) {
                return array_values(array_unique(array_map('strval', $roles)));
            }
        } catch (Throwable $e) {
            // 既存DBのカラム名差異に備えて次の候補を試す
        }
    }

    return [];
}

function userHasPermission(User $user, string $permission): bool
{
    if (method_exists($user, 'hasPermission')) {
        return (bool) $user->hasPermission($permission);
    }
    return false;
}

function getAllowedTargetScopes(User $user, PDO $db, string $userId): array
{
    $roles = array_map('strtolower', getUserRoleNames($user, $db, $userId));

    $isManager = userHasPermission($user, 'manage_users')
        || userHasPermission($user, 'manage_pdf_documents')
        || in_array('admin', $roles, true)
        || in_array('administrator', $roles, true);

    if ($isManager) {
        return ['all', 'parttime', 'fulltime'];
    }

    $scopes = ['all'];

    foreach ($roles as $role) {
        if (str_contains($role, 'part_time') || str_contains($role, 'parttime')) {
            $scopes[] = 'parttime';
        }
        if (str_contains($role, 'full_time') || str_contains($role, 'fulltime')) {
            $scopes[] = 'fulltime';
        }
    }

    return array_values(array_unique($scopes));
}

function getVisibleDocument(PDO $db, int $documentId, string $userId, array $allowedScopes): ?array
{
    if ($allowedScopes === []) {
        return null;
    }

    $placeholders = implode(',', array_fill(0, count($allowedScopes), '?'));
    $sql = "
        SELECT
            d.id,
            d.title,
            d.file_name,
            d.original_name,
            d.target_scope,
            d.requires_ack,
            d.is_active,
            d.uploaded_at,
            a.acknowledged_at
        FROM pdf_documents d
        LEFT JOIN pdf_acknowledgements a
            ON a.document_id = d.id
            AND a.user_id = ?
        WHERE d.id = ?
          AND d.is_active = 1
          AND d.target_scope IN ({$placeholders})
        LIMIT 1
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge([$userId, $documentId], $allowedScopes));
    $document = $stmt->fetch(PDO::FETCH_ASSOC);

    return $document ?: null;
}

[$user, $userId] = requirePdfUser();

$signage = isset($_GET['signage']) ? strtolower((string) $_GET['signage']) : '';

$signageFiles = [
    'sk' => 'sk.pdf',
    'em' => 'em.pdf',
];

if ($signage !== '') {
    if (!isset($signageFiles[$signage])) {
        http_response_code(404);
        exit('PDF not found');
    }

    $baseDir = realpath(privatePdfDir());
    if ($baseDir === false) {
        http_response_code(404);
        exit('PDF base dir not found');
    }

    $signageDir = realpath($baseDir . DIRECTORY_SEPARATOR . 'signage');
    if ($signageDir === false || !is_dir($signageDir)) {
        http_response_code(404);
        exit('Signage dir not found');
    }

    $filePath = realpath($signageDir . DIRECTORY_SEPARATOR . $signageFiles[$signage]);

    $signagePrefix = rtrim($signageDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (
        $filePath === false
        || !is_file($filePath)
        || strpos($filePath, $signagePrefix) !== 0
    ) {
        http_response_code(404);
        exit('PDF file not found');
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $fileSize = filesize($filePath);
    if ($fileSize === false) {
        http_response_code(500);
        exit('Failed to read PDF');
    }

    $downloadName = $signageFiles[$signage];

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename*=UTF-8\'\'' . rawurlencode($downloadName));
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: private, no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');

    readfile($filePath);
    exit;
}

$db = Database::getConnection();

$documentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$documentId || $documentId < 1) {
    http_response_code(404);
    exit('PDF not found');
}

$allowedScopes = getAllowedTargetScopes($user, $db, $userId);
$document = getVisibleDocument($db, $documentId, $userId, $allowedScopes);

if ($document === null) {
    http_response_code(404);
    exit('PDF not found');
}

$filePath = resolvePrivatePdfPath((string) $document['file_name']);
if ($filePath === null) {
    http_response_code(404);
    exit('PDF file not found');
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$fileSize = filesize($filePath);
if ($fileSize === false) {
    http_response_code(500);
    exit('Failed to read PDF');
}

$downloadName = (string) ($document['original_name'] ?: $document['file_name']);
if ($downloadName === '' || !str_ends_with(strtolower($downloadName), '.pdf')) {
    $downloadName = (string) $document['file_name'];
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename*=UTF-8\'\'' . rawurlencode($downloadName));
header('Content-Length: ' . $fileSize);
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

readfile($filePath);
exit;
