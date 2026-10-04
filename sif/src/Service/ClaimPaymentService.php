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

        return $this->registerForInvoice($invoice, $input, false, $sifDb);
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

        return $this->registerForInvoice($invoice, $input, false, $sifDb);
    }

    public function registerByUuidInTransaction(\PDO $sifDb, string $uuidFactura, array $input): array
    {
        if (!$sifDb->inTransaction()) {
            throw new \LogicException('Claim payment transaction is not active');
        }

        $uuidFactura = trim($uuidFactura);
        if ($uuidFactura === '') {
            throw SifException::validation('Missing SIF invoice UUID');
        }

        $invoice = $this->invoices->findByUuid($sifDb, $uuidFactura, true);
        if ($invoice === null) {
            throw SifException::validation('SIF invoice not found for claim payment');
        }

        return $this->registerForInvoice($invoice, $input, true, $sifDb);
    }

    public function registerByNumVisibleInTransaction(\PDO $sifDb, string $numVisible, array $input): array
    {
        if (!$sifDb->inTransaction()) {
            throw new \LogicException('Claim payment transaction is not active');
        }

        $numVisible = trim($numVisible);
        if ($numVisible === '') {
            throw SifException::validation('Missing SIF invoice number');
        }

        $invoice = $this->invoices->findByNumVisible($sifDb, $numVisible, true);
        if ($invoice === null) {
            throw SifException::validation('SIF invoice not found for claim payment');
        }

        return $this->registerForInvoice($invoice, $input, true, $sifDb);
    }

    private function registerForInvoice(
        array $invoice,
        array $input,
        bool $withinTransaction = false,
        ?\PDO $sifDb = null
    ): array {
        $input['num_visible'] = $input['num_visible'] ?? ($invoice['NUM_VISIBLE'] ?? null);
        $payload = $this->claimPayments->forExistingInvoice((string) $invoice['UUID_FACTURA'], $input);
        $result = $withinTransaction
            ? $this->payments->registerPaymentInTransaction(
                $sifDb ?? throw new \LogicException('Missing claim payment transaction'),
                $payload
            )
            : $this->payments->registerPayment($payload);
        $result['uuid_factura'] = $invoice['UUID_FACTURA'];
        $result['num_visible'] = $invoice['NUM_VISIBLE'];
        $result['payment_idempotency_key'] = $payload['idempotency_key'];

        return $result;
    }
}
