<?php

class SifInternalApiClient
{
    private string $url;
    private string $signedPath;
    private string $keyId;
    private string $secret;
    private string $courseChangeUrl;
    private string $courseChangeSignedPath;
    private int $timeout;

    public function __construct(
        ?string $url = null,
        ?string $signedPath = null,
        ?string $keyId = null,
        ?string $secret = null,
        int $timeout = 10,
        ?string $courseChangeUrl = null,
        ?string $courseChangeSignedPath = null
    ) {
        $this->url = trim((string) ($url ?? getenv('SIF_INTERNAL_API_URL') ?: ''));
        $this->signedPath = trim((string) ($signedPath ?? getenv('SIF_INTERNAL_API_SIGNED_PATH') ?: '/api/factures/query.php'));
        $this->keyId = trim((string) ($keyId ?? getenv('SIF_INTERNAL_API_KEY_ID') ?: ''));
        $configuredSecret = $secret !== null
            ? $secret
            : (getenv('SIF_INTERNAL_API_SECRET') ?: '');
        $this->secret = (string) $configuredSecret;
        $this->courseChangeUrl = trim((string) ($courseChangeUrl ?? getenv('SIF_COURSE_CHANGE_API_URL') ?: ''));
        $this->courseChangeSignedPath = trim((string) ($courseChangeSignedPath ?? getenv('SIF_INTERNAL_COURSE_CHANGE_SIGNED_PATH') ?: '/api/course-changes/preview.php'));
        $this->timeout = max(1, min(30, $timeout));

        if ($this->url === '' || $this->keyId === '' || $this->secret === '') {
            throw new RuntimeException('SIF internal API is not configured');
        }

        $this->assertSecureUrl($this->url);
        if ($this->courseChangeUrl !== '') {
            $this->assertSecureUrl($this->courseChangeUrl);
        }
    }

    public function viewInvoice(string $actorId, array $roles, string $uuidFactura): array
    {
        return $this->request($actorId, $roles, [
            'action' => 'view',
            'uuid_factura' => trim($uuidFactura),
        ]);
    }

    public function searchInvoices(string $actorId, array $roles, array $criteria, int $limit = 50): array
    {
        return $this->request($actorId, $roles, [
            'action' => 'search',
            'criteria' => $criteria,
            'limit' => max(1, min(100, $limit)),
        ]);
    }

    public function previewInvoiceBeforePayment(
        string $actorId,
        array $roles,
        array $inscriptionIds,
        int $entityId,
        string $observations = ''
    ): array {
        return $this->request($actorId, $roles, [
            'action' => 'preview',
            'inscription_ids' => array_values($inscriptionIds),
            'entity_id' => $entityId,
            'observations' => trim($observations),
        ]);
    }

    public function confirmInvoiceBeforePayment(
        string $actorId,
        array $roles,
        array $inscriptionIds,
        int $entityId,
        string $expectedFingerprint,
        string $observations = ''
    ): array {
        return $this->request($actorId, $roles, [
            'action' => 'confirm',
            'inscription_ids' => array_values($inscriptionIds),
            'entity_id' => $entityId,
            'expected_fingerprint' => strtolower(trim($expectedFingerprint)),
            'observations' => trim($observations),
        ]);
    }

    public function previewCourseChange(string $actorId, array $roles, array $payload): array
    {
        if ($this->courseChangeUrl === '') {
            throw new RuntimeException('SIF course change API is not configured');
        }

        return $this->requestTo(
            $this->courseChangeUrl,
            $this->courseChangeSignedPath,
            $actorId,
            $roles,
            $payload
        );
    }

    private function request(string $actorId, array $roles, array $payload): array
    {
        return $this->requestTo($this->url, $this->signedPath, $actorId, $roles, $payload);
    }

    private function requestTo(string $url, string $signedPath, string $actorId, array $roles, array $payload): array
    {
        $actorId = trim($actorId);
        if ($actorId === '') {
            throw new InvalidArgumentException('Missing SIF actor id');
        }

        $roles = $this->normalizeRoles($roles);
        if ($roles === []) {
            throw new InvalidArgumentException('Missing SIF actor roles');
        }

        $body = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );
        if ($body === false) {
            throw new RuntimeException('Could not encode SIF request payload');
        }

        $timestamp = (string) time();
        $requestId = $this->uuidV4();
        $canonicalRoles = implode(',', $roles);
        $bodyHash = hash('sha256', $body);
        $canonical = implode("\n", [
            'POST',
            $signedPath,
            $timestamp,
            $requestId,
            $actorId,
            $canonicalRoles,
            $bodyHash,
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

        [$status, $response] = $this->send($url, $headers, $body);

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid SIF internal API response');
        }

        $decoded['_http_status'] = $status;
        return $decoded;
    }

    private function send(string $url, array $headers, string $body): array
    {
        if (function_exists('curl_init')) {
            $curl = curl_init($url);
            if ($curl === false) {
                throw new RuntimeException('Could not initialize SIF HTTP client');
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
                    $error !== '' ? 'Could not reach SIF internal API: ' . $error : 'Could not reach SIF internal API'
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

        $response = file_get_contents($url, false, $context);
        if ($response === false) {
            throw new RuntimeException('Could not reach SIF internal API');
        }

        return [$this->httpStatus($http_response_header ?? []), $response];
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
