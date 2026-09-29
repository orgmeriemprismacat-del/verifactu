<?php

class SifInternalDocumentClient
{
    private string $url;
    private string $signedPath;
    private string $keyId;
    private string $secret;
    private int $timeout;

    public function __construct(
        ?string $url = null,
        ?string $signedPath = null,
        ?string $keyId = null,
        ?string $secret = null,
        int $timeout = 20
    ) {
        $this->url = trim((string) ($url ?? getenv('SIF_INTERNAL_DOCUMENT_API_URL') ?: ''));
        $this->signedPath = trim((string) ($signedPath ?? getenv('SIF_INTERNAL_DOCUMENT_SIGNED_PATH') ?: '/api/documents/download.php'));
        $this->keyId = trim((string) ($keyId ?? getenv('SIF_INTERNAL_API_KEY_ID') ?: ''));
        $this->secret = trim((string) ($secret ?? getenv('SIF_INTERNAL_API_SECRET') ?: ''));
        $this->timeout = max(1, min(60, $timeout));

        if ($this->url === '' || $this->keyId === '' || $this->secret === '') {
            throw new RuntimeException('SIF internal document API is not configured');
        }

        $this->assertSecureUrl($this->url);
    }

    public function download(string $actorId, array $roles, int $documentId): array
    {
        if ($documentId <= 0) {
            throw new InvalidArgumentException('Invalid SIF document id');
        }

        $actorId = trim($actorId);
        $roles = $this->normalizeRoles($roles);
        if ($actorId === '' || $roles === []) {
            throw new InvalidArgumentException('Missing SIF actor identity');
        }

        $body = json_encode(
            ['document_id' => $documentId],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        if ($body === false) {
            throw new RuntimeException('Could not encode SIF document request');
        }

        $timestamp = (string) time();
        $requestId = $this->uuidV4();
        $canonicalRoles = implode(',', $roles);
        $canonical = implode("\n", [
            'POST',
            $this->signedPath,
            $timestamp,
            $requestId,
            $actorId,
            $canonicalRoles,
            hash('sha256', $body),
        ]);
        $signature = hash_hmac('sha256', $canonical, $this->secret);

        $headers = [
            'Content-Type: application/json; charset=utf-8',
            'Accept: application/octet-stream, application/pdf, application/xml',
            'X-SIF-Key-Id: ' . $this->keyId,
            'X-SIF-Timestamp: ' . $timestamp,
            'X-SIF-Request-Id: ' . $requestId,
            'X-SIF-Actor-Id: ' . $actorId,
            'X-SIF-Actor-Roles: ' . $canonicalRoles,
            'X-SIF-Signature: ' . $signature,
        ];

        return $this->send($headers, $body);
    }

    private function send(array $headers, string $body): array
    {
        if (function_exists('curl_init')) {
            $responseHeaders = [];
            $curl = curl_init($this->url);
            if ($curl === false) {
                throw new RuntimeException('Could not initialize SIF document client');
            }

            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                    $trimmed = trim($line);
                    if ($trimmed !== '' && str_contains($trimmed, ':')) {
                        [$name, $value] = explode(':', $trimmed, 2);
                        $responseHeaders[strtolower(trim($name))] = trim($value);
                    }
                    return strlen($line);
                },
            ]);

            $bytes = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            curl_close($curl);

            if (!is_string($bytes)) {
                throw new RuntimeException(
                    $error !== '' ? 'Could not reach SIF document API: ' . $error : 'Could not reach SIF document API'
                );
            }

            return [
                'status' => $status,
                'headers' => $responseHeaders,
                'bytes' => $bytes,
            ];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => $this->timeout,
                'ignore_errors' => true,
            ],
        ]);

        $bytes = file_get_contents($this->url, false, $context);
        if ($bytes === false) {
            throw new RuntimeException('Could not reach SIF document API');
        }

        $responseHeaders = [];
        $status = 0;
        foreach (($http_response_header ?? []) as $line) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', (string) $line, $matches) === 1) {
                $status = (int) $matches[1];
            } elseif (str_contains((string) $line, ':')) {
                [$name, $value] = explode(':', (string) $line, 2);
                $responseHeaders[strtolower(trim($name))] = trim($value);
            }
        }

        return [
            'status' => $status,
            'headers' => $responseHeaders,
            'bytes' => $bytes,
        ];
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

        if ($allowLocalHttp
            && $scheme === 'http'
            && in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
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

        return array_keys($normalized);
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
            substr($hex, 20, 12)
        );
    }
}
