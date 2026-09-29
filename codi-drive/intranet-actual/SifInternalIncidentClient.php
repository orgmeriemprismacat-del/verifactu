<?php

final class SifInternalIncidentClient
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
        $this->url = trim((string) ($url ?? getenv('SIF_INTERNAL_INCIDENTS_URL') ?: ''));
        $this->signedPath = trim((string) (
            $signedPath ?? getenv('SIF_INTERNAL_INCIDENT_SIGNED_PATH') ?: '/api/incidents/manage.php'
        ));
        $this->keyId = trim((string) ($keyId ?? getenv('SIF_INTERNAL_API_KEY_ID') ?: ''));
        $this->secret = trim((string) ($secret ?? getenv('SIF_INTERNAL_API_SECRET') ?: ''));
        $this->timeout = max(1, min(30, $timeout));

        if ($this->url === '' || $this->keyId === '' || $this->secret === '') {
            throw new RuntimeException('SIF incident internal API is not configured');
        }

        $this->assertSecureUrl($this->url);
    }

    public function request(string $actorId, array $roles, array $payload): array
    {
        $actorId = trim($actorId);
        if ($actorId === '') {
            throw new InvalidArgumentException('Missing SIF actor id');
        }

        $roles = $this->normalizeRoles($roles);
        if ($roles === []) {
            throw new InvalidArgumentException('Missing SIF actor roles');
        }

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new RuntimeException('Could not encode SIF incident payload');
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
            throw new RuntimeException('Could not initialize SIF incident HTTP client');
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
            throw new RuntimeException($error !== '' ? $error : 'Could not reach SIF incident API');
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid SIF incident response');
        }
        $decoded['_http_status'] = $status;
        return $decoded;
    }

    private function assertSecureUrl(string $url): void
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            throw new RuntimeException('Invalid SIF incident API URL');
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($scheme === 'https') return;

        $allowLocalHttp = filter_var(getenv('SIF_INTERNAL_API_ALLOW_HTTP') ?: '0', FILTER_VALIDATE_BOOLEAN);
        if ($allowLocalHttp && $scheme === 'http' && in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) return;

        throw new RuntimeException('SIF incident internal API requires HTTPS');
    }

    private function normalizeRoles(array $roles): array
    {
        $normalized = [];
        foreach ($roles as $role) {
            $value = strtoupper(trim((string) $role));
            if ($value !== '') $normalized[$value] = true;
        }
        $result = array_keys($normalized);
        sort($result, SORT_STRING);
        return $result;
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf('%s-%s-%s-%s-%s', substr($hex,0,8), substr($hex,8,4), substr($hex,12,4), substr($hex,16,4), substr($hex,20,12));
    }
}
