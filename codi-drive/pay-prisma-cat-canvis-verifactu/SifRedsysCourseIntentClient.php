<?php

final class SifRedsysCourseIntentClient
{
    public function create(int $idPag, float $requestedAmount, string $terminal = '1'): array
    {
        if ($idPag < 1 || $requestedAmount <= 0) {
            throw new RuntimeException('Invalid Redsys course intent input');
        }

        $baseUrl = rtrim((string) getenv('SIF_INTERNAL_API_BASE_URL'), '/');
        $keyId = trim((string) getenv('SIF_INTERNAL_API_KEY_ID'));
        $secret = trim((string) getenv('SIF_INTERNAL_API_SECRET'));
        $path = '/api/redsys/course-intent.php';

        if ($baseUrl === '' || $keyId === '' || $secret === '') {
            throw new RuntimeException('SIF internal API is not configured');
        }

        $body = json_encode([
            'idpag' => $idPag,
            'requested_amount' => number_format($requestedAmount, 2, '.', ''),
            'terminal' => $terminal,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new RuntimeException('Could not encode SIF course intent request');
        }

        $timestamp = (string) time();
        $requestId = $this->uuidV4();
        $actorId = 'pay-prisma-cat';
        $roles = 'PAYMENT_CHANNEL';
        $canonical = implode("\n", [
            'POST',
            $path,
            $timestamp,
            strtolower($requestId),
            $actorId,
            $roles,
            hash('sha256', $body),
        ]);
        $signature = hash_hmac('sha256', $canonical, $secret);

        $ch = curl_init($baseUrl . $path);
        if ($ch === false) {
            throw new RuntimeException('Could not initialize SIF request');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-SIF-Key-Id: ' . $keyId,
                'X-SIF-Timestamp: ' . $timestamp,
                'X-SIF-Request-Id: ' . strtolower($requestId),
                'X-SIF-Actor-Id: ' . $actorId,
                'X-SIF-Actor-Roles: ' . $roles,
                'X-SIF-Signature: ' . $signature,
            ],
        ]);

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false || $error !== '') {
            throw new RuntimeException('SIF course intent request failed: ' . $error);
        }

        $response = json_decode($raw, true);
        if ($status < 200 || $status >= 300 || !is_array($response) || ($response['ok'] ?? false) !== true) {
            $message = is_array($response) ? (string) ($response['error'] ?? 'Unknown SIF error') : 'Invalid SIF response';
            throw new RuntimeException('SIF course intent rejected: ' . $message);
        }

        $intent = $response['intent'] ?? null;
        if (!is_array($intent)
            || trim((string) ($intent['ds_order'] ?? '')) === ''
            || !is_numeric($intent['amount'] ?? null)
        ) {
            throw new RuntimeException('SIF course intent response is incomplete');
        }

        return $intent;
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return substr($hex, 0, 8) . '-'
            . substr($hex, 8, 4) . '-'
            . substr($hex, 12, 4) . '-'
            . substr($hex, 16, 4) . '-'
            . substr($hex, 20, 12);
    }
}
