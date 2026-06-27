<?php
// 資料配信管理 POST処理 v1
// PDF登録、公開/非公開切替。
// PHP 7.4+ 互換を意識して、mixed / never / match / str_contains は使わない。

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function pdfAdminActionRedirect($type, $message)
{
    header('Location: pdf-admin.php?type=' . rawurlencode((string)$type) . '&message=' . rawurlencode((string)$message));
    exit;
}

function pdfAdminActionContains($haystack, $needle)
{
    return strpos((string)$haystack, (string)$needle) !== false;
}

function pdfAdminActionRequireUser()
{
    // POST処理も画面表示とは独立してログイン状態を確認する。
    if (!\App\Auth\Session::isLoggedIn()) {
        http_response_code(401);
        exit('Unauthorized');
    }

    $userId = \App\Auth\Session::getUserId();
    $user = $userId ? \App\Model\User::findById($userId) : null;

    if (!$user || !method_exists($user, 'isActive') || !$user->isActive()) {
        \App\Auth\Session::destroy();
        http_response_code(401);
        exit('Unauthorized');
    }

    return array($user, (string)$userId);
}

function pdfAdminActionNormalizeRoleNames($rawRoles)
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

function pdfAdminActionGetUserRoleNames($user, $db, $userId)
{
    // 既存DBのロールカラム名差異に備え、複数の取得方法を順に試す。
    foreach (array('getRoles', 'getRoleNames') as $method) {
        if (method_exists($user, $method)) {
            $roles = pdfAdminActionNormalizeRoleNames($user->{$method}());
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
            // 既存DBのカラム名差異に備えて次の候補を試す
        }
    }

    return array();
}

function pdfAdminActionUserHasPermission($user, $permission)
{
    if (method_exists($user, 'hasPermission')) {
        return (bool)$user->hasPermission($permission);
    }
    return false;
}

function pdfAdminActionCanManage($user, $db, $userId)
{
    // 管理者ロールまたは管理権限を持つユーザーだけPDF管理POSTを許可する。
    $roles = array_map('strtolower', pdfAdminActionGetUserRoleNames($user, $db, $userId));

    return pdfAdminActionUserHasPermission($user, 'manage_users')
        || pdfAdminActionUserHasPermission($user, 'manage_pdf_documents')
        || in_array('admin', $roles, true)
        || in_array('administrator', $roles, true);
}

function pdfAdminActionRequireManager($user, $db, $userId)
{
    if (!pdfAdminActionCanManage($user, $db, $userId)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

function pdfAdminActionVerifyCsrf()
{
    // 管理画面からの正規POSTかを確認し、CSRFトークン不一致なら処理しない。
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    $posted = isset($_POST['csrf_token']) ? (string)$_POST['csrf_token'] : '';
    $stored = isset($_SESSION['pdf_admin_csrf_token']) ? (string)$_SESSION['pdf_admin_csrf_token'] : '';

    if ($posted === '' || $stored === '' || !hash_equals($stored, $posted)) {
        pdfAdminActionRedirect('error', '不正なリクエストです。画面を再読み込みしてから再度お試しください。');
    }
}

function pdfAdminActionPrivatePdfDir()
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'private-pdfs';
}

function pdfAdminActionEnsurePrivatePdfDir()
{
    // PDF保存先がなければ作成し、登録前に書き込み可能か確認する。
    $dir = pdfAdminActionPrivatePdfDir();

    if (!is_dir($dir)) {
        if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('PDF保存フォルダを作成できません。');
        }
    }

    if (!is_writable($dir)) {
        throw new RuntimeException('PDF保存フォルダに書き込みできません。');
    }

    return $dir;
}

function pdfAdminActionTruncate($value, $maxLength)
{
    $value = trim((string)$value);
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength, 'UTF-8');
    }
    return substr($value, 0, $maxLength);
}

function pdfAdminActionMakeStorageFileName($dir)
{
    // 元ファイル名は表示用に残し、保存名は推測しにくい一意名にする。
    for ($i = 0; $i < 10; $i++) {
        $name = 'pdf_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.pdf';
        if (!file_exists($dir . DIRECTORY_SEPARATOR . $name)) {
            return $name;
        }
    }

    throw new RuntimeException('保存ファイル名を生成できませんでした。');
}

function pdfAdminActionValidatePdfUpload($file)
{
    // 拡張子だけでなくPDFヘッダーも確認し、想定外ファイルの登録を防ぐ。
    if (!isset($file) || !is_array($file)) {
        throw new RuntimeException('PDFファイルを選択してください。');
    }

    $error = isset($file['error']) ? (int)$file['error'] : UPLOAD_ERR_NO_FILE;
    if ($error !== UPLOAD_ERR_OK) {
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new RuntimeException('PDFファイルのサイズが大きすぎます。');
        }
        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('PDFファイルを選択してください。');
        }
        throw new RuntimeException('PDFアップロードに失敗しました。error=' . $error);
    }

    $tmpPath = isset($file['tmp_name']) ? (string)$file['tmp_name'] : '';
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        throw new RuntimeException('アップロードされたファイルを確認できません。');
    }

    $size = isset($file['size']) ? (int)$file['size'] : 0;
    $maxSize = 50 * 1024 * 1024;
    if ($size < 1) {
        throw new RuntimeException('空のファイルは登録できません。');
    }
    if ($size > $maxSize) {
        throw new RuntimeException('PDFファイルは50MB以下にしてください。');
    }

    $originalName = isset($file['name']) ? basename((string)$file['name']) : '';
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($extension !== 'pdf') {
        throw new RuntimeException('PDFファイルのみ登録できます。');
    }

    $handle = fopen($tmpPath, 'rb');
    if (!$handle) {
        throw new RuntimeException('PDFファイルを読み取れません。');
    }
    $header = fread($handle, 5);
    fclose($handle);

    if ($header !== '%PDF-') {
        throw new RuntimeException('PDFとして認識できないファイルです。');
    }

    return array($tmpPath, $originalName);
}

function pdfAdminActionCreateDocument($db, $userId)
{
    // PDFファイルを保存してからDB登録する。DB登録に失敗した場合は保存済みファイルを削除する。
    $title = isset($_POST['title']) ? pdfAdminActionTruncate($_POST['title'], 255) : '';
    if ($title === '') {
        throw new RuntimeException('資料タイトルを入力してください。');
    }

    $targetScope = isset($_POST['target_scope']) ? (string)$_POST['target_scope'] : 'all';
    $allowedScopes = array('all', 'parttime', 'fulltime');
    if (!in_array($targetScope, $allowedScopes, true)) {
        throw new RuntimeException('配信対象が不正です。');
    }

    $requiresAck = isset($_POST['requires_ack']) && (string)$_POST['requires_ack'] === '1' ? 1 : 0;

    list($tmpPath, $originalName) = pdfAdminActionValidatePdfUpload(isset($_FILES['pdf_file']) ? $_FILES['pdf_file'] : null);

    $dir = pdfAdminActionEnsurePrivatePdfDir();
    $storageName = pdfAdminActionMakeStorageFileName($dir);
    $destPath = $dir . DIRECTORY_SEPARATOR . $storageName;

    if (!move_uploaded_file($tmpPath, $destPath)) {
        throw new RuntimeException('PDFファイルを保存できませんでした。');
    }

    try {
        $stmt = $db->prepare('
            INSERT INTO pdf_documents
                (title, file_name, original_name, target_scope, requires_ack, is_active, uploaded_by, uploaded_at)
            VALUES
                (?, ?, ?, ?, ?, 1, ?, NOW())
        ');
        $stmt->execute(array(
            $title,
            $storageName,
            pdfAdminActionTruncate($originalName, 255),
            $targetScope,
            $requiresAck,
            $userId
        ));
    } catch (\Throwable $e) {
        if (is_file($destPath)) {
            @unlink($destPath);
        }
        throw $e;
    }

    pdfAdminActionRedirect('success', '資料を登録しました。');
}

function pdfAdminActionToggleActive($db, $userId)
{
    // 物理削除はせず、is_active で公開・非公開だけを切り替える。
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if ($id < 1) {
        throw new RuntimeException('資料IDが不正です。');
    }

    $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;
    $isActive = $isActive === 1 ? 1 : 0;

    $stmt = $db->prepare('
        UPDATE pdf_documents
        SET is_active = ?, updated_by = ?, updated_at = NOW()
        WHERE id = ?
    ');
    $stmt->execute(array($isActive, $userId, $id));

    if ($stmt->rowCount() < 1) {
        throw new RuntimeException('対象の資料が見つからないか、状態が変更されませんでした。');
    }

    pdfAdminActionRedirect('success', $isActive === 1 ? '資料を公開しました。' : '資料を非公開にしました。');
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Method Not Allowed');
    }

    list($user, $userId) = pdfAdminActionRequireUser();
    $db = \App\Config\Database::getConnection();
    pdfAdminActionRequireManager($user, $db, $userId);
    pdfAdminActionVerifyCsrf();

    $action = isset($_POST['action']) ? (string)$_POST['action'] : '';

    // action の値で、登録処理と公開状態切替を振り分ける。
    if ($action === 'create') {
        pdfAdminActionCreateDocument($db, $userId);
    }

    if ($action === 'toggle_active') {
        pdfAdminActionToggleActive($db, $userId);
    }

    pdfAdminActionRedirect('error', '不明な操作です。');
} catch (\Throwable $e) {
    error_log('[pdf-admin-action] ' . $e->getMessage());
    pdfAdminActionRedirect('error', $e->getMessage());
}
