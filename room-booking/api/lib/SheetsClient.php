<?php

class SheetsClient
{
    private $config;
    private $accessToken;
    private $tokenExpiresAt;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, null, $query);
    }

    public function post(string $path, $body, array $query = []): array
    {
        return $this->request('POST', $path, $body, $query);
    }

    public function put(string $path, $body, array $query = []): array
    {
        return $this->request('PUT', $path, $body, $query);
    }

    private function request(string $method, string $path, $body, array $query): array
    {
        $token = $this->getAccessToken();
        $url = 'https://sheets.googleapis.com/v4/' . ltrim($path, '/');
        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        error_log(sprintf('[SheetsClient:request] %s %s', $method, $url));

        $headers = [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
        ];
        $payload = null;
        if ($body !== null) {
            $payload = json_encode($body, JSON_UNESCAPED_UNICODE);
            $headers[] = 'Content-Type: application/json; charset=utf-8';
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            if ($payload !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            }
            $response = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($response === false) {
                $error = curl_error($ch);
                curl_close($ch);
                throw new ApiException('Sheets API request failed: ' . $error, 500);
            }
            curl_close($ch);
        } else {
            $context = [
                'http' => [
                    'method' => $method,
                    'header' => implode("\r\n", $headers),
                    'ignore_errors' => true,
                ],
            ];
            if ($payload !== null) {
                $context['http']['content'] = $payload;
            }
            $response = file_get_contents($url, false, stream_context_create($context));
            $status = 0;
            if (isset($http_response_header)) {
                foreach ($http_response_header as $line) {
                    if (preg_match('#HTTP/\d+\.\d+\s+(\d+)#', $line, $m)) {
                        $status = (int)$m[1];
                        break;
                    }
                }
            }
        }

        $decoded = json_decode($response ?: '', true);
        if ($status >= 400) {
            $message = $decoded['error']['message'] ?? ('Sheets API error (HTTP ' . $status . ')');
            throw new ApiException($message, $status, $decoded);
        }
        return $decoded ?: [];
    }

    private function getAccessToken(): string
    {
        if ($this->accessToken && $this->tokenExpiresAt && time() < $this->tokenExpiresAt - 60) {
            return $this->accessToken;
        }

        $jsonPath = $this->config['service_account_json'] ?? '';
        if (!$jsonPath || !is_file($jsonPath)) {
            throw new ApiException('Service account JSON not found for Sheets sync.', 500);
        }
        $creds = json_decode(file_get_contents($jsonPath), true);
        if (!$creds || empty($creds['client_email']) || empty($creds['private_key'])) {
            throw new ApiException('Invalid service account JSON.', 500);
        }

        $now = time();
        $payload = [
            'iss' => $creds['client_email'],
            'scope' => 'https://www.googleapis.com/auth/spreadsheets',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ];
        if (!empty($this->config['impersonate_user'])) {
            $payload['sub'] = $this->config['impersonate_user'];
        }
        $jwt = $this->buildJwt($payload, $creds['private_key']);

        $tokenResponse = $this->tokenRequest($jwt);
        $this->accessToken = $tokenResponse['access_token'] ?? null;
        $expiresIn = (int)($tokenResponse['expires_in'] ?? 3600);
        $this->tokenExpiresAt = $now + $expiresIn;

        if (!$this->accessToken) {
            throw new ApiException('Failed to obtain Sheets access token.', 500);
        }
        return $this->accessToken;
    }

    private function tokenRequest(string $jwt): array
    {
        $postFields = http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
        ];

        if (function_exists('curl_init')) {
            $ch = curl_init('https://oauth2.googleapis.com/token');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
            $response = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($response === false) {
                $error = curl_error($ch);
                curl_close($ch);
                throw new ApiException('Token request failed: ' . $error, 500);
            }
            curl_close($ch);
        } else {
            $context = [
                'http' => [
                    'method' => 'POST',
                    'header' => implode("\r\n", $headers),
                    'content' => $postFields,
                    'ignore_errors' => true,
                ],
            ];
            $response = file_get_contents('https://oauth2.googleapis.com/token', false, stream_context_create($context));
            $status = 0;
            if (isset($http_response_header)) {
                foreach ($http_response_header as $line) {
                    if (preg_match('#HTTP/\d+\.\d+\s+(\d+)#', $line, $m)) {
                        $status = (int)$m[1];
                        break;
                    }
                }
            }
        }

        $decoded = json_decode($response ?: '', true);
        if ($status >= 400) {
            $message = $decoded['error_description'] ?? 'Token request failed.';
            throw new ApiException($message, $status, $decoded);
        }
        return $decoded ?: [];
    }

    private function buildJwt(array $payload, string $privateKey): string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $segments = [
            $this->base64UrlEncode(json_encode($header)),
            $this->base64UrlEncode(json_encode($payload)),
        ];
        $signingInput = implode('.', $segments);
        $signature = '';
        $ok = openssl_sign($signingInput, $signature, $privateKey, 'sha256');
        if (!$ok) {
            throw new ApiException('Failed to sign JWT for Sheets.', 500);
        }
        $segments[] = $this->base64UrlEncode($signature);
        return implode('.', $segments);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
