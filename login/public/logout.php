<?php
/**
 * ログアウト
 */

require_once __DIR__ . '/bootstrap.php';

use App\Auth\Session;
use App\Security\AuditLog;

$userId = Session::getUserId();

if ($userId) {
    AuditLog::log(AuditLog::LOGOUT, $userId);
}

Session::destroy();

header('Location: /login.php');
exit;
