<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Exception\SifException;

final class ClaimPaymentExternalReceiptRepository
{
    private const COLUMNS = [
        'BANK_REFERENCE' => 'REFERENCIA_BANCARIA',
        'DS_ORDER' => 'DS_ORDER',
        'PROVIDER_REF' => 'PROVIDER_REF',
    ];

    public function findUnique(
        \PDO $db,
        string $type,
        string $externalReceiptId,
        bool $forUpdate = false
    ): ?array {
        $type = strtoupper(trim($type));
        $externalReceiptId = trim($externalReceiptId);

        if (!isset(self::COLUMNS[$type])) {
            throw SifException::validation('Invalid external receipt type');
        }
        if ($externalReceiptId === '') {
            throw SifException::validation('Missing external receipt id');
        }

        $sql = 'SELECT * FROM payment_transaction WHERE '
            . self::COLUMNS[$type]
            . ' = ? ORDER BY ID LIMIT 2';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $db->prepare($sql);
        $stmt->execute([$externalReceiptId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($rows) > 1) {
            throw SifException::conflict(
                'External receipt matches more than one payment transaction'
            );
        }

        return $rows[0] ?? null;
    }

    public function assertAllocatedToInvoice(
        \PDO $db,
        string $uuidPayment,
        string $uuidFactura
    ): void {
        $stmt = $db->prepare(
            'SELECT COUNT(*)
             FROM payment_allocation
             WHERE UUID_PAYMENT = ?
               AND UUID_FACTURA = ?'
        );
        $stmt->execute([$uuidPayment, $uuidFactura]);

        if ((int) $stmt->fetchColumn() < 1) {
            throw SifException::conflict(
                'External receipt is already linked to a different invoice'
            );
        }
    }
}
