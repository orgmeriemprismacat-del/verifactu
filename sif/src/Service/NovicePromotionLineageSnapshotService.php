<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\NovicePromotionLineageProjectionPolicy;
use Prisma\Sif\Exception\SifException;

/**
 * UC-111: read the authoritative promotion/derived/transfer ledger under an
 * ALREADY OPEN caller transaction and project it to the pure lineage graph.
 *
 * The future JASOM-refund executor MUST keep this transaction open while it
 * also locks/resolves pending reservations and performs any approved state
 * transitions. This class therefore never begins/commits/rolls back.
 *
 * Lock order:
 * root entitlement/grant -> original applications -> derived balances ->
 * derived applications -> transfers.
 *
 * It has no banking/fiscal side effects and does not itself decide/refund.
 */
final class NovicePromotionLineageSnapshotService
{
    public function __construct(
        private NovicePromotionLineageProjectionPolicy $projector
            = new NovicePromotionLineageProjectionPolicy()
    ) {
    }

    public function projectLocked(\PDO $db, string $rootUuid): array
    {
        if (!$db->inTransaction()) {
            throw new \LogicException('Lineage snapshot must be read inside the caller root-refund transaction.');
        }
        $rootUuid = trim($rootUuid);
        if ($rootUuid === '') {
            throw SifException::validation('Novice root entitlement identifier is required.');
        }

        $root = $this->one(
            $db,
            'SELECT e.UUID_ENTITLEMENT,
                    e.STATUS AS ENTITLEMENT_STATUS,
                    e.HOLDER_PARTY_KEY,
                    e.EXPIRES_AT,
                    g.ORIGIN_UUID_OPERATION,
                    g.ORIGINAL_CASH_AMOUNT,
                    g.AVAILABLE_AMOUNT,
                    v.STATUS AS VALIDATION_STATUS
             FROM commercial_entitlement e
             JOIN novice_promotion_grant g
               ON g.UUID_ENTITLEMENT = e.UUID_ENTITLEMENT
             JOIN discount_validation v
               ON v.UUID_VALIDATION = g.UUID_VALIDATION
             WHERE e.UUID_ENTITLEMENT = ?
             FOR UPDATE',
            [$rootUuid]
        );
        if ($root === null
            || (string) $root['UUID_ENTITLEMENT'] !== $rootUuid
            || (string) $root['HOLDER_PARTY_KEY'] === ''
            || (string) $root['ORIGIN_UUID_OPERATION'] === ''
            || $root['VALIDATION_STATUS'] !== 'VALIDATED'
        ) {
            throw SifException::conflict('Novice promotion root is missing or no longer validated.');
        }

        $originalApplications = $this->many(
            $db,
            'SELECT UUID_APPLICATION, UUID_ENTITLEMENT,
                    UUID_DESTINATION_OPERATION, UUID_DESTINATION_FACTURA,
                    AMOUNT, STATUS, REASON_CODE,
                    RESERVED_AT, RESERVATION_EXPIRES_AT,
                    APPLIED_AT, RELEASED_AT, REVERSED_AT
             FROM novice_promotion_application
             WHERE UUID_ENTITLEMENT = ?
             ORDER BY CREATED_AT, UUID_APPLICATION
             FOR UPDATE',
            [$rootUuid]
        );

        $derivedBalances = $this->many(
            $db,
            'SELECT UUID_DERIVED_BALANCE, ROOT_UUID_ENTITLEMENT,
                    PARENT_UUID_DERIVED_BALANCE,
                    SOURCE_UUID_APPLICATION,
                    SOURCE_UUID_DERIVED_APPLICATION,
                    SOURCE_UUID_TRANSFER,
                    UUID_DESTINATION_OPERATION,
                    UUID_RECTIFICATIVE_FACTURA,
                    HOLDER_PARTY_KEY,
                    PROMOTIONAL_ORIGIN_AMOUNT,
                    AVAILABLE_PROMOTIONAL_AMOUNT,
                    STATUS, ISSUED_AT, EXPIRES_AT,
                    CANCELLED_AT, CANCELLATION_REASON,
                    POLICY_SNAPSHOT_JSON, CREATED_AT
             FROM novice_promotion_derived_balance
             WHERE ROOT_UUID_ENTITLEMENT = ?
             ORDER BY CREATED_AT, UUID_DERIVED_BALANCE
             FOR UPDATE',
            [$rootUuid]
        );

        foreach ($derivedBalances as $balance) {
            if ((string) $balance['HOLDER_PARTY_KEY']
                !== (string) $root['HOLDER_PARTY_KEY']
            ) {
                throw SifException::conflict('Derived balance holder differs from its novice root.');
            }
        }

        $derivedApplications = $this->many(
            $db,
            'SELECT UUID_DERIVED_APPLICATION,
                    UUID_DERIVED_BALANCE,
                    ROOT_UUID_ENTITLEMENT,
                    UUID_DESTINATION_OPERATION,
                    UUID_DESTINATION_FACTURA,
                    AMOUNT, STATUS, RESERVED_AT,
                    RESERVATION_EXPIRES_AT,
                    APPLIED_AT, CLOSED_AT,
                    RELEASED_AT, REASON_CODE
             FROM novice_promotion_derived_application
             WHERE ROOT_UUID_ENTITLEMENT = ?
             ORDER BY RESERVED_AT, UUID_DERIVED_APPLICATION
             FOR UPDATE',
            [$rootUuid]
        );

        $transfers = $this->many(
            $db,
            'SELECT UUID_TRANSFER,
                    ROOT_UUID_ENTITLEMENT,
                    UUID_ORIGINAL_APPLICATION,
                    UUID_DERIVED_APPLICATION,
                    PREVIOUS_UUID_TRANSFER,
                    FROM_UUID_OPERATION,
                    TO_UUID_OPERATION,
                    UUID_RECTIFICATIVE_FACTURA,
                    UUID_DESTINATION_FACTURA,
                    AMOUNT,
                    FINAL_NET_AMOUNT,
                    ORDINARY_NET_BEFORE_PROMOTION,
                    STATUS,
                    IDEMPOTENCY_KEY,
                    REVIEW_ACTOR_ID,
                    POLICY_EVIDENCE_REF,
                    APPROVAL_DECISION_ID,
                    APPROVED_BY,
                    APPROVED_AT,
                    CREATED_AT,
                    CONFIRMED_AT,
                    CLOSED_AT,
                    CLOSE_REASON
             FROM novice_promotion_application_transfer
             WHERE ROOT_UUID_ENTITLEMENT = ?
             ORDER BY CREATED_AT, UUID_TRANSFER
             FOR UPDATE',
            [$rootUuid]
        );

        try {
            $graph = $this->projector->project(
                $root,
                $originalApplications,
                $derivedBalances,
                $derivedApplications,
                $transfers
            );
        } catch (\InvalidArgumentException $exception) {
            throw SifException::conflict(
                'Novice promotion lineage is incomplete or ambiguous: '
                . $exception->getMessage()
            );
        }

        return [
            'root' => [
                'uuid_entitlement' => $rootUuid,
                'holder_party_key' => (string) $root['HOLDER_PARTY_KEY'],
                'origin_uuid_operation' => (string) $root['ORIGIN_UUID_OPERATION'],
                'status' => (string) $root['ENTITLEMENT_STATUS'],
                'validation_status' => (string) $root['VALIDATION_STATUS'],
            ],
            'graph' => $graph,
            'ledger_counts' => [
                'original_applications' => count($originalApplications),
                'derived_balances' => count($derivedBalances),
                'derived_applications' => count($derivedApplications),
                'transfers' => count($transfers),
            ],
        ];
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
}
