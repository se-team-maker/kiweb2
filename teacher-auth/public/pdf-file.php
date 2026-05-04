<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use App\Auth\Session;
use App\Model\User;

function requirePdfFileUser(): User
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

    return $user;
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

requirePdfFileUser();

$fileName = (string) ($_GET['file'] ?? '');
$filePath = resolvePrivatePdfPath($fileName);

if ($filePath === null) {
    http_response_code(404);
    exit('PDF not found');
}

if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

$fileSize = filesize($filePath);
if ($fileSize === false) {
    http_response_code(500);
    exit('Failed to read PDF');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename*=UTF-8\'\'' . rawurlencode(basename($filePath)));
header('Content-Length: ' . $fileSize);
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

readfile($filePath);
exit;
