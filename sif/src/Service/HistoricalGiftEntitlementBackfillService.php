<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\CommercialEntitlementRepository;

/**
 * UC-018 deployment bridge for gifts purchased before automatic GIFT
 * entitlement issuance existed.
 *
 * Read-only inventory is deliberately conservative. A historical unused gift
 * is backfillable only when SIF already contains one exact paid invoice and one
 * exact confirmed CHARGE allocated to that invoice.
 */
final class HistoricalGiftEntitlementBackfillService
{
    public function __construct(
        private CommercialEntitlementRepository $entitlements,
        private GiftEntitlementIssuerService $issuer
    ) {
    }

    public function inventory(
        \PDO $sifDb,
        \PDO $legacyDb,
        ?int $giftId = null
    ): array {
        if ($giftId !== null && $giftId <= 0) {
            throw SifException::validation('Invalid historical gift ID');
        }

        $sql = 'SELECT ID, CODI, IMPORT, FACT_REL, USAT, CCURS, NOM_CURS
                FROM regal';
        $params = [];
        if ($giftId !== null) {
            $sql .= ' WHERE ID = ?';
            $params[] = $giftId;
        }
        $sql .= ' ORDER BY ID';

        $statement = $legacyDb->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        $items = [];
        foreach ($rows as $gift) {
            if (!is_array($gift)) {
                continue;
            }
            $items[] = $this->assess($sifDb, $gift);
        }

        $summary = [
            'total' => count($items),
            'entitlement_present' => 0,
            'ready_to_backfill' => 0,
            'historical_used_no_backfill' => 0,
            'needs_review' => 0,
            'blocking_unused' => 0,
        ];

        foreach ($items as $item) {
            $status = (string) ($item['status'] ?? '');
            if ($status === 'ENTITLEMENT_PRESENT') {
                $summary['entitlement_present']++;
            } elseif ($status === 'READY_TO_BACKFILL') {
                $summary['ready_to_backfill']++;
                $summary['blocking_unused']++;
            } elseif ($status === 'HISTORICAL_USED_NO_BACKFILL') {
                $summary['historical_used_no_backfill']++;
            } else {
                $summary['needs_review']++;
                if (($item['legacy_used'] ?? true) === false) {
                    $summary['blocking_unused']++;
                }
            }
        }

        return [
            'ok' => true,
            'read_only' => true,
            'summary' => $summary,
            'items' => $items,
        ];
    }

    public function backfillOne(
        \PDO $sifDb,
        \PDO $legacyDb,
        int $giftId
    ): array {
        if ($giftId <= 0) {
            throw SifException::validation('Invalid historical gift ID');
        }

        $statement = $legacyDb->prepare(
            'SELECT ID, CODI, IMPORT, FACT_REL, USAT, CCURS, NOM_CURS
             FROM regal WHERE ID = ?'
        );
        $statement->execute([$giftId]);
        $gift = $statement->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($gift)) {
            throw SifException::conflict('Historical gift was not found');
        }

        $assessment = $this->assess($sifDb, $gift);
        if ($assessment['status'] === 'ENTITLEMENT_PRESENT') {
            return [
                'ok' => true,
                'gift_id' => $giftId,
                'status' => 'ENTITLEMENT_PRESENT',
                'idempotency_reused' => true,
                'assessment' => $assessment,
            ];
        }

        if ($assessment['status'] !== 'READY_TO_BACKFILL') {
            throw SifException::conflict(
                'Historical gift is not safe to backfill: '
                . (string) $assessment['status']
            );
        }

        $evidence = (array) $assessment['evidence'];
        $reference = sprintf(
            'HISTORIC|REGAL:%d|PAYMENT:%s',
            $giftId,
            (string) $evidence['uuid_payment']
        );

        $result = $this->issuer->issue(
            $sifDb,
            [
                'ID' => $giftId,
                'CODI' => (string) $gift['CODI'],
                'CCURS' => (string) $gift['CCURS'],
                'IMPORT' => (string) $gift['IMPORT'],
            ],
            [
                'uuid_factura' => (string) $evidence['uuid_factura'],
                'uuid_payment' => (string) $evidence['uuid_payment'],
            ],
            $reference,
            'MIGRATION',
            'gift-entitlement-backfill'
        );

        return [
            'ok' => true,
            'gift_id' => $giftId,
            'status' => 'BACKFILLED',
            'idempotency_reused' => (bool) $result['idempotency_reused'],
            'uuid_operation' => (string) $result['uuid_operation'],
            'uuid_entitlement' => (string) $result['uuid_entitlement'],
            'holder_state' => (string) $result['holder_state'],
            'entitlement_status' => (string) $result['status'],
            'evidence' => $evidence,
        ];
    }

    private function assess(\PDO $sifDb, array $gift): array
    {
        $giftId = $this->positiveInt($gift['ID'] ?? null);
        $code = trim((string) ($gift['CODI'] ?? ''));
        $course = strtoupper(trim((string) ($gift['CCURS'] ?? '')));
        $legacyUsedBy = $this->nullablePositiveInt($gift['USAT'] ?? null);
        $legacyUsed = $legacyUsedBy !== null;

        $base = [
            'gift_id' => $giftId,
            'legacy_used' => $legacyUsed,
            'legacy_used_enrollment_id' => $legacyUsedBy,
            'code_hash' => $code === '' ? null : hash('sha256', $code),
        ];

        if ($giftId <= 0 || $code === '' || strlen($code) > 200) {
            return $base + [
                'status' => 'INVALID_LEGACY_GIFT',
                'reason' => 'Gift ID/code is invalid.',
            ];
        }

        $amount = $this->moneyOrNull($gift['IMPORT'] ?? null);
        if ($amount === null || (float) $amount <= 0.0 || $course === '') {
            return $base + [
                'status' => 'INVALID_LEGACY_GIFT',
                'reason' => 'Gift amount/course is invalid.',
            ];
        }

        $codeHash = hash('sha256', $code);
        $entitlement = $this->entitlements->findByCodeHash($sifDb, $codeHash, false);
        if ($entitlement !== null) {
            $type = strtoupper((string) ($entitlement['ENTITLEMENT_TYPE'] ?? ''));
            $faceValue = $this->moneyOrNull($entitlement['FACE_VALUE'] ?? null);
            $currency = strtoupper((string) ($entitlement['CURRENCY'] ?? ''));
            $status = strtoupper((string) ($entitlement['STATUS'] ?? ''));

            if ($type !== 'GIFT' || $faceValue !== $amount || $currency !== 'EUR') {
                return $base + [
                    'status' => 'CONFLICT_EXISTING_ENTITLEMENT',
                    'reason' => 'Existing entitlement does not match legacy gift value/type.',
                    'entitlement_status' => $status,
                ];
            }

            if (!$legacyUsed && in_array($status, ['CONSUMED', 'CANCELLED'], true)) {
                return $base + [
                    'status' => 'CONFLICT_UNUSED_LEGACY_TERMINAL_SIF',
                    'reason' => 'Legacy gift is unused but SIF entitlement is terminal.',
                    'entitlement_status' => $status,
                ];
            }

            if (!$legacyUsed && $status === 'RESERVED') {
                return $base + [
                    'status' => 'RESERVED_ENTITLEMENT_REVIEW',
                    'reason' => 'Unused legacy gift has a reserved SIF entitlement.',
                    'entitlement_status' => $status,
                ];
            }

            return $base + [
                'status' => 'ENTITLEMENT_PRESENT',
                'entitlement_status' => $status,
                'uuid_entitlement' => (string) $entitlement['UUID_ENTITLEMENT'],
            ];
        }

        if ($legacyUsed) {
            return $base + [
                'status' => 'HISTORICAL_USED_NO_BACKFILL',
                'reason' => 'Gift was already consumed in legacy before entitlement backfill.',
            ];
        }

        $invoiceEvidence = $this->invoiceEvidence($sifDb, $giftId);
        if (count($invoiceEvidence) === 0) {
            return $base + [
                'status' => 'MISSING_SIF_INVOICE',
                'reason' => 'No SIF invoice relation exists for this unused gift.',
            ];
        }

        if (count($invoiceEvidence) > 1) {
            return $base + [
                'status' => 'AMBIGUOUS_SIF_INVOICE',
                'reason' => 'More than one SIF invoice is related to this gift.',
                'invoice_count' => count($invoiceEvidence),
            ];
        }

        $invoice = $invoiceEvidence[0];
        $invoiceTotal = $this->moneyOrNull($invoice['TOTAL'] ?? null);
        if ($invoiceTotal !== $amount
            || strtoupper((string) ($invoice['ESTAT_COBRAMENT'] ?? '')) !== 'PAID'
            || strtoupper((string) ($invoice['ESTAT_FACTURA'] ?? '')) !== 'ISSUED'
        ) {
            return $base + [
                'status' => 'INVOICE_EVIDENCE_MISMATCH',
                'reason' => 'SIF invoice is not one exact ISSUED+PAID invoice for the gift.',
                'uuid_factura' => (string) ($invoice['UUID_FACTURA'] ?? ''),
            ];
        }

        $payments = $this->confirmedChargeEvidence(
            $sifDb,
            (string) $invoice['UUID_FACTURA']
        );
        if (count($payments) === 0) {
            return $base + [
                'status' => 'MISSING_CONFIRMED_CHARGE',
                'reason' => 'No confirmed CHARGE is allocated to the gift invoice.',
                'uuid_factura' => (string) $invoice['UUID_FACTURA'],
            ];
        }

        if (count($payments) > 1) {
            return $base + [
                'status' => 'AMBIGUOUS_CONFIRMED_CHARGE',
                'reason' => 'More than one confirmed CHARGE is allocated to the gift invoice.',
                'uuid_factura' => (string) $invoice['UUID_FACTURA'],
                'payment_count' => count($payments),
            ];
        }

        $payment = $payments[0];
        $paymentAmount = $this->moneyOrNull($payment['IMPORT'] ?? null);
        $allocatedAmount = $this->moneyOrNull($payment['IMPORT_ASSIGNAT'] ?? null);
        if ($paymentAmount !== $amount || $allocatedAmount !== $amount) {
            return $base + [
                'status' => 'PAYMENT_EVIDENCE_MISMATCH',
                'reason' => 'Confirmed CHARGE/allocation does not exactly match gift value.',
                'uuid_factura' => (string) $invoice['UUID_FACTURA'],
                'uuid_payment' => (string) ($payment['UUID_PAYMENT'] ?? ''),
            ];
        }

        return $base + [
            'status' => 'READY_TO_BACKFILL',
            'evidence' => [
                'uuid_factura' => (string) $invoice['UUID_FACTURA'],
                'uuid_payment' => (string) $payment['UUID_PAYMENT'],
                'amount' => $amount,
                'invoice_status' => (string) $invoice['ESTAT_FACTURA'],
                'payment_status' => (string) $payment['ESTAT'],
                'payment_method' => (string) $payment['METODE'],
                'source_channel' => (string) $payment['SOURCE_CHANNEL'],
                'provider_reference_hash' => trim((string) ($payment['DS_ORDER'] ?? '')) === ''
                    ? null
                    : hash('sha256', (string) $payment['DS_ORDER']),
            ],
        ];
    }

    private function invoiceEvidence(\PDO $db, int $giftId): array
    {
        $statement = $db->prepare(
            "SELECT DISTINCT f.UUID_FACTURA, f.TOTAL, f.ESTAT_COBRAMENT,
                    f.ESTAT_FACTURA, f.SOURCE_CHANNEL, f.CREATED_AT
             FROM fact_rels fr
             INNER JOIN factura f ON f.UUID_FACTURA = fr.UUID_FACTURA
             WHERE fr.SOURCE_TYPE = 'REGAL' AND fr.SOURCE_ID = ?
             ORDER BY f.CREATED_AT, f.UUID_FACTURA"
        );
        $statement->execute([$giftId]);

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function confirmedChargeEvidence(\PDO $db, string $uuidInvoice): array
    {
        $statement = $db->prepare(
            "SELECT p.UUID_PAYMENT, p.TIPUS_MOVIMENT, p.METODE,
                    p.SOURCE_CHANNEL, p.IMPORT, p.DS_ORDER, p.ESTAT,
                    pa.IMPORT_ASSIGNAT
             FROM payment_allocation pa
             INNER JOIN payment_transaction p
                ON p.UUID_PAYMENT = pa.UUID_PAYMENT
             WHERE pa.UUID_FACTURA = ?
               AND pa.TIPUS_ASSIGNACIO = 'INVOICE_PAYMENT'
               AND p.TIPUS_MOVIMENT = 'CHARGE'
               AND p.ESTAT = 'CONFIRMED'
             ORDER BY p.CREATED_AT, p.UUID_PAYMENT"
        );
        $statement->execute([$uuidInvoice]);

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function positiveInt(mixed $value): int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : 0;
    }

    private function nullablePositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || (int) $value === 0) {
            return null;
        }

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function moneyOrNull(mixed $value): ?string
    {
        if (!is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 2, '.', '');
    }
}
