<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class InternalInvoiceScopeResolver
{
    public function __construct(
        private array $fullReadRoles,
        private array $minimalReadRoles = []
    ) {
        $this->fullReadRoles = $this->normalizeRoles($this->fullReadRoles);
        $this->minimalReadRoles = $this->normalizeRoles($this->minimalReadRoles);
    }

    public function resolve(array $authenticatedActor): array
    {
        $actorId = trim((string) ($authenticatedActor['actor_id'] ?? ''));
        if ($actorId === '') {
            throw SifException::forbidden('Authenticated actor is required');
        }

        $roles = $authenticatedActor['roles'] ?? [];
        if (!is_array($roles)) {
            throw SifException::forbidden('Authenticated actor roles are required');
        }

        $roles = $this->normalizeRoles($roles);
        $projection = $this->projectionFor($roles);
        if ($projection === null) {
            throw SifException::forbidden('Invoice query role is not authorized');
        }

        $resolved = $authenticatedActor;
        $resolved['actor_id'] = $actorId;
        $resolved['roles'] = $roles;
        $resolved['invoice_scope'] = [
            'all' => true,
            'projection' => $projection,
        ];
        $resolved['invoice_scope_source'] = 'INTERNAL_ROLE';

        return $resolved;
    }

    private function projectionFor(array $roles): ?string
    {
        if (array_intersect($roles, $this->fullReadRoles) !== []) {
            return 'FULL';
        }

        if (array_intersect($roles, $this->minimalReadRoles) !== []) {
            return 'MINIMAL';
        }

        return null;
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
}
