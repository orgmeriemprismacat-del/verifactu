<?php

declare(strict_types=1);

/**
 * Server-to-server client for UC-018.
 *
 * The caller sends only the committed legacy enrollment ID and the gift code.
 * Canonical participant identity and price/tax context are resolved inside the
 * SIF boundary; this client must never forward browser identity or amounts.
 */
final class SifGiftRedemptionClient
{
    private string $baseUrl;
    private string $signedPath;
    private string $keyId;
    private string $secret;
    private string $actorId;
    private array $roles;
    private int $timeout;

    public function __construct(int $timeout = 10)
    {
        $this->baseUrl = rtrim(
            trim((string) (getenv('SIF_INTERNAL_API_BASE_URL') ?: '')),
            '/'
        );
        $this->signedPath = trim((string) (
            getenv('SIF_INTERNAL_GIFT_REDEMPTION_SIGNED_PATH')
                ?: '/api/gifts/redemption/redeem.php'
        ));
        $this->keyId = trim((string) (getenv('SIF_INTERNAL_API_KEY_ID') ?: ''));
        $this->secret = trim((string) (getenv('SIF_INTERNAL_API_SECRET') ?: ''));
        $this->actorId = trim((string) (
            getenv('SIF_GIFT_REDEMPTION_ACTOR_ID') ?: 'web-prisma-cat'
        ));
        $this->roles = array_values(array_unique(array_filter(array_map(
            static fn (string $role): string => strtoupper(trim($role)),
            explode(',', (string) (getenv('SIF_GIFT_REDEMPTION_ACTOR_ROLES') ?: ''))
        ))));
        $this->timeout = max(1, min(30, $timeout));

        if ($this->baseUrl === ''
            || $this->signedPath === ''
            || !str_starts_with($this->signedPath, '/')
            || $this->keyId === ''
            || $this->secret === ''
            || $this->actorId === ''
            || $this->roles === []
        ) {
            throw new RuntimeException('SIF gift redemption API is not configured');
        }

        $this->assertSecureBaseUrl($this->baseUrl);
    }

    public function redeemCommittedEnrollment(array $trustedPayload): array
    {
        $this->validatePayload($trustedPayload);

        $body = json_encode(
            $trustedPayload,
            JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_PRESERVE_ZERO_FRACTION
        );
        if ($body === false) {
            throw new RuntimeException('Could not encode SIF gift redemption request');
        }

        $timestamp = (string) time();
        $requestId = $this->uuidV4();
        $roles = implode(',', $this->roles);
        $canonical = implode("\n", [
            'POST',
            $this->signedPath,
            $timestamp,
            strtolower($requestId),
            $this->actorId,
            $roles,
            hash('sha256', $body),
        ]);
        $signature = hash_hmac('sha256', $canonical, $this->secret);

        [$status, $response] = $this->send(
            $this->baseUrl . $this->signedPath,
            [
                'Content-Type: application/json; charset=utf-8',
                'Accept: application/json',
                'X-SIF-Key-Id: ' . $this->keyId,
                'X-SIF-Timestamp: ' . $timestamp,
                'X-SIF-Request-Id: ' . strtolower($requestId),
                'X-SIF-Actor-Id: ' . $this->actorId,
                'X-SIF-Actor-Roles: ' . $roles,
                'X-SIF-Signature: ' . $signature,
            ],
            $body
        );

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid SIF gift redemption response');
        }

        if ($status < 200
            || $status >= 300
            || ($decoded['ok'] ?? false) !== true
            || !is_array($decoded['redemption'] ?? null)
            || !is_array($decoded['legacy_reconciliation'] ?? null)
        ) {
            throw new RuntimeException(
                'SIF gift redemption was rejected: HTTP ' . $status
            );
        }

        return $decoded;
    }

    private function validatePayload(array $payload): void
    {
        $enrollmentId = $payload['enrollment_id'] ?? null;
        if (!is_numeric($enrollmentId) || (int) $enrollmentId <= 0) {
            throw new InvalidArgumentException('Invalid trusted enrollment ID');
        }

        $giftCode = trim((string) ($payload['gift_code'] ?? ''));
        if ($giftCode === '' || strlen($giftCode) > 200) {
            throw new InvalidArgumentException(
                'Invalid trusted gift redemption field: gift_code'
            );
        }

        foreach (['holder_party_key', 'trusted_price_snapshot'] as $forbidden) {
            if (array_key_exists($forbidden, $payload)) {
                throw new InvalidArgumentException(
                    'Gift redemption authority must be resolved inside SIF: ' . $forbidden
                );
            }
        }
    }

    private function send(string $url, array $headers, string $body): array
    {
        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            if ($curl === false) {
                throw new RuntimeException(
                    'Could not initialize SIF gift redemption client'
                );
            }

            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => min(5, $this->timeout),
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_FOLLOWLOCATION => false,
            ]);

            $response = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            curl_close($curl);

            if (!is_string($response)) {
                throw new RuntimeException(
                    $error !== ''
                        ? 'Could not reach SIF gift redemption API: ' . $error
                        : 'Could not reach SIF gift redemption API'
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
                'follow_location' => 0,
            ],
        ]);
        $response = file_get_contents($url, false, $context);
        if ($response === false) {
            throw new RuntimeException('Could not reach SIF gift redemption API');
        }

        return [$this->httpStatus($http_response_header ?? []), $response];
    }

    private function assertSecureBaseUrl(string $url): void
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            throw new RuntimeException('Invalid SIF gift redemption base URL');
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
        if ($allowLocal
            && $scheme === 'http'
            && in_array($host, ['127.0.0.1', 'localhost', '::1'], true)
        ) {
            return;
        }

        throw new RuntimeException('SIF gift redemption API requires HTTPS');
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
