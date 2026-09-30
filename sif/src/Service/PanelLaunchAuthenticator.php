<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\InternalApiRequestRepository;

final class PanelLaunchAuthenticator
{
    public function __construct(
        private \PDO $db,
        private InternalApiRequestRepository $requests,
        private string $keyId,
        private string $secret,
        private int $maxClockSkewSeconds = 120
    ) {
        $this->keyId = trim($this->keyId);
        $this->secret = trim($this->secret);
        if ($this->keyId === '' || $this->secret === '') {
            throw new \RuntimeException('SIF panel launch authentication is not configured');
        }
        $this->maxClockSkewSeconds = max(30, min(300, $this->maxClockSkewSeconds));
    }

    public function authenticate(array $input, string $path): array
    {
        $receivedKeyId = trim((string) ($input['key_id'] ?? ''));
        $timestamp = trim((string) ($input['timestamp'] ?? ''));
        $requestId = strtolower(trim((string) ($input['request_id'] ?? '')));
        $actorId = trim((string) ($input['actor_id'] ?? ''));
        $roles = $this->normalizeRoles((string) ($input['roles'] ?? ''));
        $signature = strtolower(trim((string) ($input['signature'] ?? '')));

        if (!hash_equals($this->keyId, $receivedKeyId)) {
            throw SifException::unauthorized('Invalid SIF panel launch key');
        }
        if (!ctype_digit($timestamp)) {
            throw SifException::unauthorized('Invalid SIF panel launch timestamp');
        }

        $requestedAtUnix = (int) $timestamp;
        if (abs(time() - $requestedAtUnix) > $this->maxClockSkewSeconds) {
            throw SifException::unauthorized('Expired SIF panel launch');
        }
        if (preg_match('/^[0-9a-f-]{36}$/D', $requestId) !== 1) {
            throw SifException::unauthorized('Invalid SIF panel launch request id');
        }
        if ($actorId === '' || mb_strlen($actorId, 'UTF-8') > 120) {
            throw SifException::unauthorized('Invalid SIF panel actor');
        }
        if ($roles === []) {
            throw SifException::unauthorized('SIF panel actor roles are required');
        }
        if (preg_match('/^[0-9a-f]{64}$/D', $signature) !== 1) {
            throw SifException::unauthorized('Invalid SIF panel launch signature');
        }

        $path = trim($path);
        $canonicalRoles = implode(',', $roles);
        $canonical = implode("\n", [
            'SIF_PANEL_LAUNCH',
            'POST',
            $path,
            $timestamp,
            $requestId,
            $actorId,
            $canonicalRoles,
        ]);
        $expected = hash_hmac('sha256', $canonical, $this->secret);
        if (!hash_equals($expected, $signature)) {
            throw SifException::unauthorized('Invalid SIF panel launch signature');
        }

        $requestedAt = (new \DateTimeImmutable('@' . $requestedAtUnix))
            ->setTimezone(new \DateTimeZone('Europe/Madrid'));

        $this->requests->claim(
            $this->db,
            $requestId,
            $this->keyId,
            $actorId,
            $roles,
            'POST',
            $path,
            hash('sha256', $canonical),
            $requestedAt
        );

        return [
            'actor_id' => $actorId,
            'roles' => $roles,
            'request_id' => $requestId,
            'source_channel' => 'SIF_PANEL',
        ];
    }

    private function normalizeRoles(string $rolesHeader): array
    {
        $roles = [];
        foreach (explode(',', $rolesHeader) as $role) {
            $value = strtoupper(trim($role));
            if ($value !== '') {
                $roles[$value] = true;
            }
        }
        $result = array_keys($roles);
        sort($result, SORT_STRING);
        return $result;
    }
}
