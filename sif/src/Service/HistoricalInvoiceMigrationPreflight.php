<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class HistoricalInvoiceMigrationPreflight
{
    public function assertSafe(\PDO $db, array $payload): void
    {
        $claimed = $this->findClaimedNumber($db, $payload);
        if ($claimed !== null) {
            if ((string) $claimed['IDEMPOTENCY_KEY'] === (string) $payload['idempotency_key']) {
                return;
            }

            throw SifException::conflict(
                'Historical invoice number is already assigned to another invoice'
            );
        }

        $lastSequence = $this->currentFiscalSequence($db, $payload);
        if ($lastSequence === null) {
            $currentYear = (int) (new \DateTimeImmutable(
                'now',
                new \DateTimeZone('Europe/Madrid')
            ))->format('Y');

            if ((int) $payload['year'] >= $currentYear) {
                throw SifException::conflict(
                    'Historical invoice series/year has no fiscal sequence checkpoint; migration requires an explicit coexistence decision'
                );
            }

            return;
        }

        if ((int) $payload['num_seq'] > $lastSequence) {
            throw SifException::conflict(
                'Historical invoice number is ahead of the active fiscal sequence; migration requires an explicit coexistence decision'
            );
        }
    }

    private function findClaimedNumber(\PDO $db, array $payload): ?array
    {
        $stmt = $db->prepare(
            'SELECT UUID_FACTURA, IDEMPOTENCY_KEY, NUM_VISIBLE, EMISSOR_NIF
             FROM factura
             WHERE NUM_VISIBLE = ?
                OR (TIPUS_SERIE = ? AND ANY_FACT = ? AND NUM_SEQ = ?)
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([
            $payload['num_visible'],
            $payload['series'],
            $payload['year'],
            $payload['num_seq'],
        ]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function currentFiscalSequence(\PDO $db, array $payload): ?int
    {
        $stmt = $db->prepare(
            'SELECT LAST_NUM
             FROM fiscal_sequence
             WHERE TIPUS_SERIE = ? AND ANY_FACT = ?
             LIMIT 1
             FOR UPDATE'
        );
        $stmt->execute([$payload['series'], $payload['year']]);
        $last = $stmt->fetchColumn();

        return $last === false ? null : (int) $last;
    }
}
