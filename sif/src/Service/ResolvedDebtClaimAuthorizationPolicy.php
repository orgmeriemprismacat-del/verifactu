<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Contract\DebtClaimAuthorizationPolicyInterface;

final class ResolvedDebtClaimAuthorizationPolicy implements DebtClaimAuthorizationPolicyInterface
{
    public function canView(array $actor, array $invoice): bool
    {
        $scope = $actor['debt_claim_scope'] ?? null;
        if (!is_array($scope)) {
            return false;
        }

        if (($scope['all'] ?? false) === true) {
            return true;
        }

        $uuid = trim((string) ($invoice['UUID_FACTURA'] ?? ''));
        $level = $scope['invoices'][$uuid] ?? null;

        return in_array($level, ['READ', 'WRITE'], true);
    }

    public function canManage(array $actor, array $invoice, string $action): bool
    {
        $scope = $actor['debt_claim_scope'] ?? null;
        if (!is_array($scope)) {
            return false;
        }

        if (($scope['all'] ?? false) === true) {
            return ($scope['write'] ?? false) === true;
        }

        $uuid = trim((string) ($invoice['UUID_FACTURA'] ?? ''));

        return ($scope['invoices'][$uuid] ?? null) === 'WRITE';
    }
}
