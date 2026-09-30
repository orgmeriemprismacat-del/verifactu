<?php

declare(strict_types=1);

/**
 * Minimal HMAC client for POST /api/redsys/intents/create.php.
 * Keeps the checkout decoupled from the SIF database.
 */
final class SifPaymentIntentClient
{
    private string $url;
    private string $signedPath;
    private string $keyId;
    private string $secret;
    private string $actorId;
    private array $roles;
    private int $timeout;

    public function __construct(int $timeout = 10)
    {
        $this->url = trim((string) (getenv('SIF_REDSYS_INTENT_API_URL') ?: ''));
        $this->signedPath = trim((string) (
            getenv('SIF_INTERNAL_REDSYS_INTENT_SIGNED_PATH') ?: '/api/redsys/intents/create.php'
        ));
        $this->keyId = trim((string) (getenv('SIF_INTERNAL_API_KEY_ID') ?: ''));
        $this->secret = trim((string) (getenv('SIF_INTERNAL_API_SECRET') ?: ''));
        $this->actorId = trim((string) (getenv('SIF_REDSYS_INTENT_ACTOR_ID') ?: 'pay-prisma-cat'));
        $this->roles = array_values(array_filter(array_map(
            static fn (string $role): string => strtoupper(trim($role)),
            explode(',', (string) (getenv('SIF_REDSYS_INTENT_ACTOR_ROLES') ?: ''))
        )));
        $this->timeout = max(1, min(30, $timeout));

        if ($this->url === '' || $this->keyId === '' || $this->secret === ''
            || $this->actorId === '' || $this->roles === []
        ) {
            throw new RuntimeException('SIF payment intent API is not configured');
        }
        $this->assertSecureUrl($this->url);
    }

    public function create(array $payload): array
    {
        $body = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );
        if ($body === false) {
            throw new RuntimeException('Could not encode SIF payment intent');
        }

        $timestamp = (string) time();
        $requestId = $this->uuidV4();
        $roles = implode(',', $this->roles);
        $canonical = implode("\n", [
            'POST',
            $this->signedPath,
            $timestamp,
            $requestId,
            $this->actorId,
            $roles,
            hash('sha256', $body),
        ]);
        $signature = hash_hmac('sha256', $canonical, $this->secret);

        [$status, $response] = $this->send([
            'Content-Type: application/json; charset=utf-8',
            'Accept: application/json',
            'X-SIF-Key-Id: ' . $this->keyId,
            'X-SIF-Timestamp: ' . $timestamp,
            'X-SIF-Request-Id: ' . $requestId,
            'X-SIF-Actor-Id: ' . $this->actorId,
            'X-SIF-Actor-Roles: ' . $roles,
            'X-SIF-Signature: ' . $signature,
        ], $body);

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid SIF payment intent response');
        }
        if ($status < 200 || $status >= 300 || ($decoded['ok'] ?? false) !== true
            || !is_array($decoded['intent'] ?? null)
        ) {
            throw new RuntimeException('SIF payment intent rejected: HTTP ' . $status);
        }

        return $decoded['intent'];
    }

    private function send(array $headers, string $body): array
    {
        if (function_exists('curl_init')) {
            $curl = curl_init($this->url);
            if ($curl === false) {
                throw new RuntimeException('Could not initialize SIF payment intent client');
            }
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_FOLLOWLOCATION => false,
            ]);
            $response = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            curl_close($curl);
            if (!is_string($response)) {
                throw new RuntimeException(
                    $error !== '' ? 'Could not reach SIF payment intent API: ' . $error
                        : 'Could not reach SIF payment intent API'
                );
            }
            return [$status, $response];
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
        $response = file_get_contents($this->url, false, $context);
        if ($response === false) {
            throw new RuntimeException('Could not reach SIF payment intent API');
        }
        return [$this->httpStatus($http_response_header ?? []), $response];
    }

    private function assertSecureUrl(string $url): void
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            throw new RuntimeException('Invalid SIF payment intent URL');
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($scheme === 'https') {
            return;
        }
        $allowLocal = filter_var(
            getenv('SIF_INTERNAL_API_ALLOW_HTTP') ?: '0',
            FILTER_VALIDATE_BOOLEAN
        );
        if ($allowLocal && $scheme === 'http'
            && in_array($host, ['127.0.0.1', 'localhost', '::1'], true)
        ) {
            return;
        }
        throw new RuntimeException('SIF payment intent API requires HTTPS');
    }

    private function httpStatus(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/', (string) $header, $matches) === 1) {
                return (int) $matches[1];
            }
        }
        return 0;
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
