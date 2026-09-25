<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\NovicePromotionDestinationAdjustmentPolicy;
use Prisma\Sif\Domain\NovicePromotionRectificationEvidencePolicy;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

/**
 * UC-111 / DEC-21: stage a destination course cancellation for MANUAL
 * commercial+fiscal review, after a real rectificative is already persisted.
 *
 * NO derived spending right is activated, no original application is closed,
 * no original balance is replenished, no bank refund or payment is created.
 * The caller must have authenticated/authorized the secretary and obtained
 * the proposed split from the INTERNAL approved cancellation-price policy.
 * This service checks that the references and amounts do not contradict SIF
 * records, not that the proposed business decision has been approved.
 *
 * Only the FIRST directly applied novice promotion is supported in this cut.
 * A transferred course or a cancellation of a derived right needs a separate
 * link-aware workflow; do not treat either as the original destination.
 */
final class NovicePromotionDestinationCancellationReviewService
{
    public function __construct(
        private UuidGenerator $uuids,
        private NovicePromotionDestinationAdjustmentPolicy $adjustments = new NovicePromotionDestinationAdjustmentPolicy(),
        private NovicePromotionRectificationEvidencePolicy $fiscalEvidence = new NovicePromotionRectificationEvidencePolicy()
    ) {
    }

    public function stageOriginalApplicationReview(
        \PDO $db,
        string $uuidApplication,
        string $uuidRectificative,
        string $proposedEligiblePromotionalAmount,
        string $proposedEligibleCashAmount,
        string $authorizedActorId,
        string $policyEvidenceRef,
        string $idempotencyKey,
        ?\DateTimeImmutable $now = null
    ): array {
        if ($db->inTransaction()) {
            throw new \LogicException('UC-111 cancellation review requires its own transaction.');
        }
        if (trim($uuidApplication) === '' || trim($uuidRectificative) === ''
            || trim($authorizedActorId) === '' || trim($policyEvidenceRef) === ''
            || trim($idempotencyKey) === '' || strlen($idempotencyKey) > 140
            || strlen($authorizedActorId) > 100 || strlen($policyEvidenceRef) > 140
        ) {
            throw SifException::validation('Missing authenticated cancellation review reference.');
        }

        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $createdAt = $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $db->beginTransaction();
        try {
            // Lock ROOT entitlement before child application, consistent with
            // all novice grant/reservation writers. Recheck the lookup below.
            $lookup = $this->one(
                $db,
                'SELECT UUID_ENTITLEMENT FROM novice_promotion_application
                 WHERE UUID_APPLICATION = ?',
                [$uuidApplication]
            );
            if ($lookup === null) {
                throw SifException::conflict('Original promotional application does not exist.');
            }
            $root = $this->one(
                $db,
                'SELECT e.UUID_ENTITLEMENT, e.HOLDER_PARTY_KEY, e.STATUS,
                        g.ORIGIN_UUID_OPERATION, v.STATUS AS VALIDATION_STATUS
                 FROM commercial_entitlement e
                 JOIN novice_promotion_grant g ON g.UUID_ENTITLEMENT = e.UUID_ENTITLEMENT
                 JOIN discount_validation v ON v.UUID_VALIDATION = g.UUID_VALIDATION
                 WHERE e.UUID_ENTITLEMENT = ? FOR UPDATE',
                [(string) $lookup['UUID_ENTITLEMENT']]
            );
            if ($root === null
                || $root['STATUS'] !== 'ACTIVE'
                || $root['VALIDATION_STATUS'] !== 'VALIDATED'
            ) {
                throw SifException::conflict('Original novice promotion is not active and validated.');
            }

            $application = $this->one(
                $db,
                'SELECT * FROM novice_promotion_application
                 WHERE UUID_APPLICATION = ? FOR UPDATE',
                [$uuidApplication]
            );
            if ($application === null
                || (string) $application['UUID_ENTITLEMENT'] !== (string) $root['UUID_ENTITLEMENT']
                || $application['STATUS'] !== 'APPLIED'
                || trim((string) ($application['UUID_DESTINATION_FACTURA'] ?? '')) === ''
            ) {
                throw SifException::conflict('Only a completed original course application can enter cancellation review.');
            }

            $destinationUuid = (string) $application['UUID_DESTINATION_OPERATION'];
            $destination = $this->one(
                $db,
                'SELECT UUID_OPERATION, SOURCE_TYPE, SOURCE_ID, PRODUCT_TYPE,
                        UUID_FACTURA, CURRENCY
                 FROM commercial_operation WHERE UUID_OPERATION = ? FOR UPDATE',
                [$destinationUuid]
            );
            if ($destination === null || $destination['SOURCE_TYPE'] !== 'CURS'
                || $destination['PRODUCT_TYPE'] !== 'CURS'
                || $destination['CURRENCY'] !== 'EUR'
                || (string) $destination['UUID_FACTURA'] !== (string) $application['UUID_DESTINATION_FACTURA']
                || $destinationUuid === (string) $root['ORIGIN_UUID_OPERATION']
            ) {
                throw SifException::conflict('Destination does not match the original discounted enrollment.');
            }

            $people = $this->many(
                $db,
                "SELECT PARTY_KEY FROM commercial_operation_party
                 WHERE UUID_OPERATION = ? AND PARTY_ROLE = 'PARTICIPANT' FOR UPDATE",
                [$destinationUuid]
            );
            if (count($people) !== 1
                || (string) $people[0]['PARTY_KEY'] !== (string) $root['HOLDER_PARTY_KEY']
            ) {
                throw SifException::conflict('Destination participant differs from the promotional holder.');
            }

            if ($this->one(
                $db,
                'SELECT UUID_TRANSFER FROM novice_promotion_application_transfer
                 WHERE UUID_ORIGINAL_APPLICATION = ? LIMIT 1 FOR UPDATE',
                [$uuidApplication]
            ) !== null) {
                throw SifException::conflict('Transferred promotional use must be reviewed at its current course, not its historical origin.');
            }

            // Both same-key retries and other-key duplicates are blocked.
            // A previous pending review is reused only when every proposed
            // amount/reference matches the immutable initial policy snapshot.
            $previous = $this->one(
                $db,
                'SELECT * FROM novice_promotion_derived_balance
                 WHERE SOURCE_UUID_APPLICATION = ? FOR UPDATE',
                [$uuidApplication]
            );

            $originalInvoice = $this->one(
                $db,
                'SELECT UUID_FACTURA, TIPUS_FACTURA, ESTAT_FACTURA,
                        ESTAT_COBRAMENT, TOTAL, DATA_EMISSIO
                 FROM factura WHERE UUID_FACTURA = ? FOR UPDATE',
                [(string) $application['UUID_DESTINATION_FACTURA']]
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
                 WHERE UUID_FACTURA_RECTIFICATIVA = ? AND UUID_FACTURA_RECTIFICADA = ?
                 FOR UPDATE',
                [$uuidRectificative, (string) $application['UUID_DESTINATION_FACTURA']]
            );
            try {
                $this->fiscalEvidence->assertCancellationReference(
                    $originalInvoice ?? [], $rectificative ?? [], $link ?? []
                );
            } catch (\InvalidArgumentException $exception) {
                throw SifException::conflict('Destination rectificative is not linked to the original issued invoice.');
            }

            $sourceId = trim((string) ($destination['SOURCE_ID'] ?? ''));
            if ($sourceId === '' || !ctype_digit($sourceId)
                || $this->one(
                    $db,
                    "SELECT ID FROM fact_rels
                     WHERE UUID_FACTURA = ? AND SOURCE_TYPE = 'INSCRIPCIO'
                       AND SOURCE_ID = ? LIMIT 1",
                    [(string) $originalInvoice['UUID_FACTURA'], (int) $sourceId]
                ) === null
            ) {
                throw SifException::conflict('Original destination invoice is not linked to its enrolled course.');
            }

            $promotionCents = $this->cents((string) $application['AMOUNT']);
            $cash = $this->one(
                $db,
                "SELECT COALESCE(SUM(CASE
                    WHEN pt.TIPUS_MOVIMENT = 'CHARGE' THEN pa.IMPORT_ASSIGNAT
                    WHEN pt.TIPUS_MOVIMENT = 'REFUND' THEN -pa.IMPORT_ASSIGNAT
                    ELSE 0 END), 0) AS NET_CASH
                 FROM payment_allocation pa
                 JOIN payment_transaction pt ON pt.UUID_PAYMENT = pa.UUID_PAYMENT
                 WHERE pa.UUID_FACTURA = ? AND pt.ESTAT = 'CONFIRMED'",
                [(string) $originalInvoice['UUID_FACTURA']]
            );
            $cashCents = $this->cents((string) ($cash['NET_CASH'] ?? '0.00'));
            $invoiceCents = $this->cents((string) $originalInvoice['TOTAL']);
            $ordinaryCents = $this->cents((string) $application['DESTINATION_ORDINARY_NET_AMOUNT']);
            if ($cashCents < 0 || $cashCents !== $invoiceCents
                || $invoiceCents < 0
                || $cashCents + $promotionCents !== $ordinaryCents
            ) {
                throw SifException::conflict('Original external money and promotional application are not reconciled.');
            }

            try {
                $plan = $this->adjustments->planCancellation(
                    (string) $application['AMOUNT'],
                    $this->money($cashCents),
                    $proposedEligiblePromotionalAmount,
                    $proposedEligibleCashAmount,
                    $now
                );
            } catch (\InvalidArgumentException $exception) {
                throw SifException::conflict('Proposed cancellation credit exceeds documented provenance.');
            }
            if (!$plan['creates_promotional_derived_right']) {
                throw SifException::conflict('Cash-only cancellation must follow the existing cash refund/credit circuit.');
            }

            $policySnapshot = [
                'source_application' => $uuidApplication,
                'source_invoice' => (string) $originalInvoice['UUID_FACTURA'],
                'rectificative_invoice' => $uuidRectificative,
                'proposed_promotional_amount' => (string) $plan['promotional_derived_amount'],
                'proposed_cash_amount' => (string) $plan['cash_refund_or_credit_eligible'],
                'original_cash_reconciled' => $this->money($cashCents),
                'policy_evidence_ref' => $policyEvidenceRef,
                'recorded_by' => $authorizedActorId,
                'recorded_at' => $createdAt,
                'state' => 'PENDING_FISCAL_REVIEW',
            ];

            if ($previous !== null) {
                $saved = json_decode((string) $previous['POLICY_SNAPSHOT_JSON'], true, 512, JSON_THROW_ON_ERROR);
                if ((string) $previous['IDEMPOTENCY_KEY'] !== $idempotencyKey
                    || (string) $previous['UUID_RECTIFICATIVE_FACTURA'] !== $uuidRectificative
                    || (string) $previous['PROMOTIONAL_ORIGIN_AMOUNT']
                        !== $plan['promotional_derived_amount']
                    || !is_array($saved)
                    || ($saved['proposed_cash_amount'] ?? null) !== $plan['cash_refund_or_credit_eligible']
                    || ($saved['policy_evidence_ref'] ?? null) !== $policyEvidenceRef
                    || ($saved['recorded_by'] ?? null) !== $authorizedActorId
                ) {
                    throw SifException::conflict('Cancellation review already exists with different authorization evidence.');
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
                 (UUID_DERIVED_BALANCE, ROOT_UUID_ENTITLEMENT, PARENT_UUID_DERIVED_BALANCE,
                  SOURCE_UUID_APPLICATION, SOURCE_UUID_DERIVED_APPLICATION,
                  UUID_DESTINATION_OPERATION, UUID_RECTIFICATIVE_FACTURA,
                  HOLDER_PARTY_KEY, PROMOTIONAL_ORIGIN_AMOUNT, AVAILABLE_PROMOTIONAL_AMOUNT,
                  STATUS, ISSUED_AT, EXPIRES_AT, POLICY_SNAPSHOT_JSON, IDEMPOTENCY_KEY)
                 VALUES (?, ?, NULL, ?, NULL, ?, ?, ?, ?, 0.00, ?, NULL, NULL, ?, ?)'
            );
            $stmt->execute([
                $uuidDerived,
                (string) $root['UUID_ENTITLEMENT'],
                $uuidApplication,
                $destinationUuid,
                $uuidRectificative,
                (string) $root['HOLDER_PARTY_KEY'],
                (string) $plan['promotional_derived_amount'],
                'PENDING_FISCAL_REVIEW',
                json_encode($policySnapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                $idempotencyKey,
            ]);

            $db->commit();
            return [
                'uuid_derived_balance' => $uuidDerived,
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

    private function one(\PDO $db, string $sql, array $parameters): ?array
    {
        $rows = $this->many($db, $sql, $parameters);
        return $rows[0] ?? null;
    }

    private function many(\PDO $db, string $sql, array $parameters): array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($parameters);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function cents(string $amount): int
    {
        if (!preg_match('/^(-?)(\d{1,10})(?:\.(\d{1,2}))?$/D', trim($amount), $match)) {
            throw SifException::validation('Invalid amount in fiscal cancellation review.');
        }
        $cents = (int) $match[2] * 100 + (int) str_pad($match[3] ?? '', 2, '0');
        return $match[1] === '-' ? -$cents : $cents;
    }

    private function money(int $cents): string
    {
        if ($cents < 0) {
            throw SifException::validation('Negative amount cannot be used as a cash credit.');
        }
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
