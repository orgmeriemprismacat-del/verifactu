<?php

declare(strict_types=1);

namespace Prisma\Sif\Domain;

/**
 * Pure closure check for the UC-111 root-refund recovery workflow.
 *
 * RECOVERY_RESOLVED means every recovery work item has a verified final
 * resolution and the total frozen amount is fully accounted for. It does NOT
 * mean every item was collected: WAIVED and CANCELLED remain distinct.
 */
final class NovicePromotionRecoveryCompletionPolicy
{
    public function summarize(array $review, array $recoveries): array
    {
        if ((string) ($review['STATUS'] ?? '') !== 'EXECUTED') {
            throw new \InvalidArgumentException('Root refund review is not ready for recovery closure.');
        }

        $plan = json_decode((string) ($review['PLAN_JSON'] ?? ''), true);
        if (!is_array($plan)) {
            throw new \InvalidArgumentException('Frozen root-refund plan is unreadable.');
        }
        $expectedTotal = $this->cents((string) ($plan['total_recover_active'] ?? ''));

        $seen = [];
        $totals = [
            'RECOVERED' => 0,
            'WAIVED' => 0,
            'CANCELLED' => 0,
        ];
        $counts = [
            'RECOVERED' => 0,
            'WAIVED' => 0,
            'CANCELLED' => 0,
        ];

        foreach ($recoveries as $row) {
            $uuid = (string) ($row['UUID_RECOVERY'] ?? '');
            if ($uuid === '' || isset($seen[$uuid])) {
                throw new \InvalidArgumentException('Duplicate or missing recovery identifier.');
            }
            $seen[$uuid] = true;

            if ((string) ($row['UUID_REVIEW'] ?? '') !== (string) ($review['UUID_REVIEW'] ?? '')
                || (string) ($row['ROOT_UUID_ENTITLEMENT'] ?? '')
                    !== (string) ($review['ROOT_UUID_ENTITLEMENT'] ?? '')
            ) {
                throw new \InvalidArgumentException('Recovery item belongs to another frozen review.');
            }

            $status = (string) ($row['STATUS'] ?? '');
            if ($status === 'PENDING_RECOVERY') {
                throw new \InvalidArgumentException('Pending recovery blocks workflow closure.');
            }
            if (!array_key_exists($status, $totals)) {
                throw new \InvalidArgumentException('Unsupported recovery resolution state.');
            }

            foreach ([
                'RESOLVED_AT', 'RESOLUTION_CODE', 'RESOLUTION_ID',
                'RESOLVED_BY', 'RESOLUTION_EVIDENCE_REF',
            ] as $field) {
                if (!isset($row[$field]) || trim((string) $row[$field]) === '') {
                    throw new \InvalidArgumentException('Resolved recovery lacks mandatory evidence.');
                }
            }

            $amount = $this->cents((string) ($row['AMOUNT'] ?? ''));
            if ($amount <= 0) {
                throw new \InvalidArgumentException('Resolved recovery has invalid amount.');
            }
            $totals[$status] += $amount;
            $counts[$status]++;
        }

        $accounted = array_sum($totals);
        if ($accounted !== $expectedTotal) {
            throw new \InvalidArgumentException('Resolved recoveries do not account for the frozen recovery total.');
        }

        return [
            'expected_total' => $this->money($expectedTotal),
            'accounted_total' => $this->money($accounted),
            'recovered_amount' => $this->money($totals['RECOVERED']),
            'waived_amount' => $this->money($totals['WAIVED']),
            'cancelled_amount' => $this->money($totals['CANCELLED']),
            'recovered_count' => $counts['RECOVERED'],
            'waived_count' => $counts['WAIVED'],
            'cancelled_count' => $counts['CANCELLED'],
            'total_items' => count($recoveries),
            'all_items_resolved' => true,
            'all_value_recovered' => $totals['RECOVERED'] === $expectedTotal,
        ];
    }

    private function cents(string $amount): int
    {
        if (preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/D', trim($amount), $match) !== 1) {
            throw new \InvalidArgumentException('Invalid recovery amount.');
        }
        return (int) $match[1] * 100 + (int) str_pad($match[2] ?? '', 2, '0');
    }

    private function money(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
