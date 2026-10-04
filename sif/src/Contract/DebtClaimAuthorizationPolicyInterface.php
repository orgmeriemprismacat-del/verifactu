<?php

namespace Prisma\Sif\Contract;

interface DebtClaimAuthorizationPolicyInterface
{
    public function canView(array $actor, array $invoice): bool;

    public function canManage(array $actor, array $invoice, string $action): bool;
}
