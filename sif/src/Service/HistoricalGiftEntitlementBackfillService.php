<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\CommercialEntitlementRepository;

/**
 * Audits and backfills redeemable legacy gifts created before UC-017 started
 * materialising commercial_entitlement records.
 *
 * Safety rule: no fiscal/payment evidence is reconstructed. A missing right is
 * backfillable only when exactly one SIF gift invoice and exactly one confirmed
 * CHARGE fully reconcile the legacy gift amount.
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

        $gifts = $this->eligibleLegacyGifts($legacyDb, $giftId);
        $items = [];
        $counts = [
            'eligible' => 0,
            'entitled' => 0,
            'ready_backfill' => 0,
            'blocked' => 0,
        ];

        foreach ($gifts as $gift) {
            $item = $this->inspectGift($sifDb, $gift);
            $items[] = $item;
            $counts['eligible']++;

            if ($item['status'] === 'ENTITLED') {
                $counts['entitled']++;
            } elseif ($item['status'] === 'READY_BACKFILL') {
                $counts['ready_backfill']++;
            } else {
                $counts['blocked']++;
            }
        }

        return [
            'ok' => $counts['ready_backfill'] === 0 && $counts['blocked'] === 0,
            'counts' => $counts,
            'items' => $items,
        ];
    }

    public function backfill(
        \PDO $sifDb,
        \PDO $legacyDb,
        int $giftId
    ): array {
        if ($giftId <= 0) {
            throw SifException::validation('Invalid historical gift ID');
        }

        $gifts = $this->eligibleLegacyGifts($legacyDb, $giftId);
        if (count($gifts) !== 1) {
            throw SifException::conflict(
                'Historical gift is not an unused paid legacy gift'
            );
        }

        $gift = $gifts[0];
        $inspection = $this->inspectGift($sifDb, $gift);

        if ($inspection['status'] === 'ENTITLED') {
            return [
                'ok' => true,
                'gift_id' => (int) $gift['ID'],
                'status' => 'ENTITLED',
                'idempotency_reused' => true,
                'uuid_entitlement' => $inspection['uuid_entitlement'],
                'uuid_operation' => $inspection['uuid_operation'],
            ];
        }

        if ($inspection['status'] !== 'READY_BACKFILL') {
            throw SifException::conflict(
                'Historical gift cannot be backfilled: '
                . $inspection['status']
            );
        }

        $result = $this->issuer->issue(
            $sifDb,
            $gift,
            [
                'uuid_factura' => $inspection['uuid_factura'],
                'uuid_payment' => $inspection['uuid_payment'],
            ],
            $inspection['evidence_reference'],
            $inspection['source_channel'],
            'historical-gift-entitlement-backfill'
        );

        $after = $this->inspectGift($sifDb, $gift);
        if ($after['status'] !== 'ENTITLED') {
            throw SifException::conflict(
                'Historical gift backfill did not produce a valid entitlement'
            );
        }

        return [
            'ok' => true,
            'gift_id' => (int) $gift['ID'],
            'status' => 'ENTITLED',
            'idempotency_reused' => (bool) $result['idempotency_reused'],
            'uuid_entitlement' => (string) $result['uuid_entitlement'],
            'uuid_operation' => (string) $result['uuid_operation'],
            'evidence' => [
                'uuid_factura' => $inspection['uuid_factura'],
                'uuid_payment' => $inspection['uuid_payment'],
                'source_channel' => $inspection['source_channel'],
            ],
        ];
    }

    private function eligibleLegacyGifts(\PDO $legacyDb, ?int $giftId): array
    {
        $sql = 'SELECT ID, CODI, IMPORT, FACT_REL, USAT, CCURS, NOM_CURS
                FROM regal
                WHERE FACT_REL IS NOT NULL
                  AND FACT_REL <> 0
                  AND (USAT IS NULL OR USAT = 0)';
        $params = [];

        if ($giftId !== null) {
            $sql .= ' AND ID = ?';
            $params[] = $giftId;
        }

        $sql .= ' ORDER BY ID';

        $statement = $legacyDb->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    private function inspectGift(\PDO $sifDb, array $gift): array
    {
        $giftId = $this->positiveInt($gift['ID'] ?? null, 'gift.ID');
        $giftCode = $this->required($gift['CODI'] ?? null, 'gift.CODI', 200);
        $amount = $this->money($gift['IMPORT'] ?? null, 'gift.IMPORT');
        $factRel = $this->positiveInt($gift['FACT_REL'] ?? null, 'gift.FACT_REL');
        $codeHash = hash('sha256', $giftCode);

        $base = [
            'gift_id' => $giftId,
            'code_hash' => $codeHash,
            'legacy_fact_rel' => $factRel,
            'amount' => $amount,
        ];

        $entitlement = $this->entitlements->findByCodeHash($sifDb, $codeHash, false);
        if ($entitlement !== null) {
            return array_merge(
                $base,
                $this->inspectExistingEntitlement($sifDb, $giftId, $amount, $entitlement)
            );
        }

        $invoices = $this->many(
            $sifDb,
            "SELECT DISTINCT
                    f.UUID_FACTURA,
                    f.TIPUS_FACTURA,
                    f.ESTAT_FACTURA,
                    f.ESTAT_COBRAMENT,
                    f.TOTAL,
                    f.SOURCE_CHANNEL,
                    fr.FACTURA_RELACIONADA,
                    fr.DS_ORDER
             FROM fact_rels fr
             INNER JOIN factura f ON f.UUID_FACTURA = fr.UUID_FACTURA
             WHERE fr.SOURCE_TYPE = 'REGAL'
               AND fr.SOURCE_ID = ?
               AND fr.RELATION_TYPE = 'ORIGIN'
             ORDER BY f.CREATED_AT, f.UUID_FACTURA",
            [$giftId]
        );

        if ($invoices === []) {
            return $base + [
                'status' => 'BLOCKED_NO_SIF_INVOICE',
            ];
        }

        if (count($invoices) !== 1) {
            return $base + [
                'status' => 'BLOCKED_MULTIPLE_SIF_INVOICES',
                'invoice_count' => count($invoices),
            ];
        }

        $invoice = $invoices[0];
        if ((string) ($invoice['FACTURA_RELACIONADA'] ?? '') !== (string) $factRel) {
            return $base + [
                'status' => 'BLOCKED_LEGACY_INVOICE_RELATION_MISMATCH',
                'uuid_factura' => (string) $invoice['UUID_FACTURA'],
            ];
        }

        if (strtoupper((string) ($invoice['TIPUS_FACTURA'] ?? '')) !== 'F1'
            || strtoupper((string) ($invoice['ESTAT_FACTURA'] ?? '')) !== 'ISSUED'
            || strtoupper((string) ($invoice['ESTAT_COBRAMENT'] ?? '')) !== 'PAID'
            || $this->money($invoice['TOTAL'] ?? null, 'invoice.TOTAL') !== $amount
        ) {
            return $base + [
                'status' => 'BLOCKED_SIF_INVOICE_NOT_RECONCILED',
                'uuid_factura' => (string) $invoice['UUID_FACTURA'],
            ];
        }

        $payments = $this->many(
            $sifDb,
            "SELECT DISTINCT
                    p.UUID_PAYMENT,
                    p.TIPUS_MOVIMENT,
                    p.ESTAT,
                    p.IMPORT,
                    p.SOURCE_CHANNEL,
                    p.DS_ORDER,
                    p.PROVIDER_REF,
                    a.IMPORT_ASSIGNAT,
                    a.TIPUS_ASSIGNACIO
             FROM payment_allocation a
             INNER JOIN payment_transaction p ON p.UUID_PAYMENT = a.UUID_PAYMENT
             WHERE a.UUID_FACTURA = ?
               AND a.TIPUS_ASSIGNACIO = 'INVOICE_PAYMENT'
               AND p.TIPUS_MOVIMENT = 'CHARGE'
               AND p.ESTAT = 'CONFIRMED'
             ORDER BY p.CREATED_AT, p.UUID_PAYMENT",
            [(string) $invoice['UUID_FACTURA']]
        );

        if ($payments === []) {
            return $base + [
                'status' => 'BLOCKED_NO_CONFIRMED_PAYMENT',
                'uuid_factura' => (string) $invoice['UUID_FACTURA'],
            ];
        }

        if (count($payments) !== 1) {
            return $base + [
                'status' => 'BLOCKED_MULTIPLE_CONFIRMED_PAYMENTS',
                'uuid_factura' => (string) $invoice['UUID_FACTURA'],
                'payment_count' => count($payments),
            ];
        }

        $payment = $payments[0];
        if ($this->money($payment['IMPORT_ASSIGNAT'] ?? null, 'allocation.IMPORT_ASSIGNAT') !== $amount
            || $this->money($payment['IMPORT'] ?? null, 'payment.IMPORT') !== $amount
        ) {
            return $base + [
                'status' => 'BLOCKED_PAYMENT_AMOUNT_MISMATCH',
                'uuid_factura' => (string) $invoice['UUID_FACTURA'],
                'uuid_payment' => (string) $payment['UUID_PAYMENT'],
            ];
        }

        $sourceChannel = strtoupper(trim((string) ($payment['SOURCE_CHANNEL'] ?? '')));
        if ($sourceChannel === '') {
            $sourceChannel = strtoupper(trim((string) ($invoice['SOURCE_CHANNEL'] ?? '')));
        }
        if ($sourceChannel === '' || strlen($sourceChannel) > 30) {
            return $base + [
                'status' => 'BLOCKED_INVALID_SOURCE_CHANNEL',
                'uuid_factura' => (string) $invoice['UUID_FACTURA'],
                'uuid_payment' => (string) $payment['UUID_PAYMENT'],
            ];
        }

        $evidenceReference = trim((string) ($payment['DS_ORDER'] ?? ''));
        if ($evidenceReference === '') {
            $evidenceReference = trim((string) ($invoice['DS_ORDER'] ?? ''));
        }
        if ($evidenceReference === '') {
            $evidenceReference = 'HIST-GIFT-' . $giftId . '-'
                . substr((string) $payment['UUID_PAYMENT'], 0, 36);
        }
        if (strlen($evidenceReference) > 100) {
            $evidenceReference = 'HIST-GIFT-' . $giftId . '-'
                . substr(hash('sha256', $evidenceReference), 0, 48);
        }

        return $base + [
            'status' => 'READY_BACKFILL',
            'uuid_factura' => (string) $invoice['UUID_FACTURA'],
            'uuid_payment' => (string) $payment['UUID_PAYMENT'],
            'source_channel' => $sourceChannel,
            'evidence_reference' => $evidenceReference,
        ];
    }

    private function inspectExistingEntitlement(
        \PDO $sifDb,
        int $giftId,
        string $amount,
        array $entitlement
    ): array {
        $status = strtoupper(trim((string) ($entitlement['STATUS'] ?? '')));
        $type = strtoupper(trim((string) ($entitlement['ENTITLEMENT_TYPE'] ?? '')));
        $currency = strtoupper(trim((string) ($entitlement['CURRENCY'] ?? '')));
        $originUuid = trim((string) ($entitlement['ORIGIN_UUID_OPERATION'] ?? ''));

        if ($type !== 'GIFT'
            || $currency !== 'EUR'
            || $this->money($entitlement['FACE_VALUE'] ?? null, 'entitlement.FACE_VALUE') !== $amount
            || !in_array($status, ['ISSUED', 'ACTIVE'], true)
            || $originUuid === ''
        ) {
            return [
                'status' => 'BLOCKED_ENTITLEMENT_CONFLICT',
                'uuid_entitlement' => (string) ($entitlement['UUID_ENTITLEMENT'] ?? ''),
            ];
        }

        $operation = $this->one(
            $sifDb,
            "SELECT UUID_OPERATION, SOURCE_TYPE, SOURCE_ID, STATUS, CURRENCY,
                    NET_AMOUNT, UUID_FACTURA, UUID_PAYMENT
             FROM commercial_operation
             WHERE UUID_OPERATION = ?",
            [$originUuid]
        );

        if ($operation === null
            || strtoupper((string) ($operation['SOURCE_TYPE'] ?? '')) !== 'REGAL'
            || (string) ($operation['SOURCE_ID'] ?? '') !== (string) $giftId
            || !in_array(
                strtoupper((string) ($operation['STATUS'] ?? '')),
                ['PAID', 'INVOICED', 'COMPLETED'],
                true
            )
            || strtoupper((string) ($operation['CURRENCY'] ?? '')) !== 'EUR'
            || $this->money($operation['NET_AMOUNT'] ?? null, 'operation.NET_AMOUNT') !== $amount
            || trim((string) ($operation['UUID_FACTURA'] ?? '')) === ''
            || trim((string) ($operation['UUID_PAYMENT'] ?? '')) === ''
        ) {
            return [
                'status' => 'BLOCKED_ENTITLEMENT_ORIGIN_CONFLICT',
                'uuid_entitlement' => (string) $entitlement['UUID_ENTITLEMENT'],
                'uuid_operation' => $originUuid,
            ];
        }

        return [
            'status' => 'ENTITLED',
            'uuid_entitlement' => (string) $entitlement['UUID_ENTITLEMENT'],
            'uuid_operation' => (string) $operation['UUID_OPERATION'],
            'uuid_factura' => (string) $operation['UUID_FACTURA'],
            'uuid_payment' => (string) $operation['UUID_PAYMENT'],
        ];
    }

    private function one(\PDO $db, string $sql, array $params): ?array
    {
        $statement = $db->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function many(\PDO $db, string $sql, array $params): array
    {
        $statement = $db->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    private function positiveInt(mixed $value, string $field): int
    {
        if (!is_numeric($value) || (int) $value <= 0) {
            throw SifException::validation('Invalid ' . $field);
        }

        return (int) $value;
    }

    private function required(mixed $value, string $field, int $max): string
    {
        $value = trim((string) $value);
        if ($value === '' || strlen($value) > $max) {
            throw SifException::validation('Invalid ' . $field);
        }

        return $value;
    }

    private function money(mixed $value, string $field): string
    {
        if (!is_numeric($value)) {
            throw SifException::validation('Invalid ' . $field);
        }

        $amount = number_format((float) $value, 2, '.', '');
        if ((float) $amount <= 0.0) {
            throw SifException::validation('Invalid ' . $field);
        }

        return $amount;
    }
}
