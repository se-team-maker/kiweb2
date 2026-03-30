<?php

class Database
{
    private $pdo;

    public function __construct(array $config)
    {
        $charset = $config['charset'] ?? 'utf8mb4';
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['name'],
            $charset
        );
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        $this->pdo = new PDO($dsn, $config['user'], $config['pass'], $options);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function withLock(string $name, int $timeoutSeconds, callable $callback)
    {
        $stmt = $this->pdo->prepare('SELECT GET_LOCK(:name, :timeout) AS got_lock');
        $stmt->execute([
            ':name' => $name,
            ':timeout' => $timeoutSeconds,
        ]);
        $gotLock = (int)($stmt->fetch()['got_lock'] ?? 0);
        if ($gotLock !== 1) {
            throw new ApiException('他のユーザーが処理中です。しばらく待ってから再度お試しください。', 503);
        }

        try {
            return $callback();
        } finally {
            $release = $this->pdo->prepare('SELECT RELEASE_LOCK(:name)');
            $release->execute([':name' => $name]);
        }
    }
}
