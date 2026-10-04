<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Exception\SifException;

final class OperationLineInvoiceLinkRepository
{
    public function link(
        \PDO $db,
        string $uuidOperationLine,
        int $facturaLineId,
        string $linkedAmount
    ): void {
        $uuidOperationLine = strtolower(trim($uuidOperationLine));
        if (preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D',
            $uuidOperationLine
        ) !== 1) {
            throw SifException::validation('Invalid commercial operation line UUID');
        }
        if ($facturaLineId < 1 || !is_numeric($linkedAmount)) {
            throw SifException::validation('Invalid commercial operation invoice link');
        }

        $amount = number_format((float) $linkedAmount, 2, '.', '');

        try {
            $db->prepare(
                'INSERT INTO operation_line_invoice_link (
                    UUID_LINE, FACTURA_LINE_ID, LINK_TYPE, LINKED_AMOUNT
                ) VALUES (?, ?, ?, ?)'
            )->execute([
                $uuidOperationLine,
                $facturaLineId,
                'MATERIALISED_AS',
                $amount,
            ]);
        } catch (\PDOException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }

            $stmt = $db->prepare(
                'SELECT LINK_TYPE, LINKED_AMOUNT
                 FROM operation_line_invoice_link
                 WHERE UUID_LINE = ? AND FACTURA_LINE_ID = ?'
            );
            $stmt->execute([$uuidOperationLine, $facturaLineId]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($existing === false
                || (string) $existing['LINK_TYPE'] !== 'MATERIALISED_AS'
                || number_format((float) $existing['LINKED_AMOUNT'], 2, '.', '') !== $amount
            ) {
                throw SifException::conflict(
                    'Commercial operation line invoice link already exists with different data'
                );
            }
        }
    }
}
