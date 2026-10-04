<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\ClaimPaymentExternalReceiptRepository;

final class ClaimPaymentReceiptResolver
{
    private const TYPES = ['BANK_REFERENCE', 'DS_ORDER', 'PROVIDER_REF'];

    public function __construct(
        private ClaimPaymentExternalReceiptRepository $receipts
    ) {
    }

    public function resolveExisting(
        \PDO $db,
        string $type,
        string $externalReceiptId,
        string $uuidFactura,
        string $expectedAmount,
        int $expectedIdpag
    ): ?array {
        $type = $this->type($type);
        $externalReceiptId = trim($externalReceiptId);
        if ($externalReceiptId === '') {
            throw SifException::validation('Missing external receipt id');
        }

        $existing = $this->receipts->findUnique(
            $db,
            $type,
            $externalReceiptId,
            true
        );
        if ($existing === null) {
            return null;
        }

        if (strtoupper(trim((string) ($existing['ESTAT'] ?? ''))) !== 'CONFIRMED') {
            throw SifException::conflict(
                'External receipt payment is not confirmed'
            );
        }

        if (!in_array(
            strtoupper(trim((string) ($existing['TIPUS_MOVIMENT'] ?? ''))),
            ['CHARGE', 'COMPENSATION'],
            true
        )) {
            throw SifException::conflict(
                'External receipt is not a positive payment movement'
            );
        }

        $allocatedAmount = $this->receipts->allocatedAmountForInvoice(
            $db,
            (string) $existing['UUID_PAYMENT'],
            $uuidFactura
        );

        if ($expectedIdpag <= 0) {
            throw SifException::validation('Invalid claim payment IDPAG');
        }
        if (
            !isset($existing['IDPAG'])
            || (int) $existing['IDPAG'] !== $expectedIdpag
        ) {
            throw SifException::conflict(
                'External receipt IDPAG does not match claim inscription'
            );
        }

        if (
            $this->money($allocatedAmount)
            !== $this->money($expectedAmount)
        ) {
            throw SifException::conflict(
                'External receipt allocation does not match claim amount'
            );
        }

        return [
            'ok' => true,
            'idempotency_reused' => true,
            'reconciled_existing' => true,
            'uuid_payment' => (string) $existing['UUID_PAYMENT'],
            'payment_idempotency_key' => (string) $existing['IDEMPOTENCY_KEY'],
            'uuid_factura' => $uuidFactura,
        ];
    }

    private function money(string $amount): string
    {
        $normalized = str_replace(',', '.', trim($amount));
        if (!is_numeric($normalized)) {
            throw SifException::validation('Invalid external receipt amount');
        }

        return number_format((float) $normalized, 2, '.', '');
    }

    public function assertMayCreateNew(string $type): void
    {
        $type = $this->type($type);

        if ($type !== 'BANK_REFERENCE') {
            throw SifException::conflict(
                'Authoritative payment channel has not recorded this external receipt yet'
            );
        }
    }

    public function type(string $type): string
    {
        $type = strtoupper(trim($type));
        if (!in_array($type, self::TYPES, true)) {
            throw SifException::validation('Invalid external receipt type');
        }

        return $type;
    }
}
