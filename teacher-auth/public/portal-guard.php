<?php
/**
 * ログインユーザーのロールに応じて、対応するポータルHTMLを返す入口。
 *
 * リダイレクトではなくreadfile()でHTMLを返すため、ブラウザ上のURLは
 * portal-guard.phpのままでも、内容は管理者・社員・通常ポータルへ切り替わる。
 */

require_once __DIR__ . '/bootstrap.php';

use App\Auth\Session;
use App\Model\User;
use App\Security\AccessLog;

$nameParam = trim($_GET['name'] ?? '');

// 未ログイン時は name パラメータに応じて遷移
if (!Session::isLoggedIn()) {
    if ($nameParam !== '') {
        $query = 'name=' . urlencode($nameParam);
        header('Location: /kiweb/teacher-auth/public/signup.php?' . $query);
        exit;
    }
    header('Location: /kiweb/teacher-auth/public/login.php');
    exit;
}

$userId = Session::getUserId();
$user = $userId ? User::findById($userId) : null;

if (!$user || !$user->isActive()) {
    Session::destroy();
    header('Location: /kiweb/teacher-auth/public/login.php');
    exit;
}

$portalFile = 'kiweb2.html';
$roles = $user->getRoles();

// 複数ロールを持つ場合の優先順位は「管理者 > 社員 > 通常」。
// 管理者機能を持つユーザーを社員ポータルへ誤って振り分けないため、この順序を維持する。
if (in_array('admin', $roles, true)) {
    $portalFile = 'kiweb2-admin.html';
} elseif (in_array('full_time_teacher', $roles, true)) {
    $portalFile = 'kiweb2-fulltime.html';
} elseif (in_array('part_time_teacher', $roles, true) || in_array('part_time_staff', $roles, true) || in_array('teacher', $roles, true) || in_array('student', $roles, true)) {
    $portalFile = 'kiweb2.html';
} elseif ($user->hasPermission('manage_users')) {
    // 旧データ等で admin ロール未設定でも管理ポータルへ
    $portalFile = 'kiweb2-admin.html';
}

$portalRoot = dirname(__DIR__, 2);
$portalPath = $portalRoot . DIRECTORY_SEPARATOR . $portalFile;
// 対象ポータルが欠けていてもログイン後に完全な404にしないよう、通常版へフォールバックする。
if (!is_readable($portalPath)) {
    $portalPath = $portalRoot . DIRECTORY_SEPARATOR . 'kiweb2.html';
}

if (!is_readable($portalPath)) {
    http_response_code(404);
    echo 'Not Found';
    exit;
}

// ログ記録失敗はポータル表示に影響させない
AccessLog::log(
    $user->id,
    $portalFile,
    $_SERVER['REQUEST_METHOD'] ?? 'GET'
);

header('Content-Type: text/html; charset=utf-8');
readfile($portalPath);
exit;
