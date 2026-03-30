<?php

require_once __DIR__ . '/../public/bootstrap.php';

use App\Auth\Session;
use App\Model\User;

const CLASS_REPORT_SEARCH_GAS_URL = 'https://script.google.com/macros/s/AKfycbwYI6KlgF90HBv6_rFZGUV-ZLq-aNqa3FDQtorybGv9WJLQOzvhRMyTal7Iw483khxz/exec';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed', 405);
}

if (!Session::isLoggedIn()) {
    jsonResponse([
        'success' => false,
        'error' => 'ログインが必要です',
        'error_code' => 'UNAUTHORIZED'
    ], 401);
}

$userId = Session::getUserId();
$user = User::findById($userId);
if (!$user) {
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

$action = trim((string)($_GET['action'] ?? 'browser'));
if ($action !== 'browser') {
    jsonResponse([
        'success' => false,
        'error' => 'action must be browser',
        'error_code' => 'INVALID_ACTION'
    ], 400);
}

$yearMonth = trim((string)($_GET['yearMonth'] ?? ''));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $yearMonth)) {
    jsonResponse([
        'success' => false,
        'error' => 'yearMonth must be in YYYY-MM format',
        'error_code' => 'INVALID_YEARMONTH'
    ], 400);
}

$teacherName = trim((string)($_GET['teacherName'] ?? ''));
$subject = trim((string)($_GET['subject'] ?? ''));
$studentName = trim((string)($_GET['studentName'] ?? ''));
$className = trim((string)($_GET['className'] ?? ''));

if (!$user->hasPermission('manage_users')) {
    $teacherName = trim((string)$user->name);
    if ($teacherName === '') {
        jsonResponse([
            'success' => false,
            'error' => 'ユーザー名が取得できません',
            'error_code' => 'EMPTY_USER_NAME'
        ], 401);
    }
}

if (strpos(CLASS_REPORT_SEARCH_GAS_URL, 'REPLACE_WITH_NEW_GAS_DEPLOYMENT_ID') !== false) {
    jsonResponse([
        'success' => false,
        'error' => 'GAS URL is not configured',
        'error_code' => 'GAS_URL_NOT_CONFIGURED'
    ], 502);
}

$query = http_build_query([
    'action' => $action,
    'format' => 'json',
    'teacherName' => $teacherName,
    'subject' => $subject,
    'yearMonth' => $yearMonth,
    'studentName' => $studentName,
    'className' => $className
], '', '&', PHP_QUERY_RFC3986);

$url = CLASS_REPORT_SEARCH_GAS_URL . '?' . $query;

$responseBody = false;
$httpCode = 0;
$contentType = '';
$curlError = '';
$streamError = '';

if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json'
        ]
    ]);

    $responseBody = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $curlError = (string)curl_error($ch);
    curl_close($ch);
}

if ($responseBody === false) {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 60,
            'ignore_errors' => true,
            'follow_location' => 1,
            'max_redirects' => 10,
            'header' => "Accept: application/json\r\n"
        ]
    ]);

    $responseBody = @file_get_contents($url, false, $context);
    if ($responseBody === false) {
        $lastError = error_get_last();
        if (is_array($lastError) && isset($lastError['message'])) {
            $streamError = (string)$lastError['message'];
        }
    }

    if (isset($http_response_header) && is_array($http_response_header)) {
        foreach ($http_response_header as $headerLine) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/i', $headerLine, $m)) {
                $httpCode = (int)$m[1];
            }
            if (stripos($headerLine, 'Content-Type:') === 0) {
                $contentType = trim(substr($headerLine, strlen('Content-Type:')));
            }
        }
    }
}

if ($responseBody === false) {
    $errorParts = [];
    if ($curlError !== '') {
        $errorParts[] = 'cURL: ' . $curlError;
    }
    if ($streamError !== '') {
        $errorParts[] = 'stream: ' . $streamError;
    }

    jsonResponse([
        'success' => false,
        'error' => 'Failed to call GAS API' . ($errorParts ? ': ' . implode(' / ', $errorParts) : ''),
        'error_code' => 'GAS_REQUEST_FAILED'
    ], 502);
}

if ($httpCode >= 400) {
    $snippet = substr(trim((string)$responseBody), 0, 200);
    jsonResponse([
        'success' => false,
        'error' => 'GAS API returned HTTP ' . $httpCode,
        'error_code' => 'GAS_HTTP_ERROR',
        'details' => $snippet
    ], 502);
}

$decoded = json_decode($responseBody, true);
if (is_array($decoded)) {
    jsonResponse($decoded);
}

$rows = extractRowsFromGasHtmlShell((string)$responseBody, $subject);
if (is_array($rows)) {
    jsonResponse([
        'result' => 'success',
        'rows' => $rows,
        'count' => count($rows),
        'source' => 'bridge_html_fallback'
    ]);
}

$snippet = substr(trim((string)$responseBody), 0, 200);
jsonResponse([
    'success' => false,
    'error' => 'Invalid JSON response from GAS API',
    'error_code' => 'GAS_INVALID_JSON',
    'content_type' => $contentType,
    'details' => $snippet
], 502);

function extractRowsFromGasHtmlShell(string $html, string $subject): ?array
{
    $body = trim($html);
    if ($body === '') {
        return null;
    }

    $userHtml = extractAppsScriptUserHtml($body);
    if (is_string($userHtml) && trim($userHtml) !== '') {
        $body = $userHtml;
    }

    return extractRowsFromHtmlTable($body, $subject);
}

function extractAppsScriptUserHtml(string $html): ?string
{
    if (!preg_match('/goog\.script\.init\("((?:\\\\.|[^"\\\\])*)"/s', $html, $matches)) {
        return null;
    }

    $encoded = $matches[1];
    $jsonEscaped = preg_replace_callback(
        '/\\\\x([0-9A-Fa-f]{2})/',
        static function (array $hexMatches): string {
            return '\\u00' . strtoupper($hexMatches[1]);
        },
        $encoded
    );

    if (!is_string($jsonEscaped)) {
        return null;
    }

    $decodedPayload = json_decode('"' . $jsonEscaped . '"');
    if (!is_string($decodedPayload) || $decodedPayload === '') {
        return null;
    }

    $payload = json_decode($decodedPayload, true);
    if (!is_array($payload)) {
        return null;
    }

    $userHtml = $payload['userHtml'] ?? null;
    return is_string($userHtml) ? $userHtml : null;
}

function extractRowsFromHtmlTable(string $html, string $subject): ?array
{
    $body = trim($html);
    if ($body === '') {
        return null;
    }

    if (stripos($body, '<table') === false) {
        if (strpos($body, '該当するデータ') !== false || strpos($body, 'データがありません') !== false) {
            return [];
        }
        return null;
    }

    if (!class_exists('DOMDocument')) {
        return null;
    }

    $dom = new DOMDocument();
    $prevUseInternalErrors = libxml_use_internal_errors(true);
    $loaded = $dom->loadHTML('<?xml encoding="UTF-8">' . $body, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();
    libxml_use_internal_errors($prevUseInternalErrors);

    if (!$loaded) {
        return null;
    }

    $xpath = new DOMXPath($dom);
    $trNodes = $xpath->query('//table//tr');
    if (!$trNodes instanceof DOMNodeList || $trNodes->length <= 1) {
        return [];
    }

    $rows = [];
    for ($i = 1; $i < $trNodes->length; $i++) {
        $tr = $trNodes->item($i);
        if (!$tr instanceof DOMNode) {
            continue;
        }

        $cells = $xpath->query('./th|./td', $tr);
        if (!$cells instanceof DOMNodeList || $cells->length < 10) {
            continue;
        }

        $dateAndTime = nodeTextWithBreaks($cells->item(0));
        $date = $dateAndTime;
        $timeSlot = '';
        if (preg_match('/(\d{2}:\d{2}\s*-\s*\d{2}:\d{2})/u', $dateAndTime, $matches, PREG_OFFSET_CAPTURE)) {
            $timeSlot = preg_replace('/\s+/u', '', (string)$matches[1][0]);
            $date = trim(substr($dateAndTime, 0, (int)$matches[1][1]));
        }

        $studentClassRaw = nodeTextWithBreaks($cells->item(4));
        $studentClassLines = array_values(array_filter(array_map('trim', preg_split('/\n+/u', $studentClassRaw))));
        $studentNameValue = '';
        $classNameValue = '';
        if (count($studentClassLines) >= 2) {
            $studentNameValue = $studentClassLines[0];
            $classNameValue = implode(' ', array_slice($studentClassLines, 1));
        } elseif (count($studentClassLines) === 1) {
            $classNameValue = $studentClassLines[0];
        }

        $rows[] = [
            'date' => $date,
            'timeSlot' => $timeSlot,
            'branch' => nodeTextWithBreaks($cells->item(1)),
            'teacherName' => nodeTextWithBreaks($cells->item(3)),
            'studentName' => $studentNameValue,
            'className' => $classNameValue,
            'subject' => $subject,
            'textbook' => nodeTextWithBreaks($cells->item(5)),
            'chapter' => nodeTextWithBreaks($cells->item(6)),
            'homework' => nodeTextWithBreaks($cells->item(7)),
            'testContent' => nodeTextWithBreaks($cells->item(8)),
            'testScore' => nodeTextWithBreaks($cells->item(9))
        ];
    }

    return $rows;
}

function nodeTextWithBreaks(?DOMNode $node): string
{
    if ($node === null) {
        return '';
    }

    if (!$node->hasChildNodes()) {
        return normalizeWhitespace((string)$node->textContent, false);
    }

    $chunks = [];
    foreach ($node->childNodes as $child) {
        if (strcasecmp($child->nodeName, 'br') === 0) {
            $chunks[] = "\n";
            continue;
        }
        $chunks[] = $child->hasChildNodes()
            ? nodeTextWithBreaks($child)
            : (string)$child->textContent;
    }

    return normalizeWhitespace(implode('', $chunks), true);
}

function normalizeWhitespace(string $value, bool $keepNewline): string
{
    $normalized = str_replace(["\r\n", "\r"], "\n", $value);
    $normalized = preg_replace('/\x{00A0}/u', ' ', $normalized);
    if (!is_string($normalized)) {
        return '';
    }

    if ($keepNewline) {
        $lines = preg_split('/\n+/u', $normalized);
        if (!is_array($lines)) {
            return trim($normalized);
        }
        $cleanLines = [];
        foreach ($lines as $line) {
            $lineNormalized = preg_replace('/[ \t]+/u', ' ', $line);
            if (!is_string($lineNormalized)) {
                continue;
            }
            $lineNormalized = trim($lineNormalized);
            if ($lineNormalized !== '') {
                $cleanLines[] = $lineNormalized;
            }
        }
        return implode("\n", $cleanLines);
    }

    $singleLine = preg_replace('/\s+/u', ' ', $normalized);
    if (!is_string($singleLine)) {
        return '';
    }
    return trim($singleLine);
}
