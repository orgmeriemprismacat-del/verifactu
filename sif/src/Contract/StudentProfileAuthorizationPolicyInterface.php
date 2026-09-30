<?php

namespace Prisma\Sif\Contract;

interface StudentProfileAuthorizationPolicyInterface
{
    public function canView(array $actor, array $profile): bool;

    public function canChange(array $actor, array $profile, array $changes): bool;
}
