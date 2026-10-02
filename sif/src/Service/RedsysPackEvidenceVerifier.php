<?php

namespace Prisma\Sif\Service;

final class RedsysPackEvidenceVerifier
{
    public function verify(\PDO $sifDb, \PDO $legacyDb, string $dsOrder): array
    {
        $dsOrder = trim($dsOrder);
        if ($dsOrder === '') {
            throw new \InvalidArgumentException('DS_ORDER is required');
        }

        $intent = $this->one(
            $sifDb,
            'SELECT UUID_INTENT, DS_ORDER, IDPAG, SOURCE_TYPE, EXPECTED_AMOUNT, CURRENCY, STATUS
             FROM redsys_payment_intent WHERE DS_ORDER = ?',
            [$dsOrder]
        );
        $notification = $this->one(
            $sifDb,
            'SELECT ID, DS_ORDER, IDPAG, IMPORT, STATUS, SIGNATURE_VALID
             FROM redsys_notifications WHERE DS_ORDER = ?',
            [$dsOrder]
        );

        $queue = null;
        if ($notification !== null) {
            $queue = $this->one(
                $sifDb,
                'SELECT UUID_JOB, STATUS, ATTEMPTS, RESULT_JSON, UUID_FACTURA, UUID_PAYMENT,
                        LAST_ERROR, PROCESSED_AT
                 FROM redsys_callback_queue
                 WHERE NOTIFICATION_ID = ?',
                [(int) $notification['ID']]
            );
        }

        $uuidFactura = trim((string) ($queue['UUID_FACTURA'] ?? ''));
        $uuidPayment = trim((string) ($queue['UUID_PAYMENT'] ?? ''));

        $invoice = $uuidFactura === '' ? null : $this->one(
            $sifDb,
            'SELECT UUID_FACTURA, NUM_VISIBLE, TOTAL, ESTAT_COBRAMENT, ESTAT_FACTURA,
                    SOURCE_CHANNEL
             FROM factura WHERE UUID_FACTURA = ?',
            [$uuidFactura]
        );
        $payment = $uuidPayment === '' ? null : $this->one(
            $sifDb,
            'SELECT UUID_PAYMENT, TIPUS_MOVIMENT, METODE, SOURCE_CHANNEL, IMPORT, DS_ORDER,
                    IDPAG, ESTAT
             FROM payment_transaction WHERE UUID_PAYMENT = ?',
            [$uuidPayment]
        );

        $lines = $uuidFactura === '' ? [] : $this->all(
            $sifDb,
            "SELECT ID, ORDRE, TOTAL, SOURCE_TYPE, SOURCE_ID
             FROM factura_linia
             WHERE UUID_FACTURA = ?
             ORDER BY ORDRE",
            [$uuidFactura]
        );
        $relations = $uuidFactura === '' ? [] : $this->all(
            $sifDb,
            'SELECT SOURCE_TYPE, SOURCE_ID, IDPAG, DS_ORDER
             FROM fact_rels
             WHERE UUID_FACTURA = ?
             ORDER BY ID',
            [$uuidFactura]
        );

        $paymentAllocation = ($uuidFactura === '' || $uuidPayment === '') ? null : $this->one(
            $sifDb,
            'SELECT COUNT(*) AS CNT, COALESCE(SUM(IMPORT_ASSIGNAT), 0) AS TOTAL
             FROM payment_allocation
             WHERE UUID_FACTURA = ? AND UUID_PAYMENT = ?',
            [$uuidFactura, $uuidPayment]
        );
        $funds = ($uuidFactura === '' || $uuidPayment === '') ? null : $this->one(
            $sifDb,
            "SELECT COUNT(*) AS CNT, COALESCE(SUM(IMPORT), 0) AS TOTAL,
                    COUNT(DISTINCT ID_INSC_DESTI) AS INSCRIPTIONS
             FROM enrollment_fund_movement
             WHERE UUID_FACTURA = ? AND UUID_PAYMENT = ?
               AND MOVEMENT_TYPE = 'EXTERNAL_ALLOCATION'",
            [$uuidFactura, $uuidPayment]
        );
        $outbox = ($uuidFactura === '' || $uuidPayment === '') ? [] : $this->all(
            $sifDb,
            "SELECT UUID_NOTIFICATION, TEMPLATE_CODE, STATUS, UUID_FACTURA, UUID_PAYMENT,
                    CORRELATION_ID
             FROM notification_outbox
             WHERE UUID_FACTURA = ? AND UUID_PAYMENT = ?
               AND TEMPLATE_CODE = 'PACK_PAYMENT_CONFIRMED'",
            [$uuidFactura, $uuidPayment]
        );
        $fiscalRecord = $uuidFactura === '' ? null : $this->one(
            $sifDb,
            "SELECT COUNT(*) AS CNT
             FROM factura_registres
             WHERE UUID_FACTURA = ? AND TIPUS_REGISTRE = 'ALTA'",
            [$uuidFactura]
        );
        $fiscalQueue = $uuidFactura === '' ? null : $this->one(
            $sifDb,
            'SELECT COUNT(*) AS CNT FROM fiscal_queue WHERE UUID_FACTURA = ?',
            [$uuidFactura]
        );

        $inscriptionIds = [];
        $packRelations = 0;
        $relationOrdersMatch = true;
        foreach ($relations as $relation) {
            $sourceType = strtoupper((string) ($relation['SOURCE_TYPE'] ?? ''));
            if ($sourceType === 'PACK') {
                $packRelations++;
            } elseif ($sourceType === 'INSCRIPCIO' && is_numeric($relation['SOURCE_ID'] ?? null)) {
                $inscriptionIds[] = (int) $relation['SOURCE_ID'];
            }

            if ((string) ($relation['DS_ORDER'] ?? '') !== $dsOrder) {
                $relationOrdersMatch = false;
            }
        }
        $inscriptionIds = array_values(array_unique($inscriptionIds));

        $legacyRows = $this->legacyRows($legacyDb, $inscriptionIds);
        $legacyPaid = count($legacyRows) === count($inscriptionIds) && $inscriptionIds !== [];
        $legacyMarked = $legacyPaid;
        foreach ($legacyRows as $row) {
            $legacyPaid = $legacyPaid
                && $this->money($row['PAGAMENT'] ?? null) === $this->money($row['A_PAGAR'] ?? null)
                && trim((string) ($row['DATA_PAG'] ?? '')) !== '';
            $legacyMarked = $legacyMarked
                && $uuidFactura !== ''
                && str_contains((string) ($row['OBSERVACIONS'] ?? ''), $uuidFactura);
        }

        $expectedAmount = $this->money($intent['EXPECTED_AMOUNT'] ?? null);
        $notificationAmount = $this->money($notification['IMPORT'] ?? null);
        $invoiceTotal = $this->money($invoice['TOTAL'] ?? null);
        $paymentAmount = $this->money($payment['IMPORT'] ?? null);
        $lineTotal = $this->sumMoney(array_column($lines, 'TOTAL'));
        $allocationTotal = $this->money($paymentAllocation['TOTAL'] ?? null);
        $fundTotal = $this->money($funds['TOTAL'] ?? null);

        $resultJson = json_decode((string) ($queue['RESULT_JSON'] ?? ''), true);
        $legacySyncExecuted = is_array($resultJson)
            && ($resultJson['legacy_sync_executed'] ?? false) === true;

        $checks = [
            'intent_pack' => $intent !== null
                && strtoupper((string) ($intent['SOURCE_TYPE'] ?? '')) === 'PACK',
            'notification_validated' => $notification !== null
                && (string) ($notification['STATUS'] ?? '') === 'VALIDATED'
                && (int) ($notification['SIGNATURE_VALID'] ?? 0) === 1,
            'identity_matches' => $intent !== null && $notification !== null
                && (int) ($intent['IDPAG'] ?? 0) > 0
                && (int) ($intent['IDPAG'] ?? 0) === (int) ($notification['IDPAG'] ?? -1),
            'amount_matches_end_to_end' => $expectedAmount !== null
                && $expectedAmount === $notificationAmount
                && $expectedAmount === $invoiceTotal
                && $expectedAmount === $paymentAmount
                && $expectedAmount === $lineTotal
                && $expectedAmount === $allocationTotal
                && $expectedAmount === $fundTotal,
            'callback_processed' => $queue !== null
                && (string) ($queue['STATUS'] ?? '') === 'PROCESSED'
                && $uuidFactura !== ''
                && $uuidPayment !== ''
                && trim((string) ($queue['LAST_ERROR'] ?? '')) === '',
            'invoice_paid' => $invoice !== null
                && (string) ($invoice['ESTAT_FACTURA'] ?? '') === 'ISSUED'
                && (string) ($invoice['ESTAT_COBRAMENT'] ?? '') === 'PAID'
                && (string) ($invoice['SOURCE_CHANNEL'] ?? '') === 'REDSYS',
            'single_confirmed_charge' => $payment !== null
                && (string) ($payment['TIPUS_MOVIMENT'] ?? '') === 'CHARGE'
                && (string) ($payment['METODE'] ?? '') === 'REDSYS'
                && (string) ($payment['ESTAT'] ?? '') === 'CONFIRMED'
                && (string) ($payment['DS_ORDER'] ?? '') === $dsOrder,
            'invoice_lines_present' => count($lines) >= 2,
            'relations_complete' => $packRelations === 1
                && count($inscriptionIds) >= 2
                && $relationOrdersMatch,
            'single_invoice_allocation' => (int) ($paymentAllocation['CNT'] ?? 0) === 1,
            'fund_ledger_complete' => (int) ($funds['CNT'] ?? 0) === count($inscriptionIds)
                && (int) ($funds['INSCRIPTIONS'] ?? 0) === count($inscriptionIds),
            'single_notification_outbox' => count($outbox) === 1
                && (string) ($outbox[0]['CORRELATION_ID'] ?? '') === 'REDSYS|' . $dsOrder,
            'fiscal_record_present' => (int) ($fiscalRecord['CNT'] ?? 0) >= 1,
            'fiscal_queue_present' => (int) ($fiscalQueue['CNT'] ?? 0) >= 1,
            'legacy_sync_reported' => $legacySyncExecuted,
            'legacy_inscriptions_paid' => $legacyPaid,
            'legacy_invoice_marker_present' => $legacyMarked,
        ];

        $failed = array_keys(array_filter($checks, static fn (bool $ok): bool => !$ok));

        return [
            'ok' => $failed === [],
            'ds_order' => $dsOrder,
            'uuid_intent' => (string) ($intent['UUID_INTENT'] ?? ''),
            'uuid_factura' => $uuidFactura,
            'uuid_payment' => $uuidPayment,
            'num_visible' => (string) ($invoice['NUM_VISIBLE'] ?? ''),
            'idpag' => (int) ($intent['IDPAG'] ?? 0),
            'amount' => $expectedAmount,
            'queue_status' => (string) ($queue['STATUS'] ?? ''),
            'outbox_status' => count($outbox) === 1 ? (string) $outbox[0]['STATUS'] : '',
            'inscription_ids' => $inscriptionIds,
            'checks' => $checks,
            'failed' => $failed,
        ];
    }

    private function legacyRows(\PDO $legacyDb, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $marks = implode(', ', array_fill(0, count($ids), '?'));
        $stmt = $legacyDb->prepare(
            "SELECT ID, A_PAGAR, PAGAMENT, `DATA PAG` AS DATA_PAG, OBSERVACIONS
             FROM inscripcions
             WHERE ID IN ({$marks}) AND TIPUS_INSC = 'P'"
        );
        $stmt->execute($ids);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function one(\PDO $db, string $sql, array $params): ?array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function all(\PDO $db, string $sql, array $params): array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function money(mixed $value): ?string
    {
        return is_numeric($value) ? number_format((float) $value, 2, '.', '') : null;
    }

    private function sumMoney(array $values): ?string
    {
        if ($values === []) {
            return null;
        }

        $sum = 0.0;
        foreach ($values as $value) {
            if (!is_numeric($value)) {
                return null;
            }
            $sum += (float) $value;
        }

        return number_format($sum, 2, '.', '');
    }
}
