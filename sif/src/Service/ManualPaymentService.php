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
        return $this->registerByUuidInternal($sifDb, $uuidFactura, $input, false);
    }

    public function registerByUuidInTransaction(\PDO $sifDb, string $uuidFactura, array $input): array
    {
        return $this->registerByUuidInternal($sifDb, $uuidFactura, $input, true);
    }

    private function registerByUuidInternal(\PDO $sifDb, string $uuidFactura, array $input, bool $inTransaction): array
    {
        $uuidFactura = trim($uuidFactura);
        if ($uuidFactura === '') {
            throw SifException::validation('Missing SIF invoice UUID');
        }

        $invoice = $this->invoices->findByUuid($sifDb, $uuidFactura);
        if ($invoice === null) {
            throw SifException::validation('SIF invoice not found for manual payment');
        }

        return $this->registerForInvoice($sifDb, $invoice, $input, $inTransaction);
    }

    public function registerByNumVisible(\PDO $sifDb, string $numVisible, array $input): array
    {
        return $this->registerByNumVisibleInternal($sifDb, $numVisible, $input, false);
    }

    public function registerByNumVisibleInTransaction(\PDO $sifDb, string $numVisible, array $input): array
    {
        return $this->registerByNumVisibleInternal($sifDb, $numVisible, $input, true);
    }

    private function registerByNumVisibleInternal(\PDO $sifDb, string $numVisible, array $input, bool $inTransaction): array
    {
        $numVisible = trim($numVisible);
        if ($numVisible === '') {
            throw SifException::validation('Missing SIF invoice number');
        }

        $invoice = $this->invoices->findByNumVisible($sifDb, $numVisible);
        if ($invoice === null) {
            throw SifException::validation('SIF invoice not found for manual payment');
        }

        return $this->registerForInvoice($sifDb, $invoice, $input, $inTransaction);
    }

    private function registerForInvoice(\PDO $sifDb, array $invoice, array $input, bool $inTransaction): array
    {
        $input['num_visible'] = $input['num_visible'] ?? ($invoice['NUM_VISIBLE'] ?? null);
        $payload = $this->manualPayments->forExistingInvoice((string) $invoice['UUID_FACTURA'], $input);
        $result = $inTransaction
            ? $this->payments->registerPaymentInTransaction($sifDb, $payload)
            : $this->payments->registerPayment($payload);
        $result['payment_idempotency_key'] = $payload['idempotency_key'];
        $result['uuid_factura'] = $invoice['UUID_FACTURA'];
        $result['num_visible'] = $invoice['NUM_VISIBLE'];

        return $result;
    }
}
