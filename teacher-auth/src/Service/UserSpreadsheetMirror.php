<?php
/**
 * Mirror newly created users to a spreadsheet webhook.
 */

namespace App\Service;

use App\Config\Database;
use App\Model\User;
use PDO;

class UserSpreadsheetMirror
{
    public static function mirrorCreatedUser(User $user): bool
    {
        $webhookUrl = trim((string) ($_ENV['USER_SHEET_WEBHOOK_URL'] ?? ''));
        if ($webhookUrl === '') {
            return false;
        }

        $payload = [
            'event' => 'user_created',
            'occurred_at' => date('c'),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'email_verified_at' => $user->emailVerifiedAt,
                'created_at' => $user->createdAt,
                'updated_at' => $user->updatedAt,
                'roles' => $user->getRoles(),
                'scopes' => $user->getScopes(),
            ],
        ];

        return self::postJson($webhookUrl, $payload);
    }

    public static function mirrorAllUsers(): array
    {
        $db = Database::getConnection();
        $stmt = $db->query('SELECT id FROM users ORDER BY created_at ASC');
        $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $total = count($userIds);
        $mirrored = 0;
        $failedIds = [];

        foreach ($userIds as $userId) {
            $user = User::findById((string) $userId);
            if (!$user) {
                $failedIds[] = (string) $userId;
                continue;
            }

            if (self::mirrorCreatedUser($user)) {
                $mirrored++;
                continue;
            }

            $failedIds[] = $user->id;
        }

        return [
            'total' => $total,
            'mirrored' => $mirrored,
            'failed' => count($failedIds),
            'failed_ids' => $failedIds,
        ];
    }

    public static function getAllUsersPayload(): array
    {
        $db = Database::getConnection();
        $stmt = $db->query('SELECT id FROM users ORDER BY created_at ASC');
        $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $total = count($userIds);
        $users = [];
        $failedIds = [];

        foreach ($userIds as $userId) {
            $user = User::findById((string) $userId);
            if (!$user) {
                $failedIds[] = (string) $userId;
                continue;
            }

            $users[] = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'email_verified_at' => $user->emailVerifiedAt,
                'created_at' => $user->createdAt,
                'updated_at' => $user->updatedAt,
                'roles' => $user->getRoles(),
                'scopes' => $user->getScopes(),
            ];
        }

        return [
            'total' => $total,
            'users' => $users,
            'failed' => count($failedIds),
            'failed_ids' => $failedIds,
        ];
    }

    private static function postJson(string $webhookUrl, array $payload): bool
    {
        $secret = trim((string) ($_ENV['USER_SHEET_WEBHOOK_SECRET'] ?? ''));
        $timeout = max(1, (int) ($_ENV['USER_SHEET_TIMEOUT'] ?? 5));
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($body)) {
            error_log('UserSpreadsheetMirror: failed to encode payload.');
            return false;
        }

        $headers = [
            'Content-Type: application/json; charset=utf-8',
            'Content-Length: ' . strlen($body),
        ];

        if ($secret !== '') {
            $headers[] = 'X-User-Sheet-Secret: ' . $secret;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
        ]);

        $result = @file_get_contents($webhookUrl, false, $context);
        $statusCode = self::extractStatusCode($http_response_header ?? []);

        if ($result === false || $statusCode < 200 || $statusCode >= 300) {
            error_log('UserSpreadsheetMirror: webhook request failed. status=' . $statusCode);
            return false;
        }

        return true;
    }

    private static function extractStatusCode(array $responseHeaders): int
    {
        if ($responseHeaders === []) {
            return 0;
        }

        if (preg_match('/\s(\d{3})\s/', (string) $responseHeaders[0], $matches) !== 1) {
            return 0;
        }

        return (int) $matches[1];
    }
}
