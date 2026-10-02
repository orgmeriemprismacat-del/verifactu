<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Contract\InvoiceVisibilityPolicyInterface;

final class ResolvedInvoiceVisibilityPolicy implements InvoiceVisibilityPolicyInterface
{
    public function canView(array $actor, array $invoice, array $relations): bool
    {
        $scope = $this->scope($actor);
        $uuid = (string) ($invoice['UUID_FACTURA'] ?? '');

        if ($uuid === '') {
            return false;
        }

        if (array_key_exists($uuid, $scope['invoices'] ?? [])) {
            return in_array(
                strtoupper((string) $scope['invoices'][$uuid]),
                ['FULL', 'MINIMAL'],
                true
            );
        }

        if (($scope['all'] ?? false) === true) {
            return in_array(
                strtoupper((string) ($scope['projection'] ?? '')),
                ['FULL', 'MINIMAL'],
                true
            );
        }

        return false;
    }

    public function project(array $actor, array $view): array
    {
        $scope = $this->scope($actor);
        $invoice = $view['invoice'] ?? [];
        $uuid = (string) ($invoice['uuid_factura'] ?? '');

        if ($uuid === '') {
            return [];
        }

        $projection = null;
        if (array_key_exists($uuid, $scope['invoices'] ?? [])) {
            $projection = strtoupper((string) $scope['invoices'][$uuid]);
        } elseif (($scope['all'] ?? false) === true) {
            $projection = strtoupper((string) ($scope['projection'] ?? 'FULL'));
        }

        if ($projection === 'FULL') {
            return $view;
        }

        if ($projection !== 'MINIMAL') {
            return [];
        }

        return [
            'ok' => true,
            'invoice' => [
                'uuid_factura' => $invoice['uuid_factura'] ?? null,
                'num_visible' => $invoice['num_visible'] ?? null,
                'tipus_factura' => $invoice['tipus_factura'] ?? null,
                'data_emissio' => $invoice['data_emissio'] ?? null,
                'estat_factura' => $invoice['estat_factura'] ?? null,
                'estat_cobrament' => $invoice['estat_cobrament'] ?? null,
                'estat_aeat' => $invoice['estat_aeat'] ?? null,
            ],
            'lines' => [],
            'relations' => [],
            'rectifications' => [],
            'payments' => [],
            'fiscal_record' => null,
            'documents' => [],
            'document_jobs' => [],
        ];
    }

    private function scope(array $actor): array
    {
        $scope = $actor['invoice_scope'] ?? null;
        if (!is_array($scope)) {
            return [];
        }

        if (isset($scope['invoices']) && !is_array($scope['invoices'])) {
            return [];
        }

        return $scope;
    }
}
