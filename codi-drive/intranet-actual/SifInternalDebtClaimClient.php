<?php

final class SifInternalDebtClaimClient
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
        $this->url = trim((string) ($url ?? getenv('SIF_INTERNAL_DEBT_CLAIM_URL') ?: ''));
        $this->signedPath = trim((string) (
            $signedPath ?? getenv('SIF_INTERNAL_DEBT_CLAIM_SIGNED_PATH') ?: '/api/debt-claims/manage.php'
        ));
        $this->keyId = trim((string) ($keyId ?? getenv('SIF_INTERNAL_API_KEY_ID') ?: ''));
        $this->secret = trim((string) ($secret ?? getenv('SIF_INTERNAL_API_SECRET') ?: ''));
        $this->timeout = max(1, min(30, $timeout));

        if ($this->url === '' || $this->keyId === '' || $this->secret === '') {
            throw new RuntimeException('SIF debt claim internal API is not configured');
        }
        $this->assertSecureUrl($this->url);
    }

    public function preview(string $actorId, array $roles, array $criteria): array
    {
        return $this->request($actorId, $roles, array_merge($criteria, ['action' => 'preview']));
    }

    public function recordNotice(string $actorId, array $roles, array $payload): array
    {
        return $this->request($actorId, $roles, array_merge($payload, ['action' => 'record_notice']));
    }

    public function reconcileAfterPayment(string $actorId, array $roles, array $payload): array
    {
        return $this->request(
            $actorId,
            $roles,
            array_merge($payload, ['action' => 'reconcile_after_payment'])
        );
    }

    public function request(string $actorId, array $roles, array $payload): array
    {
        $actorId = trim($actorId);
        $roles = $this->normalizeRoles($roles);
        if ($actorId === '' || $roles === []) {
            throw new InvalidArgumentException('Missing SIF debt claim actor context');
        }

        $body = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );
        if ($body === false) {
            throw new RuntimeException('Could not encode SIF debt claim payload');
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

        [$status, $response] = $this->send([
            'Content-Type: application/json; charset=utf-8',
            'Accept: application/json',
            'X-SIF-Key-Id: ' . $this->keyId,
            'X-SIF-Timestamp: ' . $timestamp,
            'X-SIF-Request-Id: ' . $requestId,
            'X-SIF-Actor-Id: ' . $actorId,
            'X-SIF-Actor-Roles: ' . $canonicalRoles,
            'X-SIF-Signature: ' . $signature,
        ], $body);

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid SIF debt claim response');
        }
        $decoded['_http_status'] = $status;

        return $decoded;
    }

    private function send(array $headers, string $body): array
    {
        if (function_exists('curl_init')) {
            $curl = curl_init($this->url);
            if ($curl === false) {
                throw new RuntimeException('Could not initialize SIF debt claim client');
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
                    $error !== '' ? 'Could not reach SIF debt claim API: ' . $error : 'Could not reach SIF debt claim API'
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
            throw new RuntimeException('Could not reach SIF debt claim API');
        }

        return [$this->httpStatus($http_response_header ?? []), $response];
    }

    private function assertSecureUrl(string $url): void
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            throw new RuntimeException('Invalid SIF debt claim API URL');
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
        throw new RuntimeException('SIF debt claim internal API requires HTTPS');
    }

    private function normalizeRoles(array $roles): array
    {
        $out = [];
        foreach ($roles as $role) {
            $role = strtoupper(trim((string) $role));
            if ($role !== '') {
                $out[$role] = true;
            }
        }
        $roles = array_keys($out);
        sort($roles, SORT_STRING);

        return $roles;
    }

    private function httpStatus(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/i', (string) $header, $m) === 1) {
                return (int) $m[1];
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
