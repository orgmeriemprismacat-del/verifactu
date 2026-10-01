<?php

final class SifInternalUsocClient
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
        $this->url = trim((string) ($url ?? getenv('SIF_INTERNAL_USOC_URL') ?: ''));
        $this->signedPath = trim((string) (
            $signedPath ?? getenv('SIF_INTERNAL_USOC_SIGNED_PATH') ?: '/api/usoc/manage.php'
        ));
        $this->keyId = trim((string) ($keyId ?? getenv('SIF_INTERNAL_API_KEY_ID') ?: ''));
        $this->secret = trim((string) ($secret ?? getenv('SIF_INTERNAL_API_SECRET') ?: ''));
        $this->timeout = max(1, min(30, $timeout));

        if ($this->url === '' || $this->keyId === '' || $this->secret === '') {
            throw new RuntimeException('SIF USOC internal API is not configured');
        }

        $this->assertSecureUrl($this->url);
    }

    public function viewCase(string $actorId, array $roles, int $idInsc, int $idpag): array
    {
        return $this->request($actorId, $roles, [
            'action' => 'view',
            'id_insc' => $idInsc,
            'idpag' => $idpag,
        ]);
    }

    public function issueEntityInvoice(string $actorId, array $roles, array $input): array
    {
        return $this->request($actorId, $roles, [
            'action' => 'issue_entity_invoice',
            'input' => $input,
        ]);
    }

    public function registerEntityPayment(
        string $actorId,
        array $roles,
        string $uuidEntityInvoice,
        array $payment
    ): array {
        return $this->request($actorId, $roles, [
            'action' => 'register_entity_payment',
            'uuid_entity_invoice' => trim($uuidEntityInvoice),
            'payment' => $payment,
        ]);
    }

    public function lifecycleGuard(
        string $actorId,
        array $roles,
        int $idInsc,
        int $idpag,
        string $operation
    ): array {
        return $this->request($actorId, $roles, [
            'action' => 'lifecycle_guard',
            'id_insc' => $idInsc,
            'idpag' => $idpag,
            'operation' => trim($operation),
        ]);
    }

    public function lifecyclePlan(
        string $actorId,
        array $roles,
        int $idInsc,
        int $idpag,
        string $operation
    ): array {
        return $this->request($actorId, $roles, [
            'action' => 'lifecycle_plan',
            'id_insc' => $idInsc,
            'idpag' => $idpag,
            'operation' => trim($operation),
        ]);
    }

    public function beginValidationDecision(
        string $actorId,
        array $roles,
        string $requestId,
        int $idInsc,
        int $desiredValidDesc
    ): array {
        return $this->request($actorId, $roles, [
            'action' => 'begin_validation_decision',
            'request_id' => trim($requestId),
            'id_insc' => $idInsc,
            'desired_valid_desc' => $desiredValidDesc,
        ]);
    }

    public function completeValidationDecision(
        string $actorId,
        array $roles,
        string $requestId
    ): array {
        return $this->request($actorId, $roles, [
            'action' => 'complete_validation_decision',
            'request_id' => trim($requestId),
        ]);
    }

    public function executeCancellation(
        string $actorId,
        array $roles,
        string $requestId,
        int $idInsc,
        int $idpag,
        array $input
    ): array {
        return $this->request($actorId, $roles, [
            'action' => 'execute_cancellation',
            'request_id' => trim($requestId),
            'id_insc' => $idInsc,
            'idpag' => $idpag,
            'input' => $input,
        ]);
    }

    public function reconcile(string $actorId, array $roles, int $idInsc, int $idpag): array
    {
        return $this->request($actorId, $roles, [
            'action' => 'reconcile',
            'id_insc' => $idInsc,
            'idpag' => $idpag,
        ]);
    }

    public function request(string $actorId, array $roles, array $payload): array
    {
        $actorId = trim($actorId);
        if ($actorId === '') {
            throw new InvalidArgumentException('Missing SIF USOC actor id');
        }

        $roles = $this->normalizeRoles($roles);
        if ($roles === []) {
            throw new InvalidArgumentException('Missing SIF USOC actor roles');
        }

        $body = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );
        if ($body === false) {
            throw new RuntimeException('Could not encode SIF USOC request payload');
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
            throw new RuntimeException('Invalid SIF USOC response');
        }

        $decoded['_http_status'] = $status;
        return $decoded;
    }

    private function send(array $headers, string $body): array
    {
        if (function_exists('curl_init')) {
            $curl = curl_init($this->url);
            if ($curl === false) {
                throw new RuntimeException('Could not initialize SIF USOC HTTP client');
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
                    $error !== '' ? 'Could not reach SIF USOC API: ' . $error : 'Could not reach SIF USOC API'
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
            throw new RuntimeException('Could not reach SIF USOC API');
        }

        return [$this->httpStatus($http_response_header ?? []), $response];
    }

    private function assertSecureUrl(string $url): void
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            throw new RuntimeException('Invalid SIF USOC API URL');
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

        throw new RuntimeException('SIF USOC internal API requires HTTPS');
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
        $result = array_keys($normalized);
        sort($result, SORT_STRING);
        return $result;
    }

    private function httpStatus(array $headers): int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/i', (string) $header, $matches) === 1) {
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
