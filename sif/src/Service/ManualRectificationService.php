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
        $payload = $this->builder->forOriginalInvoice($invoice, $input);
        $result = $this->invoiceService->issueInvoice($payload);
        $this->rectifications->linkRectification($sifDb, $result['uuid_factura'], $invoice['UUID_FACTURA'], $input);
        $this->rectifications->markOriginalRectified($sifDb, $invoice['UUID_FACTURA']);
        $result['uuid_factura_rectificada'] = $invoice['UUID_FACTURA'];
        $result['num_visible_rectificada'] = $invoice['NUM_VISIBLE'];

        return $result;
    }
}
