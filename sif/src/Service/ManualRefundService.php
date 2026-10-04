<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;

final class ManualRefundService
{
    public function __construct(
        private ManualPaymentInvoiceRepository $invoices,
        private ManualRefundPayloadBuilder $manualRefunds,
        private PaymentService $payments,
        private ?EnrollmentFundMovementRepository $funds = null
    ) {
        $this->funds ??= new EnrollmentFundMovementRepository(new UuidGenerator());
    }

    public function registerByUuid(\PDO $sifDb, string $uuidFactura, array $input): array
    {
        $uuidFactura = trim($uuidFactura);
        if ($uuidFactura === '') {
            throw SifException::validation('Missing SIF invoice UUID');
        }

        $invoice = $this->invoices->findByUuid($sifDb, $uuidFactura);
        if ($invoice === null) {
            throw SifException::validation('SIF invoice not found for manual refund');
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
            throw SifException::validation('SIF invoice not found for manual refund');
        }

        return $this->registerForInvoice($sifDb, $invoice, $input);
    }

    private function registerForInvoice(
        \PDO $sifDb,
        array $invoice,
        array $input
    ): array {
        $input['num_visible'] = $input['num_visible'] ?? ($invoice['NUM_VISIBLE'] ?? null);
        $payload = $this->manualRefunds->forExistingInvoice(
            (string) $invoice['UUID_FACTURA'],
            $input
        );

        $idInsc = (int) ($payload['source_enrollment_id'] ?? 0);
        if ($idInsc <= 0) {
            $result = $this->payments->registerPayment($payload);
            $result['uuid_factura'] = $invoice['UUID_FACTURA'];
            $result['num_visible'] = $invoice['NUM_VISIBLE'];
            $result['payment_idempotency_key'] = $payload['idempotency_key'];

            return $result;
        }

        $ownsTransaction = !$sifDb->inTransaction();
        if ($ownsTransaction) {
            $sifDb->beginTransaction();
        }

        try {
            $result = $this->payments->registerPaymentInTransaction(
                $sifDb,
                $payload
            );

            $key = (string) $payload['idempotency_key'];
            $correlationId = trim((string) ($payload['correlation_id'] ?? ''));
            if ($correlationId === '') {
                $correlationId = 'UC006|REFUND|'
                    . substr(hash('sha256', $key), 0, 32);
            }

            $this->funds->insertOrReuseRefundExit(
                $sifDb,
                [
                    'idempotency_key' => 'FUND|REFUND_EXIT|'
                        . hash('sha256', $key)
                        . '|INSC:' . $idInsc,
                    'order' => 1,
                    'uuid_payment' => (string) $result['uuid_payment'],
                    'uuid_factura' => (string) $invoice['UUID_FACTURA'],
                    'id_insc_origin' => $idInsc,
                    'amount' => $payload['amount'],
                    'currency' => 'EUR',
                    'uuid_operation' => $payload['uuid_operation'] ?? null,
                    'correlation_id' => $correlationId,
                    'notes' => $payload['notes'] ?? 'UC-006 confirmed refund exit',
                ]
            );

            if ($ownsTransaction) {
                $sifDb->commit();
            }

            $result['uuid_factura'] = $invoice['UUID_FACTURA'];
            $result['num_visible'] = $invoice['NUM_VISIBLE'];
            $result['payment_idempotency_key'] = $payload['idempotency_key'];

            return $result;
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $sifDb->inTransaction()) {
                $sifDb->rollBack();
            }

            throw $exception;
        }
    }
}
