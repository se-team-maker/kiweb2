<?php
/**
 * ユーザー情報取得API（セッション確認用）
 * Reactポータルから呼び出してセッションを確認する
 */

require_once __DIR__ . '/../public/bootstrap.php';

use App\Auth\Session;
use App\Model\User;

// GETのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed', 405);
}

// セッション確認
if (!Session::isLoggedIn()) {
    jsonResponse([
        'success' => false,
        'error' => '認証が必要です',
        'error_code' => 'UNAUTHORIZED'
    ], 401);
}

$userId = Session::getUserId();
$user = User::findById($userId);

if (!$user) {
    // セッションは有効だがユーザーが存在しない（削除された等）
    Session::destroy();
    jsonResponse([
        'success' => false,
        'error' => 'ユーザーが見つかりません',
        'error_code' => 'USER_NOT_FOUND'
    ], 401);
}

if (!$user->isActive()) {
    Session::destroy();
    jsonResponse([
        'success' => false,
        'error' => 'アカウントが無効です',
        'error_code' => 'ACCOUNT_INACTIVE'
    ], 403);
}

// ユーザー情報を返す
jsonResponse([
    'success' => true,
    'user' => [
        'id' => $user->id,
        'email' => $user->email,
        'name' => $user->name,
        'status' => $user->status,
        'email_verified' => $user->isEmailVerified(),
        'roles' => $user->getRoles()
    ]
]);
