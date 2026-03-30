<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Tokyo');

function ts_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function ts_error(string $message, int $status = 400): void
{
    ts_json_response([
        'success' => false,
        'error' => $message,
    ], $status);
}

function ts_get_header(string $name): string
{
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    $value = $_SERVER[$serverKey] ?? '';

    if ($value === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $headerName => $headerValue) {
            if (strcasecmp($headerName, $name) === 0) {
                $value = (string)$headerValue;
                break;
            }
        }
    }

    return trim((string)$value);
}

function ts_strip_wrapped_quotes(string $value): string
{
    $length = strlen($value);
    if ($length >= 2) {
        $first = $value[0];
        $last = $value[$length - 1];
        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
            return substr($value, 1, -1);
        }
    }

    return $value;
}

function ts_load_env_file(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        if (strpos($line, 'export ') === 0) {
            $line = trim(substr($line, 7));
        }

        $position = strpos($line, '=');
        if ($position === false) {
            continue;
        }

        $name = trim(substr($line, 0, $position));
        $value = trim(substr($line, $position + 1));
        if ($name === '') {
            continue;
        }

        $value = ts_strip_wrapped_quotes($value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
        putenv($name . '=' . $value);
    }
}

function ts_bootstrap(): array
{
    $syncRoot = dirname(__DIR__);
    $repoRoot = dirname($syncRoot);
    $teacherAuthRoot = $repoRoot . DIRECTORY_SEPARATOR . 'teacher-auth';

    // teacher-auth の既存設定を先に読み込み、teacher-sync で上書き可能にする
    ts_load_env_file($teacherAuthRoot . DIRECTORY_SEPARATOR . '.env');
    ts_load_env_file($syncRoot . DIRECTORY_SEPARATOR . '.env');

    return [
        'db' => [
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'name' => $_ENV['DB_NAME'] ?? '',
            'user' => $_ENV['DB_USER'] ?? '',
            'pass' => $_ENV['DB_PASS'] ?? '',
            'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
        ],
        'auth' => [
            'secret' => $_ENV['TEACHER_SYNC_SECRET'] ?? '',
            'bearer_token' => $_ENV['TEACHER_SYNC_BEARER_TOKEN'] ?? '',
            'default_role_filter' => $_ENV['TEACHER_SYNC_DEFAULT_ROLE_FILTER'] ?? 'all',
            'clock_skew_seconds' => 300,
        ],
    ];
}

function ts_pdo(array $dbConfig): PDO
{
    if (($dbConfig['name'] ?? '') === '') {
        throw new RuntimeException('DB_NAME is not configured');
    }

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $dbConfig['host'],
        $dbConfig['name'],
        $dbConfig['charset']
    );

    try {
        $pdo = new PDO(
            $dsn,
            (string)$dbConfig['user'],
            (string)$dbConfig['pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        $pdo->exec("SET time_zone = '+09:00'");

        return $pdo;
    } catch (PDOException $exception) {
        throw new RuntimeException('Database connection failed', 0, $exception);
    }
}
