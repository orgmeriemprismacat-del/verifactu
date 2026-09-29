<?php

namespace Prisma\Sif\Http;

use Prisma\Sif\Exception\SifException;

final class InternalRequestAuthenticator
{
    public function __construct(
        private string $clientId,
        private string $secret,
        private int $maxClockSkewSeconds = 300
    ) {
    }

    public function authenticate(array $server): array
    {
        if ($this->clientId === '' || $this->secret === '') {
            throw SifException::forbidden('Internal API authentication is not configured');
        }

        $client = trim((string) ($server['HTTP_X_SIF_CLIENT'] ?? ''));
        $timestamp = trim((string) ($server['HTTP_X_SIF_TIMESTAMP'] ?? ''));
        $actorId = trim((string) ($server['HTTP_X_SIF_ACTOR_ID'] ?? ''));
        $actorRoles = trim((string) ($server['HTTP_X_SIF_ACTOR_ROLES'] ?? ''));
        $signature = trim((string) ($server['HTTP_X_SIF_SIGNATURE'] ?? ''));
        $method = strtoupper(trim((string) ($server['REQUEST_METHOD'] ?? 'GET')));
        $requestUri = (string) ($server['REQUEST_URI'] ?? '');

        if ($client === '' || $timestamp === '' || $actorId === '' || $signature === '' || $requestUri === '') {
            throw SifException::forbidden('Missing internal API authentication headers');
        }

        if (!hash_equals($this->clientId, $client)) {
            throw SifException::forbidden('Unknown internal API client');
        }

        if (!ctype_digit($timestamp)) {
            throw SifException::forbidden('Invalid internal API timestamp');
        }

        $now = time();
        if (abs($now - (int) $timestamp) > $this->maxClockSkewSeconds) {
            throw SifException::forbidden('Expired internal API signature');
        }

        $canonical = self::canonical(
            $client,
            $timestamp,
            $method,
            $requestUri,
            $actorId,
            $actorRoles
        );
        $expected = hash_hmac('sha256', $canonical, $this->secret);

        if (!hash_equals($expected, strtolower($signature))) {
            throw SifException::forbidden('Invalid internal API signature');
        }

        return [
            'actor_id' => $actorId,
            'actor_type' => 'INTRANET_STAFF',
            'roles' => self::roles($actorRoles),
            'authenticated_via' => 'INTERNAL_HMAC',
        ];
    }

    public static function signature(
        string $secret,
        string $client,
        string $timestamp,
        string $method,
        string $requestUri,
        string $actorId,
        string $actorRoles
    ): string {
        return hash_hmac(
            'sha256',
            self::canonical($client, $timestamp, $method, $requestUri, $actorId, $actorRoles),
            $secret
        );
    }

    private static function canonical(
        string $client,
        string $timestamp,
        string $method,
        string $requestUri,
        string $actorId,
        string $actorRoles
    ): string {
        return implode("\n", [
            trim($client),
            trim($timestamp),
            strtoupper(trim($method)),
            $requestUri,
            trim($actorId),
            trim($actorRoles),
        ]);
    }

    private static function roles(string $roles): array
    {
        $items = preg_split('/[|,;]/', $roles) ?: [];
        $result = [];

        foreach ($items as $role) {
            $role = trim($role);
            if ($role !== '') {
                $result[$role] = true;
            }
        }

        return array_keys($result);
    }
}
