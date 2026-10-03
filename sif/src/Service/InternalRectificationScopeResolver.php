<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class InternalRectificationScopeResolver
{
    private array $writeRoles;

    public function __construct(array $writeRoles)
    {
        $this->writeRoles = $this->normalizeRoles($writeRoles);

        if ($this->writeRoles === []) {
            throw new \RuntimeException('Rectification internal write roles are not configured');
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
        if (array_intersect($roles, $this->writeRoles) === []) {
            throw SifException::forbidden('Rectification write role is not authorized');
        }

        $resolved = $authenticatedActor;
        $resolved['actor_id'] = $actorId;
        $resolved['roles'] = $roles;
        $resolved['rectification_scope'] = [
            'preview' => true,
            'issue' => true,
        ];
        $resolved['rectification_scope_source'] = 'INTERNAL_ROLE';

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
