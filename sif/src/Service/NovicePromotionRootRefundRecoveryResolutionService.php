<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\NovicePromotionRecoveryResolutionPolicy;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

/**
 * UC-111 / DEC-23:
 * Resolve one PENDING_RECOVERY only from independently verified external
 * accounting/payment/backoffice evidence.
 *
 * This service NEVER creates a CHARGE, REFUND, factura, credit_balance or
 * payment allocation. RECOVERED means an authoritative external source says
 * the promotional value was actually recovered. WAIVED/CANCELLED likewise
 * require external authorization/evidence.
 */
final class NovicePromotionRootRefundRecoveryResolutionService
{
    public function __construct(
        private NovicePromotionRecoveryResolutionSourceInterface $evidence,
        private NovicePromotionRecoveryResolutionPolicy $policy
            = new NovicePromotionRecoveryResolutionPolicy(),
        private UuidGenerator $uuids = new UuidGenerator()
    ) {
    }

    public function resolveVerifiedRecovery(
        \PDO $db,
        string $uuidRecovery,
        ?\DateTimeImmutable $now = null
    ): array {
        if ($db->inTransaction()) {
            throw new \LogicException('Recovery resolution must own its SIF transaction.');
        }
        $uuidRecovery = trim($uuidRecovery);
        if ($uuidRecovery === '') {
            throw SifException::validation('Recovery identifier is required.');
        }

        $resolution = $this->evidence->resolvedRecovery($uuidRecovery);
        if (!is_array($resolution)
            || (string) ($resolution['recovery_uuid'] ?? '') !== $uuidRecovery
        ) {
            throw SifException::conflict('No independently verified recovery resolution is available.');
        }

        $now ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $timestamp = $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $db->beginTransaction();
        try {
            $lookup = $this->one(
                $db,
                'SELECT ROOT_UUID_ENTITLEMENT
                 FROM novice_promotion_root_refund_recovery
                 WHERE UUID_RECOVERY = ?',
                [$uuidRecovery]
            );
            if ($lookup === null) {
                throw SifException::conflict('Recovery item does not exist.');
            }

            $root = $this->one(
                $db,
                'SELECT UUID_ENTITLEMENT, STATUS
                 FROM commercial_entitlement
                 WHERE UUID_ENTITLEMENT = ? FOR UPDATE',
                [(string) $lookup['ROOT_UUID_ENTITLEMENT']]
            );
            if ($root === null || $root['STATUS'] !== 'CANCELLED') {
                throw SifException::conflict('Recovery can only resolve after the novice root was commercially cancelled.');
            }

            $recovery = $this->one(
                $db,
                'SELECT * FROM novice_promotion_root_refund_recovery
                 WHERE UUID_RECOVERY = ? FOR UPDATE',
                [$uuidRecovery]
            );
            if ($recovery === null
                || (string) $recovery['ROOT_UUID_ENTITLEMENT']
                    !== (string) $root['UUID_ENTITLEMENT']
            ) {
                throw SifException::conflict('Recovery item changed during resolution.');
            }

            if ($recovery['STATUS'] !== 'PENDING_RECOVERY') {
                if ((string) $recovery['STATUS']
                        === (string) ($resolution['resolution'] ?? '')
                    && (string) $recovery['RESOLUTION_ID']
                        === (string) ($resolution['resolution_id'] ?? '')
                ) {
                    $db->commit();
                    return [
                        'uuid_recovery' => $uuidRecovery,
                        'status' => (string) $recovery['STATUS'],
                        'amount' => (string) $recovery['AMOUNT'],
                        'monetary_transaction_created' => false,
                        'idempotency_reused' => true,
                    ];
                }
                throw SifException::conflict('Recovery item already has a different final resolution.');
            }

            try {
                $this->policy->assertMatches(
                    $resolution,
                    $recovery,
                    $timestamp
                );
            } catch (\InvalidArgumentException $exception) {
                throw SifException::conflict('External recovery evidence differs from the pending work item.');
            }

            $status = (string) $resolution['resolution'];
            $resolutionCode = match ($status) {
                'RECOVERED' => 'EXTERNAL_RECOVERY_CONFIRMED',
                'WAIVED' => 'AUTHORIZED_RECOVERY_WAIVER',
                'CANCELLED' => 'RECOVERY_ITEM_CANCELLED',
                default => throw SifException::conflict('Unsupported verified recovery resolution.'),
            };

            $stmt = $db->prepare(
                'UPDATE novice_promotion_root_refund_recovery
                 SET STATUS = ?, RESOLVED_AT = ?, RESOLUTION_CODE = ?,
                     RESOLUTION_ID = ?, RESOLVED_BY = ?,
                     RESOLUTION_EVIDENCE_REF = ?
                 WHERE UUID_RECOVERY = ? AND STATUS = ?'
            );
            $stmt->execute([
                $status,
                (string) $resolution['resolved_at_utc'],
                $resolutionCode,
                (string) $resolution['resolution_id'],
                (string) $resolution['resolved_by'],
                (string) $resolution['evidence_ref'],
                $uuidRecovery,
                'PENDING_RECOVERY',
            ]);
            if ($stmt->rowCount() !== 1) {
                throw SifException::conflict('Recovery item changed concurrently.');
            }

            $audit = $db->prepare(
                'INSERT INTO commercial_entitlement_event
                 (UUID_EVENT, UUID_ENTITLEMENT, ACTION, RESULT, UUID_OPERATION,
                  ACTOR_TYPE, ACTOR_ID, CORRELATION_ID, CAUSATION_ID,
                  REASON_CODE, CHANGESET_JSON, OCCURRED_AT)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $audit->execute([
                $this->uuids->generate(),
                (string) $recovery['ROOT_UUID_ENTITLEMENT'],
                'ROOT_RECOVERY_RESOLVE',
                'SUCCESS',
                (string) $recovery['UUID_DESTINATION_OPERATION'],
                'SECRETARIAT',
                (string) $resolution['resolved_by'],
                $uuidRecovery,
                (string) $resolution['resolution_id'],
                $resolutionCode,
                json_encode([
                    'source_kind' => (string) $recovery['SOURCE_KIND'],
                    'source_uuid' => (string) $recovery['SOURCE_UUID'],
                    'amount' => (string) $recovery['AMOUNT'],
                    'resolution' => $status,
                    'evidence_ref' => (string) $resolution['evidence_ref'],
                    'monetary_transaction_created' => false,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                $timestamp,
            ]);

            $db->commit();
            return [
                'uuid_recovery' => $uuidRecovery,
                'status' => $status,
                'amount' => (string) $recovery['AMOUNT'],
                'resolution_code' => $resolutionCode,
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
}
