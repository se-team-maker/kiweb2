<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Service\DeclarationScheduleCacheRefresher;
use Dotenv\Dotenv;

date_default_timezone_set('Asia/Tokyo');
Dotenv::createImmutable(__DIR__ . '/../../')->safeLoad();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondJson_([
        'success' => false,
        'error' => 'Method not allowed'
    ], 405);
}

$expectedToken = trim((string) ($_ENV['DECLARATION_SCHEDULE_REFRESH_TOKEN'] ?? ''));
if ($expectedToken === '') {
    respondJson_([
        'success' => false,
        'error' => 'Refresh token is not configured'
    ], 503);
}

$providedToken = extractBearerToken_();
if ($providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    respondJson_([
        'success' => false,
        'error' => 'Unauthorized'
    ], 401);
}

try {
    $result = DeclarationScheduleCacheRefresher::refresh();
    respondJson_([
        'success' => true,
        'record_count' => $result['record_count'],
        'refreshed_at' => $result['refreshed_at']
    ]);
} catch (Throwable $throwable) {
    error_log('refresh-declaration-schedule-cache.php failed: ' . $throwable->getMessage());
    respondJson_([
        'success' => false,
        'error' => $throwable->getMessage()
    ], 503);
}

function extractBearerToken_(): string
{
    $candidates = [];

    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $candidates[] = (string) $_SERVER['HTTP_AUTHORIZATION'];
    }
    if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $candidates[] = (string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
        if (is_array($headers)) {
            foreach ($headers as $name => $value) {
                if (strtolower((string) $name) === 'authorization') {
                    $candidates[] = (string) $value;
                }
            }
        }
    }

    foreach ($candidates as $headerValue) {
        if (preg_match('/^\s*Bearer\s+(.+)\s*$/i', $headerValue, $matches) === 1) {
            return trim($matches[1]);
        }
    }

    return '';
}

function respondJson_(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
