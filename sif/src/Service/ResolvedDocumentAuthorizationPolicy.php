<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Contract\DocumentAuthorizationPolicyInterface;

final class ResolvedDocumentAuthorizationPolicy implements DocumentAuthorizationPolicyInterface
{
    public function canDownload(array $actor, array $invoice, array $relations, array $document): bool
    {
        $scope = $actor['invoice_scope'] ?? null;
        if (!is_array($scope)) {
            return false;
        }

        $uuid = (string) ($invoice['UUID_FACTURA'] ?? '');
        if ($uuid === '') {
            return false;
        }

        if (isset($scope['invoices']) && is_array($scope['invoices'])
            && array_key_exists($uuid, $scope['invoices'])) {
            return strtoupper((string) $scope['invoices'][$uuid]) === 'FULL';
        }

        if (($scope['all'] ?? false) === true) {
            return strtoupper((string) ($scope['projection'] ?? '')) === 'FULL';
        }

        return false;
    }
}
