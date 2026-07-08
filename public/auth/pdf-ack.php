<?php
// 「確かに見ました」ボタンの確認済み記録API。
// ビューアからPOSTされた資料IDを、ログインユーザー・配信対象・確認要否を確認してから保存する。
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use App\Auth\Session;
use App\Config\Database;
use App\Model\User;

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

function pdfAckSendJson($payload, $status = 200)
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

register_shutdown_function(function () {
    // ビューア側が原因を把握できるよう、Fatal Error でもJSON形式のレスポンスを返す。
    $error = error_get_last();
    if (!$error) {
        return;
    }

    $fatalTypes = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR);
    if (!in_array($error['type'], $fatalTypes, true)) {
        return;
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
    }

    echo json_encode(array(
        'success' => false,
        'message' => 'PHP Fatal Error: ' . $error['message'],
        'file' => $error['file'],
        'line' => $error['line'],
    ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
});

function pdfAckContains($haystack, $needle)
{
    return strpos((string)$haystack, (string)$needle) !== false;
}

function pdfAckRequireUser()
{
    // 確認済み記録はログイン済みの有効ユーザーIDに紐づける。
    if (!\App\Auth\Session::isLoggedIn()) {
        pdfAckSendJson(array('success' => false, 'message' => 'Unauthorized'), 401);
    }

    $userId = \App\Auth\Session::getUserId();
    $user = $userId ? \App\Model\User::findById($userId) : null;

    if (!$user || !method_exists($user, 'isActive') || !$user->isActive()) {
        \App\Auth\Session::destroy();
        pdfAckSendJson(array('success' => false, 'message' => 'Unauthorized'), 401);
    }

    return array($user, (string)$userId);
}

function pdfAckNormalizeRoleNames($rawRoles)
{
    // User::getRoles() の戻り値差異を吸収して、ロール名の配列に揃える。
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

function pdfAckGetUserRoleNames($user, $db, $userId)
{
    // 既存DBのロールカラム名差異に備え、複数の取得方法を順に試す。
    foreach (array('getRoles', 'getRoleNames') as $method) {
        if (method_exists($user, $method)) {
            $roles = pdfAckNormalizeRoleNames($user->{$method}());
            if ($roles !== array()) {
                return $roles;
            }
        }
    }

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
            // DB構成差異に備えて次の候補を試す
        }
    }

    return array();
}

function pdfAckUserHasPermission($user, $permission)
{
    if (method_exists($user, 'hasPermission')) {
        return (bool)$user->hasPermission($permission);
    }
    return false;
}

function pdfAckGetAllowedTargetScopes($user, $db, $userId)
{
    // 確認記録APIでも対象スコープを確認し、対象外資料へのPOSTを拒否する。
    $roles = array_map('strtolower', pdfAckGetUserRoleNames($user, $db, $userId));

    $isManager = pdfAckUserHasPermission($user, 'manage_users')
        || pdfAckUserHasPermission($user, 'manage_pdf_documents')
        || in_array('admin', $roles, true)
        || in_array('administrator', $roles, true);

    if ($isManager) {
        return array('all', 'parttime', 'fulltime');
    }

    $scopes = array('all');

    foreach ($roles as $role) {
        if (pdfAckContains($role, 'part_time') || pdfAckContains($role, 'parttime')) {
            $scopes[] = 'parttime';
        }
        if (pdfAckContains($role, 'full_time') || pdfAckContains($role, 'fulltime')) {
            $scopes[] = 'fulltime';
        }
    }

    return array_values(array_unique($scopes));
}

function pdfAckGetVisibleDocument($db, $documentId, $allowedScopes)
{
    // 保存前に、資料が公開中かつログインユーザーの配信対象内かを確認する。
    if ($allowedScopes === array()) {
        return null;
    }

    $placeholders = implode(',', array_fill(0, count($allowedScopes), '?'));
    $sql = "
        SELECT id, title, target_scope, requires_ack, is_active
        FROM pdf_documents
        WHERE id = ?
          AND is_active = 1
          AND target_scope IN ({$placeholders})
        LIMIT 1
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge(array($documentId), $allowedScopes));
    $document = $stmt->fetch(\PDO::FETCH_ASSOC);

    return $document ? $document : null;
}

function pdfAckTruncateString($value, $maxLength)
{
    if ($value === null) {
        return null;
    }
    $value = (string)$value;
    if (strlen($value) <= $maxLength) {
        return $value;
    }
    return substr($value, 0, $maxLength);
}

try {
    require_once __DIR__ . '/bootstrap.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        pdfAckSendJson(array('success' => false, 'message' => 'Method not allowed'), 405);
    }

    list($user, $userId) = pdfAckRequireUser();
    $db = \App\Config\Database::getConnection();

    $documentId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($documentId < 1) {
        pdfAckSendJson(array('success' => false, 'message' => 'Invalid document id'), 400);
    }

    $allowedScopes = pdfAckGetAllowedTargetScopes($user, $db, $userId);
    $document = pdfAckGetVisibleDocument($db, $documentId, $allowedScopes);

    if ($document === null) {
        pdfAckSendJson(array('success' => false, 'message' => 'PDF not found'), 404);
    }

    if ((int)$document['requires_ack'] !== 1) {
        // 確認不要の資料はエラーにせず、保存対象外であることだけ返す。
        pdfAckSendJson(array(
            'success' => true,
            'message' => 'この資料は確認記録が不要です。',
            'requires_ack' => false
        ));
    }

    $stmt = $db->prepare('
        INSERT INTO pdf_acknowledgements
            (document_id, user_id, acknowledged_at, ip_address, user_agent)
        VALUES
            (?, ?, NOW(), ?, ?)
        ON DUPLICATE KEY UPDATE
            acknowledged_at = VALUES(acknowledged_at),
            ip_address = VALUES(ip_address),
            user_agent = VALUES(user_agent)
    ');
    // document_id + user_id のユニーク制約を使い、再クリック時は確認日時を更新する。

    $ok = $stmt->execute(array(
        $documentId,
        $userId,
        pdfAckTruncateString(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null, 45),
        pdfAckTruncateString(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null, 65535)
    ));

    if (!$ok) {
        $info = $stmt->errorInfo();
        pdfAckSendJson(array(
            'success' => false,
            'message' => 'DB insert failed: ' . implode(' / ', $info)
        ), 500);
    }

    pdfAckSendJson(array(
        'success' => true,
        'message' => '確認記録を保存しました。',
        'document_id' => $documentId
    ));

} catch (\Throwable $e) {
    error_log('[pdf-ack] ' . $e->getMessage());

    pdfAckSendJson(array(
        'success' => false,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ), 500);
}
