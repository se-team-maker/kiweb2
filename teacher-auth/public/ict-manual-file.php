<?php
// ICTマニュアル配信API
// ログイン済みユーザーにだけ、一覧JSON・Markdown・画像を返す。

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function ictManualRequireUser()
{
    if (!\App\Auth\Session::isLoggedIn()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('success' => false, 'error' => 'Unauthorized'), JSON_UNESCAPED_UNICODE);
        exit;
    }

    $userId = \App\Auth\Session::getUserId();
    $user = $userId ? \App\Model\User::findById($userId) : null;

    if (!$user || !method_exists($user, 'isActive') || !$user->isActive()) {
        \App\Auth\Session::destroy();
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('success' => false, 'error' => 'Unauthorized'), JSON_UNESCAPED_UNICODE);
        exit;
    }

    return array($user, (string)$userId);
}

function ictManualBaseDir()
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'ict-manual';
}

function ictManualJson($data, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function ictManualNormalizeRoleNames($rawRoles)
{
    if (!is_array($rawRoles)) {
        return array();
    }

    $roles = array();
    foreach ($rawRoles as $role) {
        if (is_string($role)) {
            $roles[] = $role;
            continue;
        }
        if (is_array($role)) {
            foreach (array('name', 'role_name', 'code', 'slug', 'key') as $key) {
                if (!empty($role[$key]) && is_string($role[$key])) {
                    $roles[] = $role[$key];
                    break;
                }
            }
        }
    }

    $roles = array_map('strval', $roles);
    $roles = array_filter($roles);
    return array_values(array_unique($roles));
}

function ictManualGetRoleNames($user, $userId)
{
    foreach (array('getRoles', 'getRoleNames') as $method) {
        if (method_exists($user, $method)) {
            $roles = ictManualNormalizeRoleNames($user->{$method}());
            if ($roles !== array()) {
                return $roles;
            }
        }
    }

    try {
        $db = \App\Config\Database::getConnection();
        $queries = array(
            'SELECT r.name FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?',
            'SELECT r.role_name FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?',
            'SELECT r.code FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?',
            'SELECT r.slug FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?',
            'SELECT ur.role FROM user_roles ur WHERE ur.user_id = ?'
        );

        foreach ($queries as $sql) {
            try {
                $stmt = $db->prepare($sql);
                $stmt->execute(array($userId));
                $roles = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                if ($roles) {
                    $roles = array_map('strval', $roles);
                    return array_values(array_unique($roles));
                }
            } catch (\Throwable $e) {
                // 既存DBのカラム名差異に備えて次の候補を試す
            }
        }
    } catch (\Throwable $e) {
        // DB未接続でも、Userモデルが返すロール情報で判定できる場合は続行する
    }

    return array();
}

function ictManualCanReadTarget($user, $userId, $targets)
{
    if (!is_array($targets) || $targets === array()) {
        return false;
    }

    if (in_array('all', $targets, true)) {
        return true;
    }

    $roles = array_map('strtolower', ictManualGetRoleNames($user, $userId));
    $isAdmin = in_array('admin', $roles, true)
        || in_array('administrator', $roles, true)
        || (method_exists($user, 'hasPermission') && $user->hasPermission('manage_users'));

    if ($isAdmin) {
        return true;
    }

    foreach ($targets as $target) {
        $target = strtolower((string)$target);
        if ($target === 'parttime') {
            foreach ($roles as $role) {
                if (strpos($role, 'part_time') !== false || strpos($role, 'parttime') !== false || $role === 'teacher') {
                    return true;
                }
            }
        }
        if ($target === 'fulltime') {
            foreach ($roles as $role) {
                if (strpos($role, 'full_time') !== false || strpos($role, 'fulltime') !== false) {
                    return true;
                }
            }
        }
    }

    return false;
}

function ictManualReadIndex()
{
    $path = ictManualBaseDir() . DIRECTORY_SEPARATOR . 'manuals' . DIRECTORY_SEPARATOR . 'index.json';
    if (!is_readable($path)) {
        ictManualJson(array('success' => false, 'error' => 'Manual index not found'), 404);
    }

    $json = file_get_contents($path);
    $items = json_decode((string)$json, true);
    if (!is_array($items)) {
        ictManualJson(array('success' => false, 'error' => 'Manual index is invalid'), 500);
    }

    return $items;
}

function ictManualVisibleItems($items, $user, $userId)
{
    $visible = array();
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        if (array_key_exists('visible', $item) && !$item['visible']) {
            continue;
        }
        $targets = isset($item['target']) && is_array($item['target']) ? $item['target'] : array();
        if (!ictManualCanReadTarget($user, $userId, $targets)) {
            continue;
        }
        $visible[] = $item;
    }

    usort($visible, function ($a, $b) {
        $ao = isset($a['order']) ? (int)$a['order'] : 9999;
        $bo = isset($b['order']) ? (int)$b['order'] : 9999;
        if ($ao === $bo) {
            return strcmp((string)($a['title'] ?? ''), (string)($b['title'] ?? ''));
        }
        return $ao < $bo ? -1 : 1;
    });

    return $visible;
}

function ictManualFindItem($items, $id)
{
    foreach ($items as $item) {
        if (is_array($item) && isset($item['id']) && (string)$item['id'] === $id) {
            return $item;
        }
    }
    return null;
}

function ictManualSafeManualFile($file)
{
    $file = (string)$file;
    if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\.md\z/', $file) !== 1 || strpos($file, '..') !== false) {
        return null;
    }

    $manualDir = realpath(ictManualBaseDir() . DIRECTORY_SEPARATOR . 'manuals');
    if ($manualDir === false) {
        return null;
    }

    $path = realpath($manualDir . DIRECTORY_SEPARATOR . $file);
    if ($path === false || !is_file($path)) {
        return null;
    }

    $prefix = rtrim($manualDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    return strpos($path, $prefix) === 0 ? $path : null;
}

function ictManualServeImage($path)
{
    $path = str_replace('\\', '/', (string)$path);
    if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._\/-]*\.(png|jpe?g|gif|webp|svg)\z/i', $path) !== 1 || strpos($path, '..') !== false) {
        http_response_code(404);
        exit('Not found');
    }

    $imageDir = realpath(ictManualBaseDir() . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'images');
    if ($imageDir === false) {
        http_response_code(404);
        exit('Not found');
    }

    $filePath = realpath($imageDir . DIRECTORY_SEPARATOR . $path);
    $prefix = rtrim($imageDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if ($filePath === false || !is_file($filePath) || strpos($filePath, $prefix) !== 0) {
        http_response_code(404);
        exit('Not found');
    }

    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $types = array(
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml'
    );

    header('Content-Type: ' . (isset($types[$ext]) ? $types[$ext] : 'application/octet-stream'));
    header('Cache-Control: private, max-age=300');
    header('X-Content-Type-Options: nosniff');
    readfile($filePath);
    exit;
}

list($user, $userId) = ictManualRequireUser();

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$type = isset($_GET['type']) ? (string)$_GET['type'] : 'index';

if ($type === 'image') {
    ictManualServeImage(isset($_GET['path']) ? (string)$_GET['path'] : '');
}

$items = ictManualReadIndex();

if ($type === 'index') {
    ictManualJson(array(
        'success' => true,
        'manuals' => ictManualVisibleItems($items, $user, $userId)
    ));
}

if ($type === 'markdown') {
    $id = isset($_GET['id']) ? (string)$_GET['id'] : '';
    if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9_-]*\z/', $id) !== 1) {
        ictManualJson(array('success' => false, 'error' => 'Manual not found'), 404);
    }

    $item = ictManualFindItem($items, $id);
    if ($item === null || (array_key_exists('visible', $item) && !$item['visible'])) {
        ictManualJson(array('success' => false, 'error' => 'Manual not found'), 404);
    }

    $targets = isset($item['target']) && is_array($item['target']) ? $item['target'] : array();
    if (!ictManualCanReadTarget($user, $userId, $targets)) {
        ictManualJson(array('success' => false, 'error' => 'Forbidden'), 403);
    }

    $path = ictManualSafeManualFile(isset($item['file']) ? $item['file'] : '');
    if ($path === null || !is_readable($path)) {
        ictManualJson(array('success' => false, 'error' => 'Manual not found'), 404);
    }

    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: private, no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    echo file_get_contents($path);
    exit;
}

ictManualJson(array('success' => false, 'error' => 'Unknown type'), 400);
