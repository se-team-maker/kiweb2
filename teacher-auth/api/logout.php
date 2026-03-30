<?php
/**
 * ログアウトAPI
 */

require_once __DIR__ . '/../public/bootstrap.php';

use App\Auth\Session;
use App\Security\AuditLog;

// ログイン状況の確認（任意）
$userId = Session::getUserId();

if ($userId) {
    AuditLog::log(AuditLog::LOGOUT, $userId, []);
}

// セッションを破棄
Session::destroy();

// GETでアクセスされた場合はリダイレクト
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Location: /kiweb/teacher-auth/public/login.php');
    exit;
}

// POSTの場合はJSONレスポンス
jsonResponse([
    'success' => true,
    'redirect' => '/kiweb/teacher-auth/public/login.php'
]);
