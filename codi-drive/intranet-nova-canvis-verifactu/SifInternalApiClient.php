<?php

final class SifInternalApiClient
{
    public function __construct(
        private string $baseUrl,
        private string $keyId,
        private string $secret
    ) {
        $this->baseUrl = rtrim(trim($this->baseUrl), '/');
        $this->keyId = trim($this->keyId);

        if ($this->baseUrl === '' || $this->keyId === '' || $this->secret === '') {
            throw new RuntimeException('SIF internal API client is not configured');
        }

        $this->assertSecureUrl($this->baseUrl);
    }

    public static function fromEnvironment(): self
    {
        return new self(
            (string) getenv('SIF_INTERNAL_API_BASE_URL'),
            (string) getenv('SIF_INTERNAL_API_KEY_ID'),
            (string) getenv('SIF_INTERNAL_API_SECRET')
        );
    }

    public function post(string $path, array $payload, string $actorId, array $roles): array
    {
        $path = '/' . ltrim(trim($path), '/');
        $actorId = trim($actorId);
        $roles = $this->normalizeRoles($roles);

        if ($actorId === '' || $roles === []) {
            throw new RuntimeException('Invalid SIF internal API actor');
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $requestId = $this->uuidV4();
        $canonicalRoles = implode(',', $roles);

        $canonical = implode("\n", [
            'POST',
            $path,
            $timestamp,
            strtolower($requestId),
            $actorId,
            $canonicalRoles,
            hash('sha256', $body),
        ]);

        $signature = hash_hmac('sha256', $canonical, $this->secret);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-SIF-Key-Id: ' . $this->keyId,
            'X-SIF-Timestamp: ' . $timestamp,
            'X-SIF-Request-Id: ' . $requestId,
            'X-SIF-Actor-Id: ' . $actorId,
            'X-SIF-Actor-Roles: ' . $canonicalRoles,
            'X-SIF-Signature: ' . $signature,
        ];

        [$status, $response] = $this->send($this->baseUrl . $path, $headers, $body);

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid SIF internal API response');
        }

        if ($status < 200 || $status >= 300 || (($decoded['ok'] ?? true) === false)) {
            $message = trim((string) ($decoded['error'] ?? 'SIF internal API request failed'));
            throw new RuntimeException($message !== '' ? $message : 'SIF internal API request failed', $status);
        }

        return $decoded;
    }

    private function send(string $url, array $headers, string $body): array
    {
        if (function_exists('curl_init')) {
            $handle = curl_init($url);
            if ($handle === false) {
                throw new RuntimeException('Could not initialize SIF internal API request');
            }

            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => $body,
            ]);

            $response = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
            $error = curl_error($handle);
            curl_close($handle);

            if (!is_string($response)) {
                throw new RuntimeException(
                    $error !== '' ? 'SIF internal API transport error: ' . $error : 'SIF internal API transport error'
                );
            }

            return [$status, $response];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);

        $response = file_get_contents($url, false, $context);
        if (!is_string($response)) {
            throw new RuntimeException('SIF internal API transport error');
        }

        $status = 0;
        foreach (($http_response_header ?? []) as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/i', (string) $header, $matches) === 1) {
                $status = (int) $matches[1];
                break;
            }
        }

        return [$status, $response];
    }

    private function assertSecureUrl(string $url): void
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            throw new RuntimeException('Invalid SIF internal API URL');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($scheme === 'https') {
            return;
        }

        $allowLocalHttp = filter_var(
            getenv('SIF_INTERNAL_API_ALLOW_HTTP') ?: '0',
            FILTER_VALIDATE_BOOLEAN
        );
        if ($allowLocalHttp && $scheme === 'http' && in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            return;
        }

        throw new RuntimeException('SIF internal API requires HTTPS');
    }

    private function normalizeRoles(array $roles): array
    {
        $normalized = [];
        foreach ($roles as $role) {
            $value = strtoupper(trim((string) $role));
            if ($value !== '') {
                $normalized[$value] = true;
            }
        }

        $roles = array_keys($normalized);
        sort($roles, SORT_STRING);

        return $roles;
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20)
        );
    }
}
