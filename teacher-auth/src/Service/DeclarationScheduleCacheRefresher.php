<?php

namespace App\Service;

use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

final class DeclarationScheduleCacheRefresher
{
    private const DEFAULT_SOURCE_URL = 'https://script.google.com/macros/s/AKfycbybH6gXsvA8BcuxHxaiuFrTP7M2ANLd_f1RvT8Qp-xMOiXHy5IyviqYKfUKI786c-1g/exec?action=getCachedJson';
    private const REQUIRED_COLUMN_COUNT = 10;

    public static function refresh(?string $sourceUrl = null, ?string $destinationPath = null): array
    {
        $resolvedSourceUrl = self::resolveSourceUrl($sourceUrl);
        $resolvedDestinationPath = self::resolveDestinationPath($destinationPath);

        $responseBody = self::fetchSourceJson($resolvedSourceUrl);
        $payload = json_decode($responseBody, true);
        if (!is_array($payload)) {
            throw new RuntimeException('Invalid JSON response from source GAS.');
        }
        if (self::looksLikeErrorPayload($payload)) {
            throw new RuntimeException('Source GAS returned an error payload: ' . self::encodeForMessage($payload));
        }

        $normalized = self::normalizePayload($payload);
        $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            throw new RuntimeException('Failed to encode cache JSON.');
        }

        self::writeCacheAtomically($resolvedDestinationPath, $json);

        return [
            'record_count' => count($normalized),
            'refreshed_at' => (new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo')))->format(DATE_ATOM),
            'source_url' => $resolvedSourceUrl,
            'destination_path' => $resolvedDestinationPath,
        ];
    }

    private static function resolveSourceUrl(?string $sourceUrl): string
    {
        $resolved = trim((string) ($sourceUrl ?? ($_ENV['DECLARATION_SCHEDULE_SOURCE_URL'] ?? self::DEFAULT_SOURCE_URL)));
        if ($resolved === '') {
            throw new RuntimeException('Declaration schedule source URL is not configured.');
        }

        return self::appendCacheBustQuery($resolved);
    }

    private static function resolveDestinationPath(?string $destinationPath): string
    {
        $resolved = $destinationPath ?: dirname(__DIR__, 2) . '/storage/declaration_schedule_cache.json';
        if (trim($resolved) === '') {
            throw new RuntimeException('Declaration schedule cache destination path is empty.');
        }

        return $resolved;
    }

    private static function appendCacheBustQuery(string $url): string
    {
        $separator = strpos($url, '?') === false ? '?' : '&';
        return $url . $separator . 't=' . rawurlencode((string) microtime(true));
    }

    private static function fetchSourceJson(string $sourceUrl): string
    {
        $responseBody = false;
        $httpCode = 0;

        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $sourceUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json'
                ]
            ]);

            $responseBody = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = (string) curl_error($ch);
            curl_close($ch);

            if ($responseBody === false) {
                throw new RuntimeException('Failed to call source GAS: ' . ($curlError !== '' ? $curlError : 'cURL error'));
            }
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 20,
                    'ignore_errors' => true,
                    'header' => "Accept: application/json\r\n"
                ]
            ]);

            $responseBody = @file_get_contents($sourceUrl, false, $context);
            if ($responseBody === false) {
                throw new RuntimeException('Failed to call source GAS.');
            }

            if (isset($http_response_header) && is_array($http_response_header)) {
                foreach ($http_response_header as $headerLine) {
                    if (preg_match('/^HTTP\/\S+\s+(\d{3})/i', $headerLine, $matches)) {
                        $httpCode = (int) $matches[1];
                    }
                }
            }
        }

        if ($httpCode >= 400) {
            throw new RuntimeException('Source GAS returned HTTP ' . $httpCode . '.');
        }

        return (string) $responseBody;
    }

    private static function looksLikeErrorPayload(array $payload): bool
    {
        return !isset($payload[0]) && (array_key_exists('error', $payload) || array_key_exists('success', $payload));
    }

    private static function normalizePayload(array $payload): array
    {
        if (isset($payload[0]) && is_array($payload[0]) && array_key_exists('講師名', $payload[0])) {
            return self::normalizeObjectRecords($payload);
        }

        return self::normalizeRows($payload);
    }

    private static function normalizeObjectRecords(array $records): array
    {
        $normalized = [];
        foreach ($records as $index => $record) {
            if (!is_array($record)) {
                throw new RuntimeException('Record ' . ($index + 1) . ' is not an object-like array.');
            }

            $normalized[] = [
                '授業名' => self::normalizeScalar($record['授業名'] ?? ''),
                '講師名' => self::normalizeScalar($record['講師名'] ?? ''),
                '予定開始日時' => self::normalizeScalar($record['予定開始日時'] ?? ''),
                '予定終了日時' => self::normalizeScalar($record['予定終了日時'] ?? ''),
                '予定業務No' => self::normalizeScalar($record['予定業務No'] ?? ''),
                'コマ符号' => self::normalizeScalar($record['コマ符号'] ?? ''),
                '生徒名' => self::normalizeScalar($record['生徒名'] ?? ''),
                'STATUS' => self::normalizeScalar($record['STATUS'] ?? ''),
                '授業形態詳細' => self::normalizeScalar($record['授業形態詳細'] ?? ''),
                '授業実施校舎' => self::normalizeScalar($record['授業実施校舎'] ?? ''),
            ];
        }

        return $normalized;
    }

    private static function normalizeRows(array $rows): array
    {
        if (isset($rows[0]) && is_array($rows[0]) && (($rows[0][0] ?? '') === '授業名')) {
            array_shift($rows);
        }

        $normalized = [];
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                throw new RuntimeException('Row ' . ($index + 1) . ' is not an array.');
            }
            if (count($row) < self::REQUIRED_COLUMN_COUNT) {
                throw new RuntimeException('Row ' . ($index + 1) . ' has fewer than ' . self::REQUIRED_COLUMN_COUNT . ' columns.');
            }

            $normalized[] = [
                '授業名' => self::normalizeScalar($row[0] ?? ''),
                '講師名' => self::normalizeScalar($row[1] ?? ''),
                '予定開始日時' => self::normalizeScalar($row[2] ?? ''),
                '予定終了日時' => self::normalizeScalar($row[3] ?? ''),
                '予定業務No' => self::normalizeScalar($row[4] ?? ''),
                'コマ符号' => self::normalizeScalar($row[5] ?? ''),
                '生徒名' => self::normalizeScalar($row[6] ?? ''),
                'STATUS' => self::normalizeScalar($row[7] ?? ''),
                '授業形態詳細' => self::normalizeScalar($row[8] ?? ''),
                '授業実施校舎' => self::normalizeScalar($row[9] ?? ''),
            ];
        }

        return $normalized;
    }

    private static function normalizeScalar($value)
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value)) {
            return trim($value);
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return $value;
        }

        return trim((string) $value);
    }

    private static function writeCacheAtomically(string $destinationPath, string $json): void
    {
        if ($json === '') {
            throw new RuntimeException('Refusing to write an empty cache payload.');
        }

        $directory = dirname($destinationPath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Failed to create cache directory.');
        }

        $tempPath = $destinationPath . '.tmp';
        if (@file_put_contents($tempPath, $json, LOCK_EX) === false) {
            throw new RuntimeException('Failed to write temporary cache file.');
        }

        if (!@rename($tempPath, $destinationPath)) {
            @unlink($tempPath);
            throw new RuntimeException('Failed to replace cache file.');
        }
    }

    private static function encodeForMessage(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($json) ? $json : '[unencodable error payload]';
    }
}
