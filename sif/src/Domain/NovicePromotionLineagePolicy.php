<?php

declare(strict_types=1);

namespace Prisma\Sif\Domain;

/**
 * UC-111: pure provenance + root-refund planning (NO DB/AEAT/refund actions).
 *
 * The caller must provide a CONSISTENT authoritative snapshot, captured under
 * a transaction that locks the original and every derived right/application.
 * Each allocation represents PROMOTIONAL value only; real bank-paid amounts
 * belong to a separate fiscal/credit refund workflow.
 *
 * Right: ['id', 'parent_application_id'=>null|string, 'issued'=>'90.00',
 *         'available'=>'0.00', 'forfeited'=>'0.00' (separately evidenced),
 *         'status'=>'ACTIVE'|'CANCELLED'|'EXPIRED']
 * Application: ['id','right_id','amount'=>'90.00','status'=>
 *   'ACTIVE'|'REPLACED_BY_TRANSFER'|'REPLACED_BY_DERIVED'|'RELEASED'|'CANCELLED'|'RESERVED',
 *   'successor_application_id'=>null|string,'derived_right_id'=>null|string]
 *
 * A predecessor REPLACED_BY_DERIVED is history, NOT live exposure; only the
 * current descendants contribute to clawback. A TRANSFER is attribution to
 * a new operation and never a second promotional consumption.
 */
final class NovicePromotionLineagePolicy
{
    public function planOriginalRefund(
        string $rootRightId,
        array $rights,
        array $applications
    ): array {
        $rightById = [];
        foreach ($rights as $right) {
            $id = (string) ($right['id'] ?? '');
            if ($id === '' || isset($rightById[$id])) {
                throw new \InvalidArgumentException('Ambiguous or duplicate promotional right.');
            }
            $status = (string) ($right['status'] ?? '');
            if (!in_array($status, ['ACTIVE', 'CANCELLED', 'EXPIRED'], true)) {
                throw new \InvalidArgumentException('Unknown promotional right state.');
            }
            $issued = $this->cents((string) ($right['issued'] ?? ''));
            $available = $this->cents((string) ($right['available'] ?? ''));
            // Any value extinguished by an approved cancellation policy must
            // be supported by separate auditable evidence, not disappear from
            // the ledger as an unexplained gap.
            $forfeited = $this->cents((string) ($right['forfeited'] ?? '0.00'));
            if ($issued <= 0 || $available + $forfeited > $issued) {
                throw new \InvalidArgumentException('Invalid promotional right amount.');
            }
            if ($status !== 'ACTIVE' && $available > 0) {
                throw new \InvalidArgumentException('Inactive right has an unaccounted available amount.');
            }
            $rightById[$id] = [
                'id' => $id,
                'parent_application_id' => $right['parent_application_id'] ?? null,
                'issued' => $issued,
                'available' => $available,
                'forfeited' => $forfeited,
                'status' => $status,
            ];
        }

        if (!isset($rightById[$rootRightId])
            || $rightById[$rootRightId]['parent_application_id'] !== null
            || $rightById[$rootRightId]['status'] !== 'ACTIVE'
        ) {
            throw new \InvalidArgumentException('Missing or non-rooted JASOM promotion.');
        }

        $allocationById = [];
        foreach ($applications as $application) {
            $id = (string) ($application['id'] ?? '');
            $rightId = (string) ($application['right_id'] ?? '');
            if ($id === '' || isset($allocationById[$id]) || !isset($rightById[$rightId])) {
                throw new \InvalidArgumentException('Duplicate application or missing promotional source.');
            }
            $status = (string) ($application['status'] ?? '');
            if (!in_array($status, [
                'ACTIVE', 'REPLACED_BY_TRANSFER', 'REPLACED_BY_DERIVED',
                'RELEASED', 'CANCELLED', 'RESERVED',
            ], true)) {
                throw new \InvalidArgumentException('Unknown promotional application state.');
            }
            $amount = $this->cents((string) ($application['amount'] ?? ''));
            if ($amount <= 0) {
                throw new \InvalidArgumentException('Promotional application amount must be positive.');
            }
            $allocationById[$id] = [
                'id' => $id,
                'right_id' => $rightId,
                'amount' => $amount,
                'status' => $status,
                'successor_application_id' => $application['successor_application_id'] ?? null,
                'derived_right_id' => $application['derived_right_id'] ?? null,
            ];
        }

        // Require FULL conservation within every right: unused value,
        // live or converted applications, and separately evidenced forfeiture.
        // A missing 70-euro destination cannot silently vanish from a 90-euro
        // original as if the refund plan were complete.
        foreach ($rightById as $right) {
            $allocated = $right['available'] + $right['forfeited'];
            foreach ($allocationById as $application) {
                if ($application['right_id'] === $right['id']
                    && !in_array(
                        $application['status'],
                        ['RELEASED', 'CANCELLED', 'REPLACED_BY_TRANSFER'],
                        true
                    )
                ) {
                    $allocated += $application['amount'];
                }
            }
            if ($allocated !== $right['issued']) {
                throw new \InvalidArgumentException('Promotional right has an unexplained gap or double-counted value.');
            }
        }

        $children = [];
        $incoming = [];
        foreach ($rightById as $right) {
            $parent = $right['parent_application_id'];
            if ($parent === null) {
                if ($right['id'] !== $rootRightId) {
                    throw new \InvalidArgumentException('Unrelated promotional root in refund snapshot.');
                }
                continue;
            }
            if (!is_string($parent) || !isset($allocationById[$parent])) {
                throw new \InvalidArgumentException('Derived right has no original application.');
            }
            $source = $allocationById[$parent];
            if ($source['status'] !== 'REPLACED_BY_DERIVED'
                || $source['derived_right_id'] !== $right['id']
                || $right['issued'] > $source['amount']
                || isset($children[$parent])
            ) {
                throw new \InvalidArgumentException('Derived right provenance is ambiguous or exceeds its source.');
            }
            $children[$parent] = $right['id'];
        }

        foreach ($allocationById as $application) {
            $successor = $application['successor_application_id'];
            $derived = $application['derived_right_id'];
            if ($application['status'] === 'REPLACED_BY_TRANSFER') {
                if (!is_string($successor) || !isset($allocationById[$successor])
                    || $derived !== null || $successor === $application['id']
                    || $allocationById[$successor]['right_id'] !== $application['right_id']
                    || $allocationById[$successor]['amount'] !== $application['amount']
                ) {
                    throw new \InvalidArgumentException('Transfer may not duplicate or change promotional consumption.');
                }
                $incoming[$successor] = ($incoming[$successor] ?? 0) + 1;
            } elseif ($application['status'] === 'REPLACED_BY_DERIVED') {
                if (!isset($children[$application['id']]) || $successor !== null) {
                    throw new \InvalidArgumentException('Historical consumption is not linked to a cancellation right.');
                }
            } elseif ($successor !== null || $derived !== null) {
                throw new \InvalidArgumentException('Live or closed application has a contradictory successor.');
            }
        }
        foreach ($incoming as $count) {
            if ($count !== 1) {
                throw new \InvalidArgumentException('Promotional transfer has multiple predecessors.');
            }
        }

        // Reachability follows child rights and transferred applications.
        // All incoming allocations must be reachable from the JASOM root.
        $visitedRights = [];
        $visitedApplications = [];
        $pendingRights = [$rootRightId];
        $pendingApplications = [];
        while ($pendingRights !== [] || $pendingApplications !== []) {
            while ($pendingRights !== []) {
                $id = array_pop($pendingRights);
                if (isset($visitedRights[$id])) {
                    throw new \InvalidArgumentException('Promotional provenance has a cycle.');
                }
                $visitedRights[$id] = true;
                foreach ($allocationById as $app) {
                    if ($app['right_id'] === $id && !isset($incoming[$app['id']])) {
                        $pendingApplications[] = $app['id'];
                    }
                }
            }
            while ($pendingApplications !== []) {
                $id = array_pop($pendingApplications);
                if (isset($visitedApplications[$id])) {
                    throw new \InvalidArgumentException('Promotional transfer has a cycle or a duplicate branch.');
                }
                $visitedApplications[$id] = true;
                $app = $allocationById[$id];
                if ($app['status'] === 'REPLACED_BY_TRANSFER') {
                    $pendingApplications[] = $app['successor_application_id'];
                }
                if ($app['status'] === 'REPLACED_BY_DERIVED') {
                    $pendingRights[] = $app['derived_right_id'];
                }
            }
        }
        if (count($visitedRights) !== count($rightById)
            || count($visitedApplications) !== count($allocationById)
        ) {
            throw new \InvalidArgumentException('Orphaned or cyclic promotional ledger entries.');
        }

        $cancel = [];
        foreach ($rightById as $right) {
            if ($right['available'] > 0) {
                $cancel[] = [
                    'right_id' => $right['id'],
                    'amount' => $this->money($right['available']),
                ];
            }
        }

        $clawback = [];
        foreach ($allocationById as $app) {
            if ($app['status'] === 'RESERVED') {
                throw new \InvalidArgumentException('Unsettled reservation must be reconciled before JASOM refund.');
            }
            if ($app['status'] === 'ACTIVE') {
                $clawback[] = [
                    'application_id' => $app['id'],
                    'right_id' => $app['right_id'],
                    'amount' => $this->money($app['amount']),
                ];
            }
        }

        $cancelCents = array_sum(array_map(
            fn (array $entry): int => $this->cents($entry['amount']), $cancel
        ));
        $clawbackCents = array_sum(array_map(
            fn (array $entry): int => $this->cents($entry['amount']), $clawback
        ));
        if ($cancelCents + $clawbackCents > $rightById[$rootRightId]['issued']) {
            throw new \InvalidArgumentException('Root refund would recover the same promotional value twice.');
        }
        return [
            'root_right_id' => $rootRightId,
            'cancel_available' => $cancel,
            'recover_active_applications' => $clawback,
            'total_cancel_available' => $this->money($cancelCents),
            'total_recover_active' => $this->money($clawbackCents),
            'review_required' => true,
        ];
    }

    private function cents(string $value): int
    {
        if (!preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/D', trim($value), $m)) {
            throw new \InvalidArgumentException('Invalid promotional amount.');
        }
        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }

    private function money(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
