<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class ExistingInvoiceLegacyProjectionService
{
    public function build(\PDO $db, string $uuidFactura): array
    {
        $uuidFactura = trim($uuidFactura);
        if ($uuidFactura === '') {
            throw SifException::validation('Missing invoice UUID for legacy payment projection');
        }

        $invoiceStmt = $db->prepare(
            'SELECT UUID_FACTURA, NUM_VISIBLE, TOTAL, ESTAT_COBRAMENT
             FROM factura
             WHERE UUID_FACTURA = ?'
        );
        $invoiceStmt->execute([$uuidFactura]);
        $invoice = $invoiceStmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($invoice)) {
            throw SifException::validation('Invoice not found for legacy payment projection');
        }

        $linesStmt = $db->prepare(
            "SELECT ORDRE, SOURCE_ID, TOTAL
             FROM factura_linia
             WHERE UUID_FACTURA = ?
               AND SOURCE_TYPE = 'INSCRIPCIO'
               AND SOURCE_ID IS NOT NULL
             ORDER BY ORDRE, ID"
        );
        $linesStmt->execute([$uuidFactura]);
        $lines = $linesStmt->fetchAll(\PDO::FETCH_ASSOC);
        if (!is_array($lines) || $lines === []) {
            throw SifException::conflict(
                'Invoice has no inscription lines for legacy payment projection'
            );
        }

        $sources = [];
        foreach ($lines as $line) {
            $sourceId = (int) ($line['SOURCE_ID'] ?? 0);
            if ($sourceId <= 0) {
                throw SifException::conflict('Invoice contains an invalid inscription source');
            }

            $cents = $this->cents($line['TOTAL'] ?? null, 'invoice line total');
            if ($cents < 0) {
                throw SifException::conflict('Invoice line total cannot be negative');
            }

            if (!isset($sources[$sourceId])) {
                $sources[$sourceId] = [
                    'id_insc' => $sourceId,
                    'order' => (int) ($line['ORDRE'] ?? 0),
                    'line_total_cents' => 0,
                    'factura_relacionada' => null,
                    'idpag' => null,
                ];
            }

            $sources[$sourceId]['line_total_cents'] += $cents;
            $sources[$sourceId]['order'] = min(
                $sources[$sourceId]['order'],
                (int) ($line['ORDRE'] ?? $sources[$sourceId]['order'])
            );
        }

        $relsStmt = $db->prepare(
            "SELECT SOURCE_ID, FACTURA_RELACIONADA, IDPAG
             FROM fact_rels
             WHERE UUID_FACTURA = ?
               AND SOURCE_TYPE = 'INSCRIPCIO'
               AND RELATION_TYPE = 'ORIGIN'
             ORDER BY ID"
        );
        $relsStmt->execute([$uuidFactura]);
        $relations = $relsStmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach (is_array($relations) ? $relations : [] as $relation) {
            $sourceId = (int) ($relation['SOURCE_ID'] ?? 0);
            if ($sourceId <= 0 || !isset($sources[$sourceId])) {
                continue;
            }

            $facturaRelacionada = $relation['FACTURA_RELACIONADA'];
            $idpag = $relation['IDPAG'];

            if ($sources[$sourceId]['factura_relacionada'] !== null
                && $facturaRelacionada !== null
                && (int) $sources[$sourceId]['factura_relacionada'] !== (int) $facturaRelacionada
            ) {
                throw SifException::conflict(
                    'Conflicting legacy related invoice for inscription projection'
                );
            }

            if ($sources[$sourceId]['idpag'] !== null
                && $idpag !== null
                && (int) $sources[$sourceId]['idpag'] !== (int) $idpag
            ) {
                throw SifException::conflict(
                    'Conflicting legacy IDPAG for inscription projection'
                );
            }

            if ($facturaRelacionada !== null) {
                $sources[$sourceId]['factura_relacionada'] = (int) $facturaRelacionada;
            }
            if ($idpag !== null) {
                $sources[$sourceId]['idpag'] = (int) $idpag;
            }
        }

        $invoiceTotalCents = $this->cents($invoice['TOTAL'] ?? null, 'invoice total');
        $lineTotalCents = array_sum(array_column($sources, 'line_total_cents'));
        if ($invoiceTotalCents !== $lineTotalCents) {
            throw SifException::conflict(
                'Invoice inscription lines do not cover the full invoice total'
            );
        }

        $paidStmt = $db->prepare(
            "SELECT COALESCE(SUM(
                CASE
                    WHEN pt.TIPUS_MOVIMENT IN ('CHARGE','COMPENSATION')
                        THEN pa.IMPORT_ASSIGNAT
                    WHEN pt.TIPUS_MOVIMENT = 'REFUND'
                        THEN -pa.IMPORT_ASSIGNAT
                    ELSE 0
                END
            ), 0)
             FROM payment_allocation pa
             INNER JOIN payment_transaction pt
                ON pt.UUID_PAYMENT = pa.UUID_PAYMENT
             WHERE pa.UUID_FACTURA = ?
               AND pt.ESTAT = 'CONFIRMED'"
        );
        $paidStmt->execute([$uuidFactura]);
        $netPaidCents = max(
            0,
            $this->signedCents($paidStmt->fetchColumn(), 'invoice paid total')
        );
        $projectedTotalCents = min($invoiceTotalCents, $netPaidCents);

        uasort(
            $sources,
            static fn (array $a, array $b): int => [$a['order'], $a['id_insc']]
                <=> [$b['order'], $b['id_insc']]
        );

        $remaining = $projectedTotalCents;
        $items = [];
        foreach ($sources as $source) {
            $projected = min($source['line_total_cents'], max(0, $remaining));
            $remaining -= $projected;

            $items[] = [
                'id_insc' => $source['id_insc'],
                'idpag' => $source['idpag'],
                'factura_relacionada' => $source['factura_relacionada'],
                'line_total' => $this->amount($source['line_total_cents']),
                'projected_payment' => $this->amount($projected),
                'fully_paid' => $projected >= $source['line_total_cents'],
            ];
        }

        return [
            'uuid_factura' => (string) $invoice['UUID_FACTURA'],
            'num_visible' => (string) $invoice['NUM_VISIBLE'],
            'payment_status' => (string) $invoice['ESTAT_COBRAMENT'],
            'invoice_total' => $this->amount($invoiceTotalCents),
            'net_paid' => $this->amount($netPaidCents),
            'projected_total' => $this->amount($projectedTotalCents),
            'overpaid_amount' => $this->amount(max(0, $netPaidCents - $invoiceTotalCents)),
            'items' => $items,
        ];
    }

    private function cents(mixed $value, string $label): int
    {
        $raw = trim(str_replace(',', '.', (string) $value));
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $raw)) {
            throw SifException::validation("Invalid {$label}");
        }

        [$euros, $decimals] = array_pad(explode('.', $raw, 2), 2, '');

        return (int) $euros * 100 + (int) str_pad($decimals, 2, '0');
    }

    private function signedCents(mixed $value, string $label): int
    {
        $raw = trim(str_replace(',', '.', (string) $value));
        if (!preg_match('/^-?\d{1,10}(?:\.\d{1,2})?$/D', $raw)) {
            throw SifException::validation("Invalid {$label}");
        }

        $negative = str_starts_with($raw, '-');
        if ($negative) {
            $raw = substr($raw, 1);
        }

        $cents = $this->cents($raw, $label);

        return $negative ? -$cents : $cents;
    }

    private function amount(int $cents): string
    {
        return intdiv($cents, 100)
            . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
