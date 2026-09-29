<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\NovicePromotionRecoveryCompletionPolicy;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

/**
 * UC-111 / DEC-23:
 * Close the RECOVERY WORKFLOW only after every recovery item created by an
 * EXECUTED root refund has a verified final resolution.
 *
 * This service NEVER creates a payment, invoice, credit, waiver or refund.
 * It merely verifies that the externally resolved items fully account for the
 * frozen plan and persists an immutable summary.
 */
final class NovicePromotionRootRefundRecoveryCompletionService
{
    public function __construct(
        private NovicePromotionRecoveryCompletionPolicy $policy
            = new NovicePromotionRecoveryCompletionPolicy(),
        private UuidGenerator $uuids = new UuidGenerator()
    ) {
    }

    public function closeResolvedWorkflow(
        \PDO $db,
        string $uuidReview,
        ?\DateTimeImmutable $now = null
    ): array {
        if ($db->inTransaction()) {
            throw new \LogicException('Recovery workflow closure must own its SIF transaction.');
        }

        $uuidReview = trim($uuidReview);
        if ($uuidReview === '') {
            throw SifException::validation('Root refund review identifier is required.');
        }

        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $timestamp = $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $db->beginTransaction();
        try {
            $lookup = $this->one(
                $db,
                'SELECT ROOT_UUID_ENTITLEMENT
                 FROM novice_promotion_root_refund_review
                 WHERE UUID_REVIEW = ?',
                [$uuidReview]
            );
            if ($lookup === null) {
                throw SifException::conflict('Root refund review does not exist.');
            }

            $root = $this->one(
                $db,
                'SELECT UUID_ENTITLEMENT, STATUS
                 FROM commercial_entitlement
                 WHERE UUID_ENTITLEMENT = ? FOR UPDATE',
                [(string) $lookup['ROOT_UUID_ENTITLEMENT']]
            );
            if ($root === null || $root['STATUS'] !== 'CANCELLED') {
                throw SifException::conflict('Recovery workflow can close only after the novice root was cancelled.');
            }

            $review = $this->one(
                $db,
                'SELECT * FROM novice_promotion_root_refund_review
                 WHERE UUID_REVIEW = ? FOR UPDATE',
                [$uuidReview]
            );
            if ($review === null
                || (string) $review['ROOT_UUID_ENTITLEMENT']
                    !== (string) $root['UUID_ENTITLEMENT']
            ) {
                throw SifException::conflict('Root refund review changed during recovery closure.');
            }

            if ($review['STATUS'] === 'RECOVERY_RESOLVED') {
                $summary = json_decode(
                    (string) $review['RECOVERY_SUMMARY_JSON'],
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
                if (!is_array($summary)) {
                    throw SifException::conflict('Stored recovery completion summary is unreadable.');
                }
                $db->commit();
                return [
                    'uuid_review' => $uuidReview,
                    'status' => 'RECOVERY_RESOLVED',
                    'summary' => $summary,
                    'monetary_transaction_created' => false,
                    'idempotency_reused' => true,
                ];
            }

            if ($review['STATUS'] !== 'EXECUTED'
                || $review['EXECUTED_AT'] === null
                || $review['ORIGIN_REFUND_EVIDENCE_ID'] === null
                || $review['ORIGIN_REFUND_CONFIRMED_AT'] === null
                || $review['ORIGIN_REFUNDED_AMOUNT'] === null
            ) {
                throw SifException::conflict('Root refund commercial execution is not complete.');
            }

            $recoveries = $this->many(
                $db,
                'SELECT *
                 FROM novice_promotion_root_refund_recovery
                 WHERE UUID_REVIEW = ?
                 ORDER BY CREATED_AT, UUID_RECOVERY FOR UPDATE',
                [$uuidReview]
            );

            try {
                $summary = $this->policy->summarize($review, $recoveries);
            } catch (\InvalidArgumentException $exception) {
                throw SifException::conflict(
                    'Recovery workflow still has pending, missing or inconsistent resolution evidence.'
                );
            }

            $summary['completed_at_utc'] = $timestamp;
            $summary['monetary_transaction_created_by_closure'] = false;

            $stmt = $db->prepare(
                "UPDATE novice_promotion_root_refund_review
                 SET STATUS = 'RECOVERY_RESOLVED',
                     RECOVERY_COMPLETED_AT = ?,
                     RECOVERY_SUMMARY_JSON = ?
                 WHERE UUID_REVIEW = ? AND STATUS = 'EXECUTED'"
            );
            $stmt->execute([
                $timestamp,
                json_encode($summary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                $uuidReview,
            ]);
            if ($stmt->rowCount() !== 1) {
                throw SifException::conflict('Root refund recovery workflow changed concurrently.');
            }

            $audit = $db->prepare(
                'INSERT INTO commercial_entitlement_event
                 (UUID_EVENT, UUID_ENTITLEMENT, ACTION, RESULT,
                  ACTOR_TYPE, CORRELATION_ID, CAUSATION_ID,
                  REASON_CODE, CHANGESET_JSON, OCCURRED_AT)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $audit->execute([
                $this->uuids->generate(),
                (string) $root['UUID_ENTITLEMENT'],
                'ROOT_RECOVERY_CLOSE',
                'SUCCESS',
                'SYSTEM',
                $uuidReview,
                $uuidReview,
                'ROOT_REFUND_RECOVERY_WORKFLOW_RESOLVED',
                json_encode($summary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                $timestamp,
            ]);

            $db->commit();
            return [
                'uuid_review' => $uuidReview,
                'status' => 'RECOVERY_RESOLVED',
                'summary' => $summary,
                'monetary_transaction_created' => false,
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
