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

    public function issueByUuid(\PDO $sifDb, string $uuidFactura, array $input): array
    {
        $uuidFactura = trim($uuidFactura);
        if ($uuidFactura === '') {
            throw SifException::validation('Missing SIF invoice UUID');
        }

        $invoice = $this->invoices->findByUuid($sifDb, $uuidFactura);
        if ($invoice === null) {
            throw SifException::validation('SIF invoice not found for manual rectification');
        }

        return $this->issueForInvoice($sifDb, $invoice, $input);
    }

    public function issueByNumVisible(\PDO $sifDb, string $numVisible, array $input): array
    {
        $numVisible = trim($numVisible);
        if ($numVisible === '') {
            throw SifException::validation('Missing SIF invoice number');
        }

        $invoice = $this->invoices->findByNumVisible($sifDb, $numVisible);
        if ($invoice === null) {
            throw SifException::validation('SIF invoice not found for manual rectification');
        }

        return $this->issueForInvoice($sifDb, $invoice, $input);
    }

    private function issueForInvoice(\PDO $sifDb, array $invoice, array $input): array
    {
        $input = $this->normalizeInput($input);
        $payload = $this->builder->forOriginalInvoice($invoice, $input);
        $result = $this->invoiceService->issueInvoice($payload);
        $this->rectifications->linkRectification($sifDb, $result['uuid_factura'], $invoice['UUID_FACTURA'], $input);
        $this->rectifications->markOriginalRectified($sifDb, $invoice['UUID_FACTURA']);
        $result['uuid_factura_rectificada'] = $invoice['UUID_FACTURA'];
        $result['num_visible_rectificada'] = $invoice['NUM_VISIBLE'];

        return $result;
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
