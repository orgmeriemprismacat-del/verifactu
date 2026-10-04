<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;

final class ManualPaymentService
{
    public function __construct(
        private ManualPaymentInvoiceRepository $invoices,
        private ManualPaymentPayloadBuilder $manualPayments,
        private PaymentService $payments
    ) {
    }

    public function registerByUuid(\PDO $sifDb, string $uuidFactura, array $input): array
    {
        $uuidFactura = trim($uuidFactura);
        if ($uuidFactura === '') {
            throw SifException::validation('Missing SIF invoice UUID');
        }

        $invoice = $this->invoices->findByUuid($sifDb, $uuidFactura);
        if ($invoice === null) {
            throw SifException::validation('SIF invoice not found for manual payment');
        }

        return $this->registerForInvoice($sifDb, $invoice, $input);
    }

    public function registerByNumVisible(\PDO $sifDb, string $numVisible, array $input): array
    {
        $numVisible = trim($numVisible);
        if ($numVisible === '') {
            throw SifException::validation('Missing SIF invoice number');
        }

        $invoice = $this->invoices->findByNumVisible($sifDb, $numVisible);
        if ($invoice === null) {
            throw SifException::validation('SIF invoice not found for manual payment');
        }

        return $this->registerForInvoice($sifDb, $invoice, $input);
    }

    public function registerByLegacyFacturaRelacionada(
        \PDO $sifDb,
        int $facturaRelacionada,
        array $input
    ): array {
        $invoice = $this->invoices->findByLegacyFacturaRelacionada(
            $sifDb,
            $facturaRelacionada
        );
        if ($invoice === null) {
            throw SifException::validation(
                'SIF invoice not found for legacy related invoice'
            );
        }

        return $this->registerForInvoice($sifDb, $invoice, $input);
    }

    private function registerForInvoice(\PDO $sifDb, array $invoice, array $input): array
    {
        $input['num_visible'] = $input['num_visible'] ?? ($invoice['NUM_VISIBLE'] ?? null);
        $payload = $this->manualPayments->forExistingInvoice((string) $invoice['UUID_FACTURA'], $input);
        $result = $sifDb->inTransaction()
            ? $this->payments->registerPaymentInTransaction($sifDb, $payload)
            : $this->payments->registerPayment($payload);
        $result['uuid_factura'] = $invoice['UUID_FACTURA'];
        $result['num_visible'] = $invoice['NUM_VISIBLE'];

        return $result;
    }
}
