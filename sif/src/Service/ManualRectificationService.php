<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\RectificationRepository;

final class ManualRectificationService
{
    public function __construct(
        private ManualPaymentInvoiceRepository $invoices,
        private RectificationRepository $rectifications,
        private ManualRectificationPayloadBuilder $builder,
        private InvoiceService $invoiceService
    ) {
    }

    public function issueByUuid(\PDO $sifDb, string $uuidFactura, array $input, ?callable $beforeCommit = null): array
    {
        $uuidFactura = trim($uuidFactura);
        if ($uuidFactura === '') {
            throw SifException::validation('Missing SIF invoice UUID');
        }

        $invoice = $this->invoices->findByUuid($sifDb, $uuidFactura);
        if ($invoice === null) {
            throw SifException::validation('SIF invoice not found for manual rectification');
        }

        return $this->issueForInvoice($sifDb, $invoice, $input, $beforeCommit);
    }

    public function issueByNumVisible(\PDO $sifDb, string $numVisible, array $input, ?callable $beforeCommit = null): array
    {
        $numVisible = trim($numVisible);
        if ($numVisible === '') {
            throw SifException::validation('Missing SIF invoice number');
        }

        $invoice = $this->invoices->findByNumVisible($sifDb, $numVisible);
        if ($invoice === null) {
            throw SifException::validation('SIF invoice not found for manual rectification');
        }

        return $this->issueForInvoice($sifDb, $invoice, $input, $beforeCommit);
    }

    private function issueForInvoice(\PDO $sifDb, array $invoice, array $input, ?callable $beforeCommit = null): array
    {
        $input = $this->normalizeInput($input);
        $payload = $this->builder->forOriginalInvoice($invoice, $input);
        $result = $this->invoiceService->issueInvoice(
            $payload,
            function (\PDO $db, array $issued) use ($invoice, $input, $beforeCommit): void {
                $lockedOriginal = $this->invoices->findByUuid($db, $invoice['UUID_FACTURA'], true);
                if ($lockedOriginal === null) {
                    throw SifException::conflict('Original SIF invoice disappeared during rectification');
                }

                $this->assertOriginalSnapshotUnchanged($invoice, $lockedOriginal);
                $this->rectifications->linkRectification(
                    $db,
                    $issued['uuid_factura'],
                    $invoice['UUID_FACTURA'],
                    $input
                );
                $this->rectifications->markOriginalRectified($db, $invoice['UUID_FACTURA']);

                if ($beforeCommit !== null) {
                    $beforeCommit($db, $issued, $lockedOriginal, $input);
                }
            }
        );
        $result['uuid_factura_rectificada'] = $invoice['UUID_FACTURA'];
        $result['num_visible_rectificada'] = $invoice['NUM_VISIBLE'];

        return $result;
    }

    private function assertOriginalSnapshotUnchanged(array $before, array $locked): void
    {
        foreach ([
            'UUID_FACTURA',
            'NUM_VISIBLE',
            'ANY_FACT',
            'TIPUS_FACTURA',
            'ESTAT_FACTURA',
            'BILLING_NOM_RAO',
            'BILLING_NIF_CIF',
            'BILLING_ADRECA',
            'BILLING_CP',
            'BILLING_POBLACIO',
            'BILLING_PROVINCIA',
            'BILLING_PAIS',
            'BILLING_EMAIL',
            'IMPORT_BASE',
            'BASE_IMPOSABLE',
            'IVA_REGIM',
            'IVA_PCT',
            'IVA_IMPORT',
            'TOTAL',
        ] as $field) {
            if (($before[$field] ?? null) !== ($locked[$field] ?? null)) {
                throw SifException::conflict(
                    'Original SIF invoice changed before rectification could be committed'
                );
            }
        }
    }

    private function normalizeInput(array $input): array
    {
        if (!array_key_exists('reason', $input) && array_key_exists('motiu', $input)) {
            $input['reason'] = $input['motiu'];
        }

        if (!array_key_exists('mode', $input) && array_key_exists('mode_rectificacio', $input)) {
            $input['mode'] = $input['mode_rectificacio'];
        }

        if (!array_key_exists('detail', $input)) {
            if (array_key_exists('details', $input)) {
                $input['detail'] = $input['details'];
            } elseif (array_key_exists('detall', $input)) {
                $input['detail'] = $input['detall'];
            }
        }

        return $input;
    }
}
