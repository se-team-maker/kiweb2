<?php
/**
 * エントリーポイント - ログインページにリダイレクト
 */

require_once __DIR__ . '/bootstrap.php';

use App\Auth\Session;

// ログイン済みならダッシュボードへ
if (Session::isLoggedIn()) {
    header('Location: /dashboard.php');
    exit;
}

// 未ログインならログインページへ
header('Location: /login.php');
exit;
