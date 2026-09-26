<?php

declare(strict_types=1);

namespace Prisma\Sif\Domain;

/**
 * Pure immutable binding of a FINAL authorized decision to a pending transfer
 * whose source is the CURRENT exposure: either DERIVED_APPLICATION or
 * PREVIOUS_TRANSFER.
 *
 * Authentication/authorization remains the approval-source responsibility.
 */
final class NovicePromotionApprovedSuccessiveTransferPolicy
{
    public function assertMatches(
        array $approval,
        string $uuidTransfer,
        array $stagedTransfer,
        string $sourceKind,
        string $sourceId,
        string $nowUtc
    ): void {
        if (!in_array($sourceKind, ['DERIVED_APPLICATION', 'PREVIOUS_TRANSFER'], true)) {
            throw new \InvalidArgumentException('Unsupported current transfer source kind.');
        }

        $expected = [
            'review_uuid' => $uuidTransfer,
            'decision_type' => 'NOVICE_SUCCESSIVE_DESTINATION_TRANSFER',
            'decision' => 'APPROVED',
            'source_kind' => $sourceKind,
            'source_id' => $sourceId,
            'uuid_rectificative' => (string) ($stagedTransfer['UUID_RECTIFICATIVE_FACTURA'] ?? ''),
            'uuid_new_operation' => (string) ($stagedTransfer['TO_UUID_OPERATION'] ?? ''),
            'approved_promotional_amount' => (string) ($stagedTransfer['AMOUNT'] ?? ''),
            'evidence_ref' => (string) ($stagedTransfer['POLICY_EVIDENCE_REF'] ?? ''),
        ];
        foreach ($expected as $key => $value) {
            if ($value === '' || !isset($approval[$key]) || !is_string($approval[$key])
                || !hash_equals($value, $approval[$key])
            ) {
                throw new \InvalidArgumentException('Successive transfer approval differs from its immutable review.');
            }
        }

        foreach (['decision_id', 'reviewer_id', 'approved_at_utc'] as $key) {
            if (!isset($approval[$key]) || !is_string($approval[$key])
                || trim($approval[$key]) === ''
            ) {
                throw new \InvalidArgumentException('Successive transfer lacks finalized approval evidence.');
            }
        }
        if (strlen($approval['decision_id']) > 100
            || strlen($approval['reviewer_id']) > 100
            || strlen($approval['evidence_ref']) > 140
        ) {
            throw new \InvalidArgumentException('Successive transfer approval reference is too long.');
        }

        $created = (string) ($stagedTransfer['CREATED_AT'] ?? '');
        $approved = (string) $approval['approved_at_utc'];
        if (preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/D', $created) !== 1
            || preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/D', $approved) !== 1
            || preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/D', $nowUtc) !== 1
            || $approved < $created || $approved > $nowUtc
        ) {
            throw new \InvalidArgumentException('Successive transfer approval chronology is inconsistent.');
        }
    }
}
