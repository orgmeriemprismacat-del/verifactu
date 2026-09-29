<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\NovicePromotionDestinationAdjustmentPolicy;
use Prisma\Sif\Domain\NovicePromotionRectificationEvidencePolicy;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

/**
 * UC-111 / DEC-18: stage a transfer from the CURRENT promotional exposure.
 *
 * Supported current sources:
 * - an APPLIED novice_promotion_derived_application;
 * - a CONFIRMED novice_promotion_application_transfer with no active child.
 *
 * No source balance is re-credited and no new promotional consumption occurs.
 * This service only writes PENDING_FISCAL_REVIEW after the source course has a
 * real issued rectificative. Final confirmation is a separate service.
 */
final class NovicePromotionSuccessiveTransferReviewService
{
    public function __construct(
        private UuidGenerator $uuids = new UuidGenerator(),
        private NovicePromotionDestinationAdjustmentPolicy $adjustments = new NovicePromotionDestinationAdjustmentPolicy(),
        private NovicePromotionRectificationEvidencePolicy $fiscal = new NovicePromotionRectificationEvidencePolicy()
    ) {
    }

    public function stageFromDerivedApplication(
        \PDO $db,
        string $uuidDerivedApplication,
        string $uuidNewOperation,
        string $uuidRectificative,
        string $authorizedActorId,
        string $policyEvidenceRef,
        string $idempotencyKey
    ): array {
        return $this->stage(
            $db,
            'DERIVED_APPLICATION',
            $uuidDerivedApplication,
            $uuidNewOperation,
            $uuidRectificative,
            $authorizedActorId,
            $policyEvidenceRef,
            $idempotencyKey
        );
    }

    public function stageFromConfirmedTransfer(
        \PDO $db,
        string $uuidPreviousTransfer,
        string $uuidNewOperation,
        string $uuidRectificative,
        string $authorizedActorId,
        string $policyEvidenceRef,
        string $idempotencyKey
    ): array {
        return $this->stage(
            $db,
            'PREVIOUS_TRANSFER',
            $uuidPreviousTransfer,
            $uuidNewOperation,
            $uuidRectificative,
            $authorizedActorId,
            $policyEvidenceRef,
            $idempotencyKey
        );
    }

    private function stage(
        \PDO $db,
        string $sourceKind,
        string $sourceId,
        string $uuidNewOperation,
        string $uuidRectificative,
        string $authorizedActorId,
        string $policyEvidenceRef,
        string $idempotencyKey
    ): array {
        if ($db->inTransaction()) {
            throw new \LogicException('Successive transfer review requires its own SIF transaction.');
        }
        foreach ([
            $sourceId, $uuidNewOperation, $uuidRectificative,
            $authorizedActorId, $policyEvidenceRef, $idempotencyKey,
        ] as $value) {
            if (trim($value) === '') {
                throw SifException::validation('Missing successive course-transfer review reference.');
            }
        }
        if (!in_array($sourceKind, ['DERIVED_APPLICATION', 'PREVIOUS_TRANSFER'], true)
            || strlen($authorizedActorId) > 100
            || strlen($policyEvidenceRef) > 140
            || strlen($idempotencyKey) > 140
        ) {
            throw SifException::validation('Invalid successive transfer review request.');
        }

        $db->beginTransaction();
        try {
            $rootUuid = $this->sourceRootUuid($db, $sourceKind, $sourceId);
            if ($rootUuid === null) {
                throw SifException::conflict('Current promotional transfer source does not exist.');
            }
            $root = $this->lockRoot($db, $rootUuid);
            $source = $this->lockSource($db, $sourceKind, $sourceId);
            if ((string) $source['root_uuid'] !== (string) $root['UUID_ENTITLEMENT']
                || (string) $source['holder_party_key'] !== (string) $root['HOLDER_PARTY_KEY']
            ) {
                throw SifException::conflict('Current transfer source holder differs from the novice root.');
            }

            if ($sourceKind === 'PREVIOUS_TRANSFER') {
                if ($this->one(
                    $db,
                    "SELECT UUID_TRANSFER FROM novice_promotion_application_transfer
                     WHERE PREVIOUS_UUID_TRANSFER = ? AND STATUS <> 'CANCELLED' FOR UPDATE",
                    [$sourceId]
                ) !== null) {
                    throw SifException::conflict('Only the latest confirmed transfer can be moved again.');
                }
                if ($this->one(
                    $db,
                    'SELECT UUID_DERIVED_BALANCE FROM novice_promotion_derived_balance
                     WHERE SOURCE_UUID_TRANSFER = ? FOR UPDATE',
                    [$sourceId]
                ) !== null) {
                    throw SifException::conflict('Current transfer already has a cancellation-derived descendant.');
                }
            } else {
                if ($this->one(
                    $db,
                    'SELECT UUID_DERIVED_BALANCE FROM novice_promotion_derived_balance
                     WHERE SOURCE_UUID_DERIVED_APPLICATION = ? FOR UPDATE',
                    [$sourceId]
                ) !== null) {
                    throw SifException::conflict('Derived application already became a cancellation-derived balance.');
                }
                if ($this->one(
                    $db,
                    "SELECT UUID_TRANSFER FROM novice_promotion_application_transfer
                     WHERE UUID_DERIVED_APPLICATION = ? AND STATUS <> 'CANCELLED' FOR UPDATE",
                    [$sourceId]
                ) !== null) {
                    throw SifException::conflict('Derived application already has a current transfer.');
                }
            }

            $oldOperation = $this->one(
                $db,
                'SELECT UUID_OPERATION, SOURCE_TYPE, SOURCE_ID, PRODUCT_TYPE,
                        CURRENCY, UUID_FACTURA, CREATED_AT
                 FROM commercial_operation WHERE UUID_OPERATION = ? FOR UPDATE',
                [$source['operation_uuid']]
            );
            $newOperation = $this->one(
                $db,
                'SELECT * FROM commercial_operation WHERE UUID_OPERATION = ? FOR UPDATE',
                [$uuidNewOperation]
            );
            if ($oldOperation === null || $newOperation === null
                || $oldOperation['SOURCE_TYPE'] !== 'CURS'
                || $oldOperation['PRODUCT_TYPE'] !== 'CURS'
                || $oldOperation['CURRENCY'] !== 'EUR'
                || (string) $oldOperation['UUID_FACTURA'] !== $source['invoice_uuid']
                || $newOperation['SOURCE_TYPE'] !== 'CURS'
                || $newOperation['PRODUCT_TYPE'] !== 'CURS'
                || $newOperation['CURRENCY'] !== 'EUR'
                || $newOperation['STATUS'] !== 'READY_FOR_PAYMENT'
                || trim((string) ($newOperation['UUID_FACTURA'] ?? '')) !== ''
                || trim((string) ($newOperation['UUID_INTENT'] ?? '')) !== ''
                || (string) $newOperation['UUID_OPERATION'] === (string) $oldOperation['UUID_OPERATION']
                || (string) $newOperation['UUID_OPERATION'] === (string) $root['ORIGIN_UUID_OPERATION']
                || (string) $newOperation['CREATED_AT'] < (string) $oldOperation['CREATED_AT']
            ) {
                throw SifException::conflict('Replacement is not a valid later unbilled course.');
            }

            foreach ([$source['operation_uuid'], $uuidNewOperation] as $operationUuid) {
                $people = $this->many(
                    $db,
                    "SELECT PARTY_KEY FROM commercial_operation_party
                     WHERE UUID_OPERATION = ? AND PARTY_ROLE = 'PARTICIPANT' FOR UPDATE",
                    [$operationUuid]
                );
                if (count($people) !== 1
                    || (string) $people[0]['PARTY_KEY'] !== (string) $root['HOLDER_PARTY_KEY']
                ) {
                    throw SifException::conflict('Successive course transfer cannot change the promotion holder.');
                }
            }

            if ($this->destinationAlreadyUsesPromotion($db, $uuidNewOperation)) {
                throw SifException::conflict('Replacement course already has a UC-111 promotional path.');
            }

            $snapshot = json_decode(
                (string) $newOperation['PRICE_SNAPSHOT_JSON'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );
            $quote = is_array($snapshot) ? ($snapshot['novice_promotion_transfer_quote'] ?? null) : null;
            $ordinaryNet = $this->cents((string) $newOperation['NET_AMOUNT']);
            if (!is_array($quote)
                || ($quote['source'] ?? '') !== 'TRUSTED_SIF_PRICING'
                || ($quote['stage'] ?? '') !== 'BEFORE_TRANSFERRED_PROMOTION'
                || ($quote['source_kind'] ?? '') !== $sourceKind
                || ($quote['source_id'] ?? '') !== $sourceId
                || !isset($quote['ordinary_net'])
                || $this->cents((string) $quote['ordinary_net']) !== $ordinaryNet
                || $ordinaryNet <= 0
                || $this->cents((string) $newOperation['GROSS_AMOUNT'])
                    - $this->cents((string) $newOperation['DISCOUNT_AMOUNT']) !== $ordinaryNet
            ) {
                throw SifException::conflict('Replacement lacks a trusted source-aware transfer quote.');
            }

            try {
                $plan = $this->adjustments->planCourseChange(
                    $source['amount'],
                    $this->money($ordinaryNet)
                );
            } catch (\InvalidArgumentException $exception) {
                throw SifException::conflict('Replacement course cannot carry the full current promotional attribution.');
            }

            $sourceInvoice = $this->one(
                $db,
                'SELECT UUID_FACTURA, TIPUS_FACTURA, ESTAT_FACTURA, DATA_EMISSIO
                 FROM factura WHERE UUID_FACTURA = ? FOR UPDATE',
                [$source['invoice_uuid']]
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
                [$uuidRectificative, $source['invoice_uuid']]
            );
            try {
                $this->fiscal->assertCancellationReference(
                    $sourceInvoice ?? [], $rectificative ?? [], $link ?? []
                );
            } catch (\InvalidArgumentException $exception) {
                throw SifException::conflict('Successive transfer rectificative does not match the current course invoice.');
            }

            $previous = $this->existingTransferForSource($db, $sourceKind, $sourceId);
            if ($previous !== null) {
                if ((string) $previous['IDEMPOTENCY_KEY'] !== $idempotencyKey
                    || (string) $previous['TO_UUID_OPERATION'] !== $uuidNewOperation
                    || (string) $previous['UUID_RECTIFICATIVE_FACTURA'] !== $uuidRectificative
                    || (string) $previous['AMOUNT'] !== (string) $plan['transfer_promotion']
                    || (string) $previous['REVIEW_ACTOR_ID'] !== $authorizedActorId
                    || (string) $previous['POLICY_EVIDENCE_REF'] !== $policyEvidenceRef
                ) {
                    throw SifException::conflict('Current promotional source already has a different transfer review.');
                }
                $db->commit();
                return [
                    'uuid_transfer' => (string) $previous['UUID_TRANSFER'],
                    'source_kind' => $sourceKind,
                    'source_id' => $sourceId,
                    'status' => (string) $previous['STATUS'],
                    'transfer_promotion' => (string) $previous['AMOUNT'],
                    'idempotency_reused' => true,
                ];
            }

            $uuidTransfer = $this->uuids->generate();
            $stmt = $db->prepare(
                'INSERT INTO novice_promotion_application_transfer
                 (UUID_TRANSFER, ROOT_UUID_ENTITLEMENT, UUID_ORIGINAL_APPLICATION,
                  UUID_DERIVED_APPLICATION, PREVIOUS_UUID_TRANSFER,
                  FROM_UUID_OPERATION, TO_UUID_OPERATION,
                  UUID_RECTIFICATIVE_FACTURA, AMOUNT, STATUS, IDEMPOTENCY_KEY,
                  REVIEW_ACTOR_ID, POLICY_EVIDENCE_REF)
                 VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $uuidTransfer,
                $source['root_uuid'],
                $sourceKind === 'DERIVED_APPLICATION' ? $sourceId : null,
                $sourceKind === 'PREVIOUS_TRANSFER' ? $sourceId : null,
                $source['operation_uuid'],
                $uuidNewOperation,
                $uuidRectificative,
                (string) $plan['transfer_promotion'],
                'PENDING_FISCAL_REVIEW',
                $idempotencyKey,
                $authorizedActorId,
                $policyEvidenceRef,
            ]);

            $db->commit();
            return [
                'uuid_transfer' => $uuidTransfer,
                'source_kind' => $sourceKind,
                'source_id' => $sourceId,
                'status' => 'PENDING_FISCAL_REVIEW',
                'transfer_promotion' => (string) $plan['transfer_promotion'],
                'new_course_remaining_before_real_payments'
                    => (string) $plan['new_course_remaining_before_real_payments'],
                'idempotency_reused' => false,
            ];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    private function sourceRootUuid(\PDO $db, string $kind, string $sourceId): ?string
    {
        if ($kind === 'PREVIOUS_TRANSFER') {
            $row = $this->one(
                $db,
                'SELECT ROOT_UUID_ENTITLEMENT
                 FROM novice_promotion_application_transfer WHERE UUID_TRANSFER = ?',
                [$sourceId]
            );
        } else {
            $row = $this->one(
                $db,
                'SELECT ROOT_UUID_ENTITLEMENT
                 FROM novice_promotion_derived_application
                 WHERE UUID_DERIVED_APPLICATION = ?',
                [$sourceId]
            );
        }
        return $row === null ? null : (string) $row['ROOT_UUID_ENTITLEMENT'];
    }

    private function lockSource(\PDO $db, string $kind, string $sourceId): array
    {
        if ($kind === 'PREVIOUS_TRANSFER') {
            $row = $this->one(
                $db,
                'SELECT ROOT_UUID_ENTITLEMENT, TO_UUID_OPERATION,
                        UUID_DESTINATION_FACTURA, AMOUNT, STATUS
                 FROM novice_promotion_application_transfer
                 WHERE UUID_TRANSFER = ? FOR UPDATE',
                [$sourceId]
            );
            if ($row === null || $row['STATUS'] !== 'CONFIRMED'
                || trim((string) ($row['UUID_DESTINATION_FACTURA'] ?? '')) === ''
            ) {
                throw SifException::conflict('Previous transfer is not the current confirmed promotional exposure.');
            }
            return [
                'root_uuid' => (string) $row['ROOT_UUID_ENTITLEMENT'],
                'holder_party_key' => $this->holderForRoot($db, (string) $row['ROOT_UUID_ENTITLEMENT']),
                'operation_uuid' => (string) $row['TO_UUID_OPERATION'],
                'invoice_uuid' => (string) $row['UUID_DESTINATION_FACTURA'],
                'amount' => (string) $row['AMOUNT'],
            ];
        }

        $row = $this->one(
            $db,
            'SELECT a.ROOT_UUID_ENTITLEMENT, a.UUID_DERIVED_BALANCE,
                    a.UUID_DESTINATION_OPERATION, a.UUID_DESTINATION_FACTURA,
                    a.AMOUNT, a.STATUS, b.HOLDER_PARTY_KEY, b.STATUS AS BALANCE_STATUS
             FROM novice_promotion_derived_application a
             JOIN novice_promotion_derived_balance b
               ON b.UUID_DERIVED_BALANCE = a.UUID_DERIVED_BALANCE
             WHERE a.UUID_DERIVED_APPLICATION = ? FOR UPDATE',
            [$sourceId]
        );
        if ($row === null || $row['STATUS'] !== 'APPLIED'
            || !in_array((string) $row['BALANCE_STATUS'], ['ACTIVE', 'EXPIRED'], true)
            || trim((string) ($row['UUID_DESTINATION_FACTURA'] ?? '')) === ''
        ) {
            throw SifException::conflict('Derived application is not a current applied promotional exposure.');
        }
        return [
            'root_uuid' => (string) $row['ROOT_UUID_ENTITLEMENT'],
            'holder_party_key' => (string) $row['HOLDER_PARTY_KEY'],
            'operation_uuid' => (string) $row['UUID_DESTINATION_OPERATION'],
            'invoice_uuid' => (string) $row['UUID_DESTINATION_FACTURA'],
            'amount' => (string) $row['AMOUNT'],
        ];
    }

    private function lockRoot(\PDO $db, string $rootUuid): array
    {
        $row = $this->one(
            $db,
            'SELECT e.UUID_ENTITLEMENT, e.STATUS, e.HOLDER_PARTY_KEY,
                    g.ORIGIN_UUID_OPERATION, v.STATUS AS VALIDATION_STATUS
             FROM commercial_entitlement e
             JOIN novice_promotion_grant g ON g.UUID_ENTITLEMENT = e.UUID_ENTITLEMENT
             JOIN discount_validation v ON v.UUID_VALIDATION = g.UUID_VALIDATION
             WHERE e.UUID_ENTITLEMENT = ? FOR UPDATE',
            [$rootUuid]
        );
        if ($row === null || $row['STATUS'] !== 'ACTIVE'
            || $row['VALIDATION_STATUS'] !== 'VALIDATED'
        ) {
            throw SifException::conflict('Novice root is not active for another course transfer.');
        }
        return $row;
    }

    private function holderForRoot(\PDO $db, string $rootUuid): string
    {
        $row = $this->one(
            $db,
            'SELECT HOLDER_PARTY_KEY FROM commercial_entitlement
             WHERE UUID_ENTITLEMENT = ?',
            [$rootUuid]
        );
        return $row === null ? '' : (string) $row['HOLDER_PARTY_KEY'];
    }

    private function destinationAlreadyUsesPromotion(\PDO $db, string $uuid): bool
    {
        return $this->one(
            $db,
            'SELECT UUID_APPLICATION AS ID FROM novice_promotion_application
             WHERE UUID_DESTINATION_OPERATION = ? FOR UPDATE',
            [$uuid]
        ) !== null
            || $this->one(
                $db,
                'SELECT UUID_DERIVED_APPLICATION AS ID
                 FROM novice_promotion_derived_application
                 WHERE UUID_DESTINATION_OPERATION = ? FOR UPDATE',
                [$uuid]
            ) !== null
            || $this->one(
                $db,
                "SELECT UUID_TRANSFER AS ID FROM novice_promotion_application_transfer
                 WHERE TO_UUID_OPERATION = ? AND STATUS <> 'CANCELLED' FOR UPDATE",
                [$uuid]
            ) !== null;
    }

    private function existingTransferForSource(\PDO $db, string $kind, string $id): ?array
    {
        if ($kind === 'PREVIOUS_TRANSFER') {
            return $this->one(
                $db,
                'SELECT * FROM novice_promotion_application_transfer
                 WHERE PREVIOUS_UUID_TRANSFER = ? FOR UPDATE',
                [$id]
            );
        }
        return $this->one(
            $db,
            'SELECT * FROM novice_promotion_application_transfer
             WHERE UUID_DERIVED_APPLICATION = ? FOR UPDATE',
            [$id]
        );
    }

    private function cents(string $amount): int
    {
        if (!preg_match('/^(-?)(\d{1,10})(?:\.(\d{1,2}))?$/D', trim($amount), $m)) {
            throw SifException::validation('Invalid successive transfer amount.');
        }
        $cents = (int) $m[2] * 100 + (int) str_pad($m[3] ?? '', 2, '0');
        return $m[1] === '-' ? -$cents : $cents;
    }

    private function money(int $cents): string
    {
        if ($cents < 0) {
            throw SifException::validation('Negative successive transfer value is not valid.');
        }
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function one(\PDO $db, string $sql, array $args): ?array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($args);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function many(\PDO $db, string $sql, array $args): array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($args);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
