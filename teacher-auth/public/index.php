<?php
/**
 * エントリーポイント - ログインページにリダイレクト
 */

require_once __DIR__ . '/bootstrap.php';

use App\Auth\Session;

// ログイン済みならポータルへ
if (Session::isLoggedIn()) {
    header('Location: /kiweb/kiweb2.html');
    exit;
}

// name パラメータがあればアカウント作成へ
$nameParam = trim($_GET['name'] ?? '');
if ($nameParam !== '') {
    $query = 'name=' . urlencode($nameParam);
    header('Location: /kiweb/teacher-auth/public/signup.php?' . $query);
    exit;
}

// 未ログインならログインページへ
header('Location: /kiweb/teacher-auth/public/login.php');
exit;
