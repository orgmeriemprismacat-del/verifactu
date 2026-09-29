<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Contract\StudentProfileAuthorizationPolicyInterface;

final class ResolvedStudentProfileAuthorizationPolicy implements StudentProfileAuthorizationPolicyInterface
{
    public function canView(array $actor, array $profile): bool
    {
        $scope = $actor['student_scope'] ?? null;
        if (!is_array($scope)) {
            return false;
        }

        if (($scope['all'] ?? false) === true) {
            return true;
        }

        $id = (string) ($profile['ID'] ?? '');
        $level = $scope['enrollments'][$id] ?? null;

        return in_array($level, ['READ', 'WRITE'], true);
    }

    public function canChange(array $actor, array $profile, array $changes): bool
    {
        $scope = $actor['student_scope'] ?? null;
        if (!is_array($scope)) {
            return false;
        }

        if (($scope['all'] ?? false) === true) {
            return ($scope['write'] ?? false) === true;
        }

        $id = (string) ($profile['ID'] ?? '');
        return ($scope['enrollments'][$id] ?? null) === 'WRITE';
    }
}
