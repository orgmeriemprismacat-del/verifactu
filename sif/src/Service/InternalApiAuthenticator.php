<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\InternalApiRequestRepository;

final class InternalApiAuthenticator
{
    public function __construct(
        private \PDO $db,
        private InternalApiRequestRepository $requests,
        private string $keyId,
        private string $secret,
        private int $maxClockSkewSeconds = 300
    ) {
        $this->keyId = trim($this->keyId);
        $this->secret = trim($this->secret);

        if ($this->keyId === '' || $this->secret === '') {
            throw new \RuntimeException('Internal API authentication is not configured');
        }

        $this->maxClockSkewSeconds = max(30, min(900, $this->maxClockSkewSeconds));
    }

    public function authenticate(
        array $server,
        string $rawBody,
        string $method,
        string $path
    ): array {
        $receivedKeyId = trim((string) ($server['HTTP_X_SIF_KEY_ID'] ?? ''));
        $timestamp = trim((string) ($server['HTTP_X_SIF_TIMESTAMP'] ?? ''));
        $requestId = trim((string) ($server['HTTP_X_SIF_REQUEST_ID'] ?? ''));
        $actorId = trim((string) ($server['HTTP_X_SIF_ACTOR_ID'] ?? ''));
        $rolesHeader = trim((string) ($server['HTTP_X_SIF_ACTOR_ROLES'] ?? ''));
        $receivedSignature = strtolower(trim((string) ($server['HTTP_X_SIF_SIGNATURE'] ?? '')));

        if (!hash_equals($this->keyId, $receivedKeyId)) {
            throw SifException::unauthorized('Invalid internal API key');
        }

        if (!ctype_digit($timestamp)) {
            throw SifException::unauthorized('Invalid internal API timestamp');
        }

        $requestedAtUnix = (int) $timestamp;
        if (abs(time() - $requestedAtUnix) > $this->maxClockSkewSeconds) {
            throw SifException::unauthorized('Expired internal API request');
        }

        if (preg_match('/^[0-9a-fA-F-]{36}$/D', $requestId) !== 1) {
            throw SifException::unauthorized('Invalid internal API request id');
        }

        if ($actorId === '' || mb_strlen($actorId, 'UTF-8') > 120) {
            throw SifException::unauthorized('Invalid internal API actor');
        }

        $roles = $this->normalizeRoles($rolesHeader);
        if ($roles === []) {
            throw SifException::unauthorized('Internal API actor roles are required');
        }

        if (preg_match('/^[0-9a-f]{64}$/D', $receivedSignature) !== 1) {
            throw SifException::unauthorized('Invalid internal API signature');
        }

        $method = strtoupper(trim($method));
        $path = trim($path);
        $bodyHash = hash('sha256', $rawBody);
        $canonicalRoles = implode(',', $roles);
        if (strlen($canonicalRoles) > 500) {
            throw SifException::unauthorized('Internal API actor roles are too long');
        }

        $canonical = implode("\n", [
            $method,
            $path,
            $timestamp,
            strtolower($requestId),
            $actorId,
            $canonicalRoles,
            $bodyHash,
        ]);

        $expected = hash_hmac('sha256', $canonical, $this->secret);
        if (!hash_equals($expected, $receivedSignature)) {
            throw SifException::unauthorized('Invalid internal API signature');
        }

        $requestedAt = (new \DateTimeImmutable('@' . $requestedAtUnix))
            ->setTimezone(new \DateTimeZone('Europe/Madrid'));

        $this->requests->claim(
            $this->db,
            strtolower($requestId),
            $this->keyId,
            $actorId,
            $roles,
            $method,
            $path,
            $bodyHash,
            $requestedAt
        );

        return [
            'actor_id' => $actorId,
            'roles' => $roles,
            'request_id' => strtolower($requestId),
            'source_channel' => 'INTERNAL_API',
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

        return array_keys($roles);
    }
}
