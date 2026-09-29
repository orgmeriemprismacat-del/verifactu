<?php

declare(strict_types=1);

namespace Prisma\Sif\Domain;

/**
 * Pure exact binding of externally verified recovery evidence to one
 * PENDING_RECOVERY row. This validates consistency only; the evidence source
 * is responsible for authentication and real accounting/payment verification.
 */
final class NovicePromotionRecoveryResolutionPolicy
{
    public function assertMatches(
        array $evidence,
        array $recovery,
        string $nowUtc
    ): void {
        $resolution = (string) ($evidence['resolution'] ?? '');
        if (!in_array($resolution, ['RECOVERED', 'WAIVED', 'CANCELLED'], true)) {
            throw new \InvalidArgumentException('Unsupported recovery resolution.');
        }

        $expected = [
            'recovery_uuid' => (string) ($recovery['UUID_RECOVERY'] ?? ''),
            'root_uuid_entitlement' => (string) ($recovery['ROOT_UUID_ENTITLEMENT'] ?? ''),
            'source_kind' => (string) ($recovery['SOURCE_KIND'] ?? ''),
            'source_uuid' => (string) ($recovery['SOURCE_UUID'] ?? ''),
            'uuid_destination_operation' => (string) ($recovery['UUID_DESTINATION_OPERATION'] ?? ''),
            'amount' => (string) ($recovery['AMOUNT'] ?? ''),
        ];
        foreach ($expected as $key => $value) {
            if ($value === '' || !isset($evidence[$key]) || !is_string($evidence[$key])
                || !hash_equals($value, $evidence[$key])
            ) {
                throw new \InvalidArgumentException('Recovery evidence does not match the frozen work item.');
            }
        }

        foreach (['resolution_id', 'resolved_by', 'evidence_ref', 'resolved_at_utc'] as $key) {
            if (!isset($evidence[$key]) || !is_string($evidence[$key])
                || trim($evidence[$key]) === ''
            ) {
                throw new \InvalidArgumentException('Recovery resolution lacks verified evidence.');
            }
        }
        if (strlen($evidence['resolution_id']) > 100
            || strlen($evidence['resolved_by']) > 100
            || strlen($evidence['evidence_ref']) > 140
        ) {
            throw new \InvalidArgumentException('Recovery resolution evidence reference is too long.');
        }

        $created = (string) ($recovery['CREATED_AT'] ?? '');
        $resolved = (string) $evidence['resolved_at_utc'];
        if (preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/D', $created) !== 1
            || preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/D', $resolved) !== 1
            || preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/D', $nowUtc) !== 1
            || $resolved < $created || $resolved > $nowUtc
            || (string) ($recovery['STATUS'] ?? '') !== 'PENDING_RECOVERY'
        ) {
            throw new \InvalidArgumentException('Recovery evidence chronology or state is invalid.');
        }
    }
}
