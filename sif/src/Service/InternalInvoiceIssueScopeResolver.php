<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class InternalInvoiceIssueScopeResolver
{
    private array $writeRoles;

    public function __construct(array $writeRoles)
    {
        $this->writeRoles = $this->normalizeRoles($writeRoles);
        if ($this->writeRoles === []) {
            throw new \RuntimeException('Invoice issue internal write roles are not configured');
        }
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
        $matchedRoles = array_values(array_intersect($roles, $this->writeRoles));
        if ($matchedRoles === []) {
            throw SifException::forbidden('Invoice issue role is not authorized');
        }

        $resolved = $authenticatedActor;
        $resolved['actor_id'] = $actorId;
        $resolved['roles'] = $roles;
        $resolved['invoice_issue_scope'] = ['issue' => true];
        $resolved['invoice_issue_role'] = $matchedRoles[0];
        $resolved['invoice_issue_scope_source'] = 'INTERNAL_ROLE';

        return $resolved;
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
