<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;

final class ClaimPaymentService
{
    public function __construct(
        private ManualPaymentInvoiceRepository $invoices,
        private ClaimPaymentPayloadBuilder $claimPayments,
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
            throw SifException::validation('SIF invoice not found for claim payment');
        }

        return $this->registerForInvoice($invoice, $input);
    }

    public function registerByNumVisible(\PDO $sifDb, string $numVisible, array $input): array
    {
        $numVisible = trim($numVisible);
        if ($numVisible === '') {
            throw SifException::validation('Missing SIF invoice number');
        }

        $invoice = $this->invoices->findByNumVisible($sifDb, $numVisible);
        if ($invoice === null) {
            throw SifException::validation('SIF invoice not found for claim payment');
        }

        return $this->registerForInvoice($invoice, $input);
    }

    private function registerForInvoice(array $invoice, array $input): array
    {
        $input['num_visible'] = $input['num_visible'] ?? ($invoice['NUM_VISIBLE'] ?? null);
        $payload = $this->claimPayments->forExistingInvoice((string) $invoice['UUID_FACTURA'], $input);
        $result = $this->payments->registerPayment($payload);
        $result['uuid_factura'] = $invoice['UUID_FACTURA'];
        $result['num_visible'] = $invoice['NUM_VISIBLE'];

        return $result;
    }
}
