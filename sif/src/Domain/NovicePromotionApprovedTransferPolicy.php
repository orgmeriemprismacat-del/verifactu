<?php

declare(strict_types=1);

namespace Prisma\Sif\Domain;

/**
 * Pure binding of a FINAL independently authorized change-course decision to
 * its staged review. This does not itself authenticate the reviewer's role:
 * the backoffice approval-source implementation must do so independently.
 */
final class NovicePromotionApprovedTransferPolicy
{
    public function assertMatches(
        array $approval,
        string $uuidTransfer,
        array $stagedTransfer,
        string $nowUtc
    ): void {
        $expected = [
            'review_uuid' => $uuidTransfer,
            'decision_type' => 'NOVICE_DESTINATION_TRANSFER',
            'decision' => 'APPROVED',
            'uuid_original_application' => (string) ($stagedTransfer['UUID_ORIGINAL_APPLICATION'] ?? ''),
            'uuid_rectificative' => (string) ($stagedTransfer['UUID_RECTIFICATIVE_FACTURA'] ?? ''),
            'uuid_new_operation' => (string) ($stagedTransfer['TO_UUID_OPERATION'] ?? ''),
            'approved_promotional_amount' => (string) ($stagedTransfer['AMOUNT'] ?? ''),
            'evidence_ref' => (string) ($stagedTransfer['POLICY_EVIDENCE_REF'] ?? ''),
        ];
        foreach ($expected as $key => $value) {
            if ($value === '' || !isset($approval[$key])
                || !is_string($approval[$key])
                || !hash_equals($value, $approval[$key])
            ) {
                throw new \InvalidArgumentException('Course change approval is not bound to its immutable staged review.');
            }
        }

        foreach (['decision_id', 'reviewer_id', 'approved_at_utc'] as $key) {
            if (!isset($approval[$key]) || !is_string($approval[$key])
                || trim($approval[$key]) === ''
            ) {
                throw new \InvalidArgumentException('Course change lacks a finalized authorized decision reference.');
            }
        }
        if (strlen($approval['decision_id']) > 100
            || strlen($approval['reviewer_id']) > 100
            || strlen($approval['evidence_ref']) > 140
        ) {
            throw new \InvalidArgumentException('Course change approval reference exceeds permitted length.');
        }

        $created = (string) ($stagedTransfer['CREATED_AT'] ?? '');
        $approved = $approval['approved_at_utc'];
        if (preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/D', $created) !== 1
            || preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/D', $approved) !== 1
            || preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/D', $nowUtc) !== 1
            || $approved < $created || $approved > $nowUtc
        ) {
            throw new \InvalidArgumentException('Course change approval chronology is inconsistent with its proposal.');
        }
    }
}
