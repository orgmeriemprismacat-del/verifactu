<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\NovicePromotionDestinationAdjustmentPolicy;
use Prisma\Sif\Domain\NovicePromotionRectificationEvidencePolicy;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

/**
 * UC-111 / DEC-20: INTERNAL preflight and fiscal REVIEW record for the FIRST
 * transfer of an already applied novice discount to a later course.
 *
 * Does NOT issue/rectify invoices, settle cash, change the initial application
 * or charge the original right a second time. A record in status
 * PENDING_FISCAL_REVIEW is neither confirmed transfer nor spendable value.
 *
 * An authenticated, authorized change-course backend must create the real
 * replacement operation and issue its rectificative BEFORE calling this.
 * Subsequent chained transfers and partially cheaper replacement courses are
 * deliberately rejected until their approved fiscal treatment is integrated.
 */
final class NovicePromotionCourseTransferReviewService
{
    public function __construct(
        private UuidGenerator $uuids,
        private NovicePromotionDestinationAdjustmentPolicy $adjustments = new NovicePromotionDestinationAdjustmentPolicy(),
        private NovicePromotionRectificationEvidencePolicy $evidence = new NovicePromotionRectificationEvidencePolicy()
    ) {
    }

    public function stageFirstTransfer(
        \PDO $db,
        string $uuidOriginalApplication,
        string $uuidNewOperation,
        string $uuidRectificativeInvoice,
        string $authorizedActorId,
        string $policyEvidenceRef,
        string $idempotencyKey
    ): array {
        if ($db->inTransaction()) {
            throw new \LogicException('Course transfer review must use an independent transaction.');
        }
        foreach ([$uuidOriginalApplication, $uuidNewOperation, $uuidRectificativeInvoice,
                  $authorizedActorId, $policyEvidenceRef, $idempotencyKey] as $field) {
            if (trim($field) === '') {
                throw SifException::validation('Missing authorized course transfer review reference.');
            }
        }
        if (strlen($idempotencyKey) > 140 || strlen($authorizedActorId) > 100
            || strlen($policyEvidenceRef) > 140
        ) {
            throw SifException::validation('Course transfer review reference exceeds its allowed length.');
        }

        $db->beginTransaction();
        try {
            $lookup = $this->one(
                $db,
                'SELECT UUID_ENTITLEMENT FROM novice_promotion_application
                 WHERE UUID_APPLICATION = ?',
                [$uuidOriginalApplication]
            );
            if ($lookup === null) {
                throw SifException::conflict('Promotional source for course transfer does not exist.');
            }

            $right = $this->one(
                $db,
                'SELECT e.UUID_ENTITLEMENT, e.STATUS, e.HOLDER_PARTY_KEY,
                        g.ORIGIN_UUID_OPERATION, v.STATUS AS VALIDATION_STATUS
                 FROM commercial_entitlement e
                 JOIN novice_promotion_grant g ON g.UUID_ENTITLEMENT = e.UUID_ENTITLEMENT
                 JOIN discount_validation v ON v.UUID_VALIDATION = g.UUID_VALIDATION
                 WHERE e.UUID_ENTITLEMENT = ? FOR UPDATE',
                [(string) $lookup['UUID_ENTITLEMENT']]
            );
            if ($right === null || $right['STATUS'] !== 'ACTIVE'
                || $right['VALIDATION_STATUS'] !== 'VALIDATED'
            ) {
                throw SifException::conflict('Original novice promotion is not valid for course transfer.');
            }

            $source = $this->one(
                $db,
                'SELECT * FROM novice_promotion_application
                 WHERE UUID_APPLICATION = ? FOR UPDATE',
                [$uuidOriginalApplication]
            );
            if ($source === null || $source['STATUS'] !== 'APPLIED'
                || (string) $source['UUID_ENTITLEMENT'] !== (string) $right['UUID_ENTITLEMENT']
                || trim((string) ($source['UUID_DESTINATION_FACTURA'] ?? '')) === ''
            ) {
                throw SifException::conflict('Only an already applied original promotion can be transferred.');
            }

            if ($this->one(
                $db,
                'SELECT UUID_DERIVED_BALANCE FROM novice_promotion_derived_balance
                 WHERE SOURCE_UUID_APPLICATION = ? FOR UPDATE',
                [$uuidOriginalApplication]
            ) !== null) {
                throw SifException::conflict('An application already converted to cancellation credit cannot be transferred.');
            }

            $oldOperation = $this->one(
                $db,
                'SELECT UUID_OPERATION, SOURCE_TYPE, SOURCE_ID, UUID_FACTURA, CURRENCY
                 FROM commercial_operation WHERE UUID_OPERATION = ? FOR UPDATE',
                [(string) $source['UUID_DESTINATION_OPERATION']]
            );
            $newOperation = $this->one(
                $db,
                'SELECT UUID_OPERATION, SOURCE_TYPE, SOURCE_ID, PRODUCT_TYPE,
                        CURRENCY, STATUS, GROSS_AMOUNT, DISCOUNT_AMOUNT,
                        NET_AMOUNT, PRICE_SNAPSHOT_JSON, UUID_FACTURA, UUID_INTENT,
                        CREATED_AT
                 FROM commercial_operation WHERE UUID_OPERATION = ? FOR UPDATE',
                [$uuidNewOperation]
            );
            if ($oldOperation === null || $newOperation === null
                || $uuidNewOperation === (string) $oldOperation['UUID_OPERATION']
                || $uuidNewOperation === (string) $right['ORIGIN_UUID_OPERATION']
                || $oldOperation['SOURCE_TYPE'] !== 'CURS'
                || $oldOperation['CURRENCY'] !== 'EUR'
                || (string) $oldOperation['UUID_FACTURA'] !== (string) $source['UUID_DESTINATION_FACTURA']
                || $newOperation['SOURCE_TYPE'] !== 'CURS'
                || $newOperation['PRODUCT_TYPE'] !== 'CURS'
                || $newOperation['CURRENCY'] !== 'EUR'
                || $newOperation['STATUS'] !== 'READY_FOR_PAYMENT'
                || trim((string) ($newOperation['UUID_FACTURA'] ?? '')) !== ''
                || trim((string) ($newOperation['UUID_INTENT'] ?? '')) !== ''
            ) {
                throw SifException::conflict('Replacement must be a distinct unbilled course of the same commercial currency.');
            }

            foreach ([(string) $oldOperation['UUID_OPERATION'], $uuidNewOperation] as $operationUuid) {
                $participants = $this->many(
                    $db,
                    "SELECT PARTY_KEY FROM commercial_operation_party
                     WHERE UUID_OPERATION = ? AND PARTY_ROLE = 'PARTICIPANT' FOR UPDATE",
                    [$operationUuid]
                );
                if (count($participants) !== 1
                    || (string) $participants[0]['PARTY_KEY'] !== (string) $right['HOLDER_PARTY_KEY']
                ) {
                    throw SifException::conflict('Course change cannot transfer promotion to another participant.');
                }
            }

            $snapshot = json_decode((string) $newOperation['PRICE_SNAPSHOT_JSON'], true, 512, JSON_THROW_ON_ERROR);
            $quote = is_array($snapshot) ? ($snapshot['novice_promotion_quote'] ?? null) : null;
            $ordinaryNetCents = $this->cents((string) $newOperation['NET_AMOUNT']);
            if (!is_array($quote) || ($quote['source'] ?? null) !== 'TRUSTED_SIF_PRICING'
                || ($quote['stage'] ?? null) !== 'BEFORE_PROMOTION'
                || !isset($quote['ordinary_net'])
                || $this->cents((string) $quote['ordinary_net']) !== $ordinaryNetCents
                || $this->cents((string) $newOperation['GROSS_AMOUNT'])
                    - $this->cents((string) $newOperation['DISCOUNT_AMOUNT']) !== $ordinaryNetCents
            ) {
                throw SifException::conflict('Replacement is missing trusted ordinary price before promotion.');
            }
            try {
                $plan = $this->adjustments->planCourseChange(
                    (string) $source['AMOUNT'],
                    (string) $newOperation['NET_AMOUNT']
                );
            } catch (\InvalidArgumentException $exception) {
                throw SifException::conflict('New course is cheaper than the promotion being transferred.');
            }

            $originalInvoice = $this->one(
                $db,
                'SELECT UUID_FACTURA, TIPUS_FACTURA, ESTAT_FACTURA, DATA_EMISSIO
                 FROM factura WHERE UUID_FACTURA = ? FOR UPDATE',
                [(string) $source['UUID_DESTINATION_FACTURA']]
            );
            $rectificative = $this->one(
                $db,
                'SELECT UUID_FACTURA, TIPUS_FACTURA, ESTAT_FACTURA, DATA_EMISSIO
                 FROM factura WHERE UUID_FACTURA = ? FOR UPDATE',
                [$uuidRectificativeInvoice]
            );
            $link = $this->one(
                $db,
                'SELECT UUID_FACTURA_RECTIFICADA, UUID_FACTURA_RECTIFICATIVA,
                        MOTIU, MODE_RECTIFICACIO
                 FROM factura_rectificacio
                 WHERE UUID_FACTURA_RECTIFICATIVA = ? AND UUID_FACTURA_RECTIFICADA = ?
                 FOR UPDATE',
                [$uuidRectificativeInvoice, (string) $source['UUID_DESTINATION_FACTURA']]
            );
            try {
                $this->evidence->assertCancellationReference(
                    $originalInvoice ?? [], $rectificative ?? [], $link ?? []
                );
            } catch (\InvalidArgumentException $exception) {
                throw SifException::conflict('Issued change-course rectificative does not match the original destination invoice.');
            }

            $previous = $this->one(
                $db,
                'SELECT * FROM novice_promotion_application_transfer
                 WHERE UUID_ORIGINAL_APPLICATION = ? FOR UPDATE',
                [$uuidOriginalApplication]
            );
            if ($previous !== null) {
                if ((string) $previous['IDEMPOTENCY_KEY'] !== $idempotencyKey
                    || (string) $previous['TO_UUID_OPERATION'] !== $uuidNewOperation
                    || (string) $previous['UUID_RECTIFICATIVE_FACTURA'] !== $uuidRectificativeInvoice
                    || (string) $previous['AMOUNT'] !== (string) $plan['transfer_promotion']
                    || (string) $previous['REVIEW_ACTOR_ID'] !== $authorizedActorId
                    || (string) $previous['POLICY_EVIDENCE_REF'] !== $policyEvidenceRef
                ) {
                    throw SifException::conflict('A different original course transfer already exists.');
                }
                $db->commit();
                return [
                    'uuid_transfer' => (string) $previous['UUID_TRANSFER'],
                    'status' => (string) $previous['STATUS'],
                    'transfer_promotion' => (string) $previous['AMOUNT'],
                    'idempotency_reused' => true,
                ];
            }

            $uuidTransfer = $this->uuids->generate();
            $stmt = $db->prepare(
                'INSERT INTO novice_promotion_application_transfer
                 (UUID_TRANSFER, ROOT_UUID_ENTITLEMENT, UUID_ORIGINAL_APPLICATION,
                  UUID_DERIVED_APPLICATION, PREVIOUS_UUID_TRANSFER, FROM_UUID_OPERATION,
                  TO_UUID_OPERATION, UUID_RECTIFICATIVE_FACTURA, AMOUNT, STATUS,
                  IDEMPOTENCY_KEY, REVIEW_ACTOR_ID, POLICY_EVIDENCE_REF)
                 VALUES (?, ?, ?, NULL, NULL, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $uuidTransfer, (string) $right['UUID_ENTITLEMENT'], $uuidOriginalApplication,
                (string) $source['UUID_DESTINATION_OPERATION'], $uuidNewOperation,
                $uuidRectificativeInvoice, (string) $plan['transfer_promotion'],
                'PENDING_FISCAL_REVIEW', $idempotencyKey,
                $authorizedActorId, $policyEvidenceRef,
            ]);

            $db->commit();
            return [
                'uuid_transfer' => $uuidTransfer,
                'status' => 'PENDING_FISCAL_REVIEW',
                'transfer_promotion' => (string) $plan['transfer_promotion'],
                'new_course_remaining_before_real_payments' => (string) $plan['new_course_remaining_before_real_payments'],
                'idempotency_reused' => false,
            ];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    private function one(\PDO $db, string $sql, array $args): ?array
    {
        $rows = $this->many($db, $sql, $args);
        return $rows[0] ?? null;
    }

    private function many(\PDO $db, string $sql, array $args): array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($args);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function cents(string $amount): int
    {
        if (!preg_match('/^(-?)(\d{1,10})(?:\.(\d{1,2}))?$/D', trim($amount), $m)) {
            throw SifException::validation('Invalid course transfer price.');
        }
        $cents = (int) $m[2] * 100 + (int) str_pad($m[3] ?? '', 2, '0');
        return $m[1] === '-' ? -$cents : $cents;
    }
}
