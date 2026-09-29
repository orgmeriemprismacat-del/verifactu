<?php

namespace Prisma\Sif\Http;

use Prisma\Sif\Exception\SifException;

final class InternalInvoiceActorResolver
{
    public function __construct(
        private string $expectedToken,
        private array $fullReadRoles,
        private array $minimalReadRoles = []
    ) {
    }

    public function resolve(array $server): array
    {
        if ($this->expectedToken === '') {
            throw SifException::forbidden('Internal invoice API is not configured');
        }

        $authorization = trim((string) ($server['HTTP_AUTHORIZATION'] ?? ''));
        if (!str_starts_with($authorization, 'Bearer ')) {
            throw SifException::forbidden('Missing internal API credentials');
        }

        $token = substr($authorization, 7);
        if ($token === '' || !hash_equals($this->expectedToken, $token)) {
            throw SifException::forbidden('Invalid internal API credentials');
        }

        $actorId = trim((string) ($server['HTTP_X_SIF_ACTOR_ID'] ?? ''));
        $actorRole = trim((string) ($server['HTTP_X_SIF_ACTOR_ROLE'] ?? ''));
        $requestId = trim((string) ($server['HTTP_X_REQUEST_ID'] ?? ''));

        if ($actorId === '' || $actorRole === '') {
            throw SifException::forbidden('Missing trusted actor context');
        }

        $projection = $this->projectionForRole($actorRole);
        if ($projection === null) {
            throw SifException::forbidden('Actor role is not allowed to query invoices');
        }

        return [
            'actor_id' => $actorId,
            'actor_role' => $actorRole,
            'actor_type' => 'INTRANET_OPERATOR',
            'request_id' => $requestId !== '' ? $requestId : null,
            'invoice_scope' => [
                'all' => true,
                'projection' => $projection,
            ],
        ];
    }

    private function projectionForRole(string $role): ?string
    {
        if (in_array($role, $this->fullReadRoles, true)) {
            return 'FULL';
        }

        if (in_array($role, $this->minimalReadRoles, true)) {
            return 'MINIMAL';
        }

        return null;
    }
}
