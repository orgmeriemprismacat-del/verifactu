<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\NovicePromotionDestinationAdjustmentPolicy;
use Prisma\Sif\Domain\NovicePromotionRectificationEvidencePolicy;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

/**
 * UC-111 / DEC-18/21/23:
 * Stage cancellation of the CURRENT destination reached through a CONFIRMED
 * first transfer. This fixes lineage: the new derived balance points to the
 * transfer, not back to the historical pre-transfer application.
 *
 * It creates ONLY PENDING_FISCAL_REVIEW evidence. No spendable balance,
 * refund, credit_balance, bank movement or fiscal invoice is created here.
 * The caller must be an authenticated internal backoffice workflow.
 *
 * This cut supports a CONFIRMED first transfer sourced from the original
 * novice application. Successive transfers and transfer sourced from an
 * already-derived application remain a separate integration.
 */
final class NovicePromotionTransferredDestinationCancellationReviewService
{
    public function __construct(
        private UuidGenerator $uuids = new UuidGenerator(),
        private NovicePromotionDestinationAdjustmentPolicy $adjustments = new NovicePromotionDestinationAdjustmentPolicy(),
        private NovicePromotionRectificationEvidencePolicy $fiscal = new NovicePromotionRectificationEvidencePolicy()
    ) {
    }

    public function stageFirstTransferredDestinationReview(
        \PDO $db,
        string $uuidTransfer,
        string $uuidRectificative,
        string $proposedEligiblePromotionalAmount,
        string $proposedEligibleCashAmount,
        string $authorizedActorId,
        string $policyEvidenceRef,
        string $idempotencyKey,
        ?\DateTimeImmutable $now = null
    ): array {
        if ($db->inTransaction()) {
            throw new \LogicException('Transferred destination cancellation review requires its own transaction.');
        }
        foreach ([
            $uuidTransfer, $uuidRectificative, $authorizedActorId,
            $policyEvidenceRef, $idempotencyKey,
        ] as $value) {
            if (trim($value) === '') {
                throw SifException::validation('Missing transferred cancellation review reference.');
            }
        }
        if (strlen($authorizedActorId) > 100
            || strlen($policyEvidenceRef) > 140
            || strlen($idempotencyKey) > 140
        ) {
            throw SifException::validation('Transferred cancellation review reference is too long.');
        }

        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $timestamp = $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $db->beginTransaction();
        try {
            $lookup = $this->one(
                $db,
                'SELECT ROOT_UUID_ENTITLEMENT FROM novice_promotion_application_transfer
                 WHERE UUID_TRANSFER = ?',
                [$uuidTransfer]
            );
            if ($lookup === null) {
                throw SifException::conflict('Confirmed promotional transfer was not found.');
            }

            $root = $this->one(
                $db,
                'SELECT e.UUID_ENTITLEMENT, e.STATUS, e.HOLDER_PARTY_KEY,
                        g.ORIGIN_UUID_OPERATION, v.STATUS AS VALIDATION_STATUS
                 FROM commercial_entitlement e
                 JOIN novice_promotion_grant g ON g.UUID_ENTITLEMENT = e.UUID_ENTITLEMENT
                 JOIN discount_validation v ON v.UUID_VALIDATION = g.UUID_VALIDATION
                 WHERE e.UUID_ENTITLEMENT = ? FOR UPDATE',
                [(string) $lookup['ROOT_UUID_ENTITLEMENT']]
            );
            if ($root === null || $root['STATUS'] !== 'ACTIVE'
                || $root['VALIDATION_STATUS'] !== 'VALIDATED'
            ) {
                throw SifException::conflict('Novice root is not active for transferred cancellation review.');
            }

            $transfer = $this->one(
                $db,
                'SELECT * FROM novice_promotion_application_transfer
                 WHERE UUID_TRANSFER = ? FOR UPDATE',
                [$uuidTransfer]
            );
            if ($transfer === null
                || (string) $transfer['ROOT_UUID_ENTITLEMENT'] !== (string) $root['UUID_ENTITLEMENT']
                || $transfer['STATUS'] !== 'CONFIRMED'
                || $transfer['PREVIOUS_UUID_TRANSFER'] !== null
                || $transfer['UUID_DERIVED_APPLICATION'] !== null
                || trim((string) ($transfer['UUID_ORIGINAL_APPLICATION'] ?? '')) === ''
                || trim((string) ($transfer['UUID_DESTINATION_FACTURA'] ?? '')) === ''
            ) {
                throw SifException::conflict('Only the confirmed first original-course transfer is supported here.');
            }

            if ($this->one(
                $db,
                "SELECT UUID_TRANSFER FROM novice_promotion_application_transfer
                 WHERE PREVIOUS_UUID_TRANSFER = ? AND STATUS <> 'CANCELLED'
                 FOR UPDATE",
                [$uuidTransfer]
            ) !== null) {
                throw SifException::conflict('Cancellation must follow the latest course in the transfer chain.');
            }

            $destination = $this->one(
                $db,
                'SELECT UUID_OPERATION, SOURCE_TYPE, SOURCE_ID, PRODUCT_TYPE,
                        CURRENCY, UUID_FACTURA, STATUS
                 FROM commercial_operation WHERE UUID_OPERATION = ? FOR UPDATE',
                [(string) $transfer['TO_UUID_OPERATION']]
            );
            if ($destination === null
                || $destination['SOURCE_TYPE'] !== 'CURS'
                || $destination['PRODUCT_TYPE'] !== 'CURS'
                || $destination['CURRENCY'] !== 'EUR'
                || (string) $destination['UUID_FACTURA'] !== (string) $transfer['UUID_DESTINATION_FACTURA']
                || !in_array((string) $destination['STATUS'], ['PAID', 'COMPLETED', 'CANCELLED'], true)
            ) {
                throw SifException::conflict('Transferred destination no longer matches its confirmed final invoice.');
            }

            $participants = $this->many(
                $db,
                "SELECT PARTY_KEY FROM commercial_operation_party
                 WHERE UUID_OPERATION = ? AND PARTY_ROLE = 'PARTICIPANT' FOR UPDATE",
                [(string) $destination['UUID_OPERATION']]
            );
            if (count($participants) !== 1
                || (string) $participants[0]['PARTY_KEY'] !== (string) $root['HOLDER_PARTY_KEY']
            ) {
                throw SifException::conflict('Transferred destination no longer belongs to the novice holder.');
            }

            $previous = $this->one(
                $db,
                'SELECT * FROM novice_promotion_derived_balance
                 WHERE SOURCE_UUID_TRANSFER = ? FOR UPDATE',
                [$uuidTransfer]
            );

            $originalInvoice = $this->one(
                $db,
                'SELECT UUID_FACTURA, TIPUS_FACTURA, ESTAT_FACTURA,
                        ESTAT_COBRAMENT, TOTAL, DATA_EMISSIO
                 FROM factura WHERE UUID_FACTURA = ? FOR UPDATE',
                [(string) $transfer['UUID_DESTINATION_FACTURA']]
            );
            $rectificative = $this->one(
                $db,
                'SELECT UUID_FACTURA, TIPUS_FACTURA, ESTAT_FACTURA, DATA_EMISSIO
                 FROM factura WHERE UUID_FACTURA = ? FOR UPDATE',
                [$uuidRectificative]
            );
            $link = $this->one(
                $db,
                'SELECT UUID_FACTURA_RECTIFICADA, UUID_FACTURA_RECTIFICATIVA,
                        MOTIU, MODE_RECTIFICACIO
                 FROM factura_rectificacio
                 WHERE UUID_FACTURA_RECTIFICATIVA = ?
                   AND UUID_FACTURA_RECTIFICADA = ? FOR UPDATE',
                [$uuidRectificative, (string) $transfer['UUID_DESTINATION_FACTURA']]
            );
            try {
                $this->fiscal->assertCancellationReference(
                    $originalInvoice ?? [], $rectificative ?? [], $link ?? []
                );
            } catch (\InvalidArgumentException $exception) {
                throw SifException::conflict('Transferred destination rectificative does not match its final invoice.');
            }

            $sourceId = trim((string) ($destination['SOURCE_ID'] ?? ''));
            if ($sourceId === '' || !ctype_digit($sourceId)
                || $this->one(
                    $db,
                    "SELECT ID FROM fact_rels WHERE UUID_FACTURA = ?
                     AND SOURCE_TYPE = 'INSCRIPCIO' AND SOURCE_ID = ? LIMIT 1",
                    [(string) $transfer['UUID_DESTINATION_FACTURA'], (int) $sourceId]
                ) === null
            ) {
                throw SifException::conflict('Transferred destination invoice is not linked to its enrollment.');
            }

            $promotion = $this->cents((string) $transfer['AMOUNT']);
            $ordinaryNet = $this->cents((string) $transfer['ORDINARY_NET_BEFORE_PROMOTION']);
            $finalNet = $this->cents((string) $transfer['FINAL_NET_AMOUNT']);
            $invoiceTotal = $this->cents((string) ($originalInvoice['TOTAL'] ?? ''));
            $settlement = $this->one(
                $db,
                "SELECT COALESCE(SUM(CASE
                    WHEN pt.TIPUS_MOVIMENT = 'CHARGE' THEN pa.IMPORT_ASSIGNAT
                    WHEN pt.TIPUS_MOVIMENT = 'REFUND' THEN -pa.IMPORT_ASSIGNAT
                    ELSE 0 END), 0) AS NET_CASH
                 FROM payment_allocation pa
                 JOIN payment_transaction pt ON pt.UUID_PAYMENT = pa.UUID_PAYMENT
                 WHERE pa.UUID_FACTURA = ? AND pt.ESTAT = 'CONFIRMED'",
                [(string) $transfer['UUID_DESTINATION_FACTURA']]
            );
            $cash = $this->cents((string) ($settlement['NET_CASH'] ?? '0.00'));
            if ($promotion <= 0 || $finalNet < 0
                || $ordinaryNet !== $promotion + $finalNet
                || $invoiceTotal !== $finalNet
                || $cash !== $finalNet
            ) {
                throw SifException::conflict('Transferred promotion and external cash no longer reconcile.');
            }

            try {
                $plan = $this->adjustments->planCancellation(
                    (string) $transfer['AMOUNT'],
                    $this->money($cash),
                    $proposedEligiblePromotionalAmount,
                    $proposedEligibleCashAmount,
                    $now
                );
            } catch (\InvalidArgumentException $exception) {
                throw SifException::conflict('Transferred cancellation proposal exceeds documented value.');
            }
            if (!$plan['creates_promotional_derived_right']) {
                throw SifException::conflict('Cash-only transferred cancellation belongs to the cash refund/credit circuit.');
            }

            $snapshot = [
                'source_transfer' => $uuidTransfer,
                'source_operation' => (string) $transfer['TO_UUID_OPERATION'],
                'source_invoice' => (string) $transfer['UUID_DESTINATION_FACTURA'],
                'rectificative_invoice' => $uuidRectificative,
                'proposed_promotional_amount' => (string) $plan['promotional_derived_amount'],
                'proposed_cash_amount' => (string) $plan['cash_refund_or_credit_eligible'],
                'original_cash_reconciled' => $this->money($cash),
                'policy_evidence_ref' => $policyEvidenceRef,
                'recorded_by' => $authorizedActorId,
                'recorded_at' => $timestamp,
                'state' => 'PENDING_FISCAL_REVIEW',
            ];

            if ($previous !== null) {
                $saved = json_decode(
                    (string) $previous['POLICY_SNAPSHOT_JSON'],
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
                if ((string) $previous['IDEMPOTENCY_KEY'] !== $idempotencyKey
                    || (string) $previous['UUID_RECTIFICATIVE_FACTURA'] !== $uuidRectificative
                    || (string) $previous['PROMOTIONAL_ORIGIN_AMOUNT']
                        !== (string) $plan['promotional_derived_amount']
                    || !is_array($saved)
                    || ($saved['proposed_cash_amount'] ?? null)
                        !== (string) $plan['cash_refund_or_credit_eligible']
                    || ($saved['policy_evidence_ref'] ?? null) !== $policyEvidenceRef
                    || ($saved['recorded_by'] ?? null) !== $authorizedActorId
                ) {
                    throw SifException::conflict('Transferred cancellation review already exists with different evidence.');
                }
                $db->commit();
                return [
                    'uuid_derived_balance' => (string) $previous['UUID_DERIVED_BALANCE'],
                    'status' => (string) $previous['STATUS'],
                    'proposed_promotional_amount' => (string) $previous['PROMOTIONAL_ORIGIN_AMOUNT'],
                    'idempotency_reused' => true,
                ];
            }

            $uuidDerived = $this->uuids->generate();
            $stmt = $db->prepare(
                'INSERT INTO novice_promotion_derived_balance
                 (UUID_DERIVED_BALANCE, ROOT_UUID_ENTITLEMENT,
                  PARENT_UUID_DERIVED_BALANCE, SOURCE_UUID_APPLICATION,
                  SOURCE_UUID_DERIVED_APPLICATION, SOURCE_UUID_TRANSFER,
                  UUID_DESTINATION_OPERATION, UUID_RECTIFICATIVE_FACTURA,
                  HOLDER_PARTY_KEY, PROMOTIONAL_ORIGIN_AMOUNT,
                  AVAILABLE_PROMOTIONAL_AMOUNT, STATUS, ISSUED_AT, EXPIRES_AT,
                  POLICY_SNAPSHOT_JSON, IDEMPOTENCY_KEY)
                 VALUES (?, ?, NULL, NULL, NULL, ?, ?, ?, ?, ?, 0.00,
                         ?, NULL, NULL, ?, ?)'
            );
            $stmt->execute([
                $uuidDerived,
                (string) $root['UUID_ENTITLEMENT'],
                $uuidTransfer,
                (string) $transfer['TO_UUID_OPERATION'],
                $uuidRectificative,
                (string) $root['HOLDER_PARTY_KEY'],
                (string) $plan['promotional_derived_amount'],
                'PENDING_FISCAL_REVIEW',
                json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                $idempotencyKey,
            ]);

            $db->commit();
            return [
                'uuid_derived_balance' => $uuidDerived,
                'source_uuid_transfer' => $uuidTransfer,
                'status' => 'PENDING_FISCAL_REVIEW',
                'proposed_promotional_amount' => (string) $plan['promotional_derived_amount'],
                'idempotency_reused' => false,
            ];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    private function cents(string $amount): int
    {
        if (!preg_match('/^(-?)(\d{1,10})(?:\.(\d{1,2}))?$/D', trim($amount), $match)) {
            throw SifException::validation('Invalid transferred cancellation amount.');
        }
        $cents = (int) $match[2] * 100 + (int) str_pad($match[3] ?? '', 2, '0');
        return $match[1] === '-' ? -$cents : $cents;
    }

    private function money(int $cents): string
    {
        if ($cents < 0) {
            throw SifException::validation('Negative transferred cancellation cash is not valid.');
        }
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function one(\PDO $db, string $sql, array $params): ?array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function many(\PDO $db, string $sql, array $params): array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
