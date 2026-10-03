<?php

final class SifInternalInstallmentClient
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
        int $timeout = 10
    ) {
        $this->url = trim((string) ($url ?? getenv('SIF_INTERNAL_INSTALLMENT_URL') ?: ''));
        $this->signedPath = trim((string) (
            $signedPath ?? getenv('SIF_INTERNAL_INSTALLMENT_SIGNED_PATH') ?: '/api/payments/installment.php'
        ));
        $this->keyId = trim((string) ($keyId ?? getenv('SIF_INTERNAL_API_KEY_ID') ?: ''));
        $this->secret = (string) ($secret ?? getenv('SIF_INTERNAL_API_SECRET') ?: '');
        $this->timeout = max(1, min(30, $timeout));

        if ($this->url === '' || $this->keyId === '' || $this->secret === '') {
            throw new RuntimeException('SIF installment internal API is not configured');
        }

        $parts = parse_url($this->url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $allowLocalHttp = filter_var(
            getenv('SIF_INTERNAL_API_ALLOW_HTTP') ?: '0',
            FILTER_VALIDATE_BOOLEAN
        );
        if ($scheme !== 'https'
            && !($allowLocalHttp && $scheme === 'http' && in_array($host, ['127.0.0.1', 'localhost', '::1'], true))
        ) {
            throw new RuntimeException('SIF installment internal API requires HTTPS');
        }
    }

    public function register(
        string $actorId,
        array $roles,
        ?string $uuidFactura,
        ?string $numVisible,
        array $input
    ): array {
        $payload = [
            'action' => 'register',
            'input' => $input,
        ];

        if ($uuidFactura !== null && trim($uuidFactura) !== '') {
            $payload['uuid_factura'] = trim($uuidFactura);
        }
        if ($numVisible !== null && trim($numVisible) !== '') {
            $payload['num_visible'] = trim($numVisible);
        }

        return $this->request($actorId, $roles, $payload);
    }

    private function request(string $actorId, array $roles, array $payload): array
    {
        $actorId = trim($actorId);
        if ($actorId === '') {
            throw new InvalidArgumentException('Missing SIF installment actor id');
        }

        $normalized = [];
        foreach ($roles as $role) {
            $value = strtoupper(trim((string) $role));
            if ($value !== '') {
                $normalized[$value] = true;
            }
        }
        $roles = array_keys($normalized);
        sort($roles, SORT_STRING);
        if ($roles === []) {
            throw new InvalidArgumentException('Missing SIF installment actor roles');
        }

        $body = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );
        if ($body === false) {
            throw new RuntimeException('Could not encode SIF installment request');
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
            'Accept: application/json',
            'X-SIF-Key-Id: ' . $this->keyId,
            'X-SIF-Timestamp: ' . $timestamp,
            'X-SIF-Request-Id: ' . $requestId,
            'X-SIF-Actor-Id: ' . $actorId,
            'X-SIF-Actor-Roles: ' . $canonicalRoles,
            'X-SIF-Signature: ' . $signature,
        ];

        $curl = curl_init($this->url);
        if ($curl === false) {
            throw new RuntimeException('Could not initialize SIF installment client');
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
                $error !== '' ? 'Could not reach SIF installment API: ' . $error : 'Could not reach SIF installment API'
            );
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid SIF installment API response');
        }
        $decoded['_http_status'] = $status;

        return $decoded;
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
