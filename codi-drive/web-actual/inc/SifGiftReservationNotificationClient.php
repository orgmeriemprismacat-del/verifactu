<?php

declare(strict_types=1);

final class SifGiftReservationNotificationClient
{
    private string $baseUrl;
    private string $path;
    private string $keyId;
    private string $secret;
    private int $timeout;

    public function __construct(int $timeout = 10)
    {
        $this->baseUrl = rtrim(trim((string) getenv('SIF_INTERNAL_API_BASE_URL')), '/');
        $this->path = trim((string) (
            getenv('SIF_INTERNAL_GIFT_RESERVATION_NOTIFICATION_SIGNED_PATH')
                ?: '/api/gifts/reservation/notifications.php'
        ));
        $this->keyId = trim((string) getenv('SIF_INTERNAL_API_KEY_ID'));
        $this->secret = trim((string) getenv('SIF_INTERNAL_API_SECRET'));
        $this->timeout = max(1, min(30, $timeout));

        if ($this->baseUrl === '' || $this->path === '' || !str_starts_with($this->path, '/')
            || $this->keyId === '' || $this->secret === ''
        ) {
            throw new RuntimeException('SIF gift reservation notification API is not configured');
        }

        $parts = parse_url($this->baseUrl);
        $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';
        $host = is_array($parts) ? strtolower((string) ($parts['host'] ?? '')) : '';
        $allowLocal = filter_var(
            getenv('SIF_INTERNAL_API_ALLOW_HTTP') ?: '0',
            FILTER_VALIDATE_BOOLEAN
        );
        if ($scheme !== 'https'
            && !($allowLocal && $scheme === 'http'
                && in_array($host, ['127.0.0.1', 'localhost', '::1'], true))
        ) {
            throw new RuntimeException('SIF gift reservation notification API requires HTTPS');
        }
    }

    public function enqueue(int $giftId): array
    {
        if ($giftId <= 0) {
            throw new InvalidArgumentException('Invalid gift reservation ID');
        }

        $decoded = $this->post([
            'action' => 'enqueue',
            'gift_id' => $giftId,
        ]);

        $bundle = $decoded['bundle'] ?? null;
        if (!is_array($bundle) || !is_array($bundle['notifications'] ?? null)) {
            throw new RuntimeException('Incomplete gift reservation notification bundle');
        }

        return $bundle;
    }

    public function claim(string $uuidNotification): array
    {
        $decoded = $this->post([
            'action' => 'claim',
            'uuid_notification' => $this->uuid($uuidNotification),
        ]);
        if (!is_array($decoded['claim'] ?? null)) {
            throw new RuntimeException('Incomplete gift reservation notification claim');
        }

        return $decoded['claim'];
    }

    public function complete(
        string $uuidNotification,
        string $uuidAttempt,
        bool $accepted
    ): array {
        $decoded = $this->post([
            'action' => 'complete',
            'uuid_notification' => $this->uuid($uuidNotification),
            'uuid_delivery_attempt' => $this->uuid($uuidAttempt),
            'accepted' => $accepted,
        ]);
        if (!is_array($decoded['delivery'] ?? null)) {
            throw new RuntimeException('Incomplete gift reservation notification completion');
        }

        return $decoded['delivery'];
    }

    private function post(array $payload): array
    {
        $body = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );
        if ($body === false) {
            throw new RuntimeException('Could not encode gift reservation notification request');
        }

        $timestamp = (string) time();
        $requestId = $this->uuidV4();
        $actorId = 'web-prisma-cat';
        $roles = 'PAYMENT_CHANNEL';
        $canonical = implode("\n", [
            'POST',
            $this->path,
            $timestamp,
            strtolower($requestId),
            $actorId,
            $roles,
            hash('sha256', $body),
        ]);
        $signature = hash_hmac('sha256', $canonical, $this->secret);

        $curl = curl_init($this->baseUrl . $this->path);
        if ($curl === false) {
            throw new RuntimeException('Could not initialize gift reservation notification request');
        }
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(5, $this->timeout),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json; charset=utf-8',
                'Accept: application/json',
                'X-SIF-Key-Id: ' . $this->keyId,
                'X-SIF-Timestamp: ' . $timestamp,
                'X-SIF-Request-Id: ' . strtolower($requestId),
                'X-SIF-Actor-Id: ' . $actorId,
                'X-SIF-Actor-Roles: ' . $roles,
                'X-SIF-Signature: ' . $signature,
            ],
        ]);

        $raw = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if (!is_string($raw)) {
            throw new RuntimeException(
                $error !== '' ? 'Could not reach gift reservation notification API: ' . $error
                    : 'Could not reach gift reservation notification API'
            );
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || $status < 200 || $status >= 300
            || ($decoded['ok'] ?? false) !== true
        ) {
            throw new RuntimeException('Gift reservation notification request rejected');
        }

        return $decoded;
    }

    private function uuid(string $value): string
    {
        $value = strtolower(trim($value));
        if (preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D',
            $value
        ) !== 1) {
            throw new InvalidArgumentException('Invalid notification UUID');
        }

        return $value;
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
