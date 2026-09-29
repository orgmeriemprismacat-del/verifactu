<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class CourseChangePreviewGateway
{
    private array $allowedRoles;

    public function __construct(
        private CourseChangePreviewService $preview,
        array $allowedRoles
    ) {
        $this->allowedRoles = $this->normalizeRoles($allowedRoles);
    }

    public function preview(array $authenticatedActor, array $payload): array
    {
        $actorId = trim((string) ($authenticatedActor['actor_id'] ?? ''));
        $roles = $authenticatedActor['roles'] ?? [];

        if ($actorId === '' || !is_array($roles)) {
            throw SifException::forbidden('Authenticated actor is required');
        }

        $roles = $this->normalizeRoles($roles);
        if ($this->allowedRoles === [] || array_intersect($roles, $this->allowedRoles) === []) {
            throw SifException::forbidden('Course change preview role is not authorized');
        }

        $result = $this->preview->preview($payload);
        $result['actor'] = [
            'actor_id' => $actorId,
            'roles' => $roles,
        ];

        return $result;
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
