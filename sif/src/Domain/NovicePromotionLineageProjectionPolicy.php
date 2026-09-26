<?php

declare(strict_types=1);

namespace Prisma\Sif\Domain;

/**
 * Pure SQL-ledger -> NovicePromotionLineagePolicy graph projection.
 *
 * It turns original applications, derived applications and transfer records
 * into ONE logical application chain. Historical predecessors replaced by a
 * transfer/derived right are never exposed as live use. Pending fiscal review
 * records fail closed because a JASOM refund must not race an unresolved
 * course change/cancellation.
 *
 * Logical IDs are prefixed by table type to avoid accidental cross-table UUID
 * collisions: root:, right:, app:, dapp:, transfer:.
 */
final class NovicePromotionLineageProjectionPolicy
{
    public function project(
        array $root,
        array $originalApplications,
        array $derivedBalances,
        array $derivedApplications,
        array $transfers
    ): array {
        $rootUuid = (string) ($root['UUID_ENTITLEMENT'] ?? '');
        $rootStatus = (string) ($root['ENTITLEMENT_STATUS'] ?? '');
        if ($rootUuid === ''
            || !in_array($rootStatus, ['ACTIVE', 'CANCELLED', 'EXPIRED'], true)
        ) {
            throw new \InvalidArgumentException('Invalid root entitlement snapshot.');
        }

        $rootRightId = 'root:' . $rootUuid;
        $rights = [[
            'id' => $rootRightId,
            'parent_application_id' => null,
            'issued' => $this->money($this->cents((string) ($root['ORIGINAL_CASH_AMOUNT'] ?? ''))),
            'available' => $this->money($this->cents((string) ($root['AVAILABLE_AMOUNT'] ?? ''))),
            'forfeited' => '0.00',
            'status' => $rootStatus,
        ]];

        $originalById = $this->index($originalApplications, 'UUID_APPLICATION');
        $derivedBalanceById = $this->index($derivedBalances, 'UUID_DERIVED_BALANCE');
        $derivedApplicationById = $this->index($derivedApplications, 'UUID_DERIVED_APPLICATION');
        $transferById = $this->index($transfers, 'UUID_TRANSFER');

        foreach ($derivedBalanceById as $balance) {
            if ($balance['STATUS'] === 'PENDING_FISCAL_REVIEW') {
                throw new \InvalidArgumentException('Pending cancellation review must be resolved before root refund.');
            }
        }
        foreach ($transferById as $transfer) {
            if ($transfer['STATUS'] === 'PENDING_FISCAL_REVIEW') {
                throw new \InvalidArgumentException('Pending course transfer must be resolved before root refund.');
            }
        }

        $transferChildren = [];
        $transferFromOriginal = [];
        $transferFromDerived = [];
        foreach ($transferById as $id => $transfer) {
            $previous = trim((string) ($transfer['PREVIOUS_UUID_TRANSFER'] ?? ''));
            $original = trim((string) ($transfer['UUID_ORIGINAL_APPLICATION'] ?? ''));
            $derived = trim((string) ($transfer['UUID_DERIVED_APPLICATION'] ?? ''));

            if ($previous !== '') {
                $this->singleEdge($transferChildren, $previous, $id, 'previous transfer');
            } elseif ($original !== '') {
                $this->singleEdge($transferFromOriginal, $original, $id, 'original application transfer');
            } elseif ($derived !== '') {
                $this->singleEdge($transferFromDerived, $derived, $id, 'derived application transfer');
            } else {
                throw new \InvalidArgumentException('Transfer has no promotional predecessor.');
            }
        }

        $derivedFromOriginal = [];
        $derivedFromApplication = [];
        $derivedFromTransfer = [];
        foreach ($derivedBalanceById as $id => $balance) {
            if ($balance['STATUS'] === 'REJECTED') {
                continue;
            }
            $sourceOriginal = trim((string) ($balance['SOURCE_UUID_APPLICATION'] ?? ''));
            $sourceDerived = trim((string) ($balance['SOURCE_UUID_DERIVED_APPLICATION'] ?? ''));
            $sourceTransfer = trim((string) ($balance['SOURCE_UUID_TRANSFER'] ?? ''));

            $nonEmpty = ($sourceOriginal !== '' ? 1 : 0)
                + ($sourceDerived !== '' ? 1 : 0)
                + ($sourceTransfer !== '' ? 1 : 0);
            if ($nonEmpty !== 1) {
                throw new \InvalidArgumentException('Issued derived right has ambiguous provenance.');
            }

            $declaredParent = trim((string) ($balance['PARENT_UUID_DERIVED_BALANCE'] ?? ''));
            if ($sourceOriginal !== '') {
                if ($declaredParent !== '') {
                    throw new \InvalidArgumentException('Original application child right cannot invent a derived parent.');
                }
                $this->singleEdge($derivedFromOriginal, $sourceOriginal, $id, 'original application derived right');
                $parentApplicationId = 'app:' . $sourceOriginal;
            } elseif ($sourceDerived !== '') {
                if (!isset($derivedApplicationById[$sourceDerived])
                    || $declaredParent === ''
                    || $declaredParent !== (string) $derivedApplicationById[$sourceDerived]['UUID_DERIVED_BALANCE']
                ) {
                    throw new \InvalidArgumentException('Derived child right does not match its parent balance.');
                }
                $this->singleEdge($derivedFromApplication, $sourceDerived, $id, 'derived application child right');
                $parentApplicationId = 'dapp:' . $sourceDerived;
            } else {
                $this->singleEdge($derivedFromTransfer, $sourceTransfer, $id, 'transfer child right');
                $parentApplicationId = 'transfer:' . $sourceTransfer;
            }

            $status = (string) $balance['STATUS'];
            if (!in_array($status, ['ACTIVE', 'CANCELLED', 'EXPIRED'], true)) {
                throw new \InvalidArgumentException('Derived right has unsupported issued state.');
            }
            $rights[] = [
                'id' => 'right:' . $id,
                'parent_application_id' => $parentApplicationId,
                'issued' => $this->money($this->cents((string) $balance['PROMOTIONAL_ORIGIN_AMOUNT'])),
                'available' => $this->money($this->cents((string) $balance['AVAILABLE_PROMOTIONAL_AMOUNT'])),
                'forfeited' => '0.00',
                'status' => $status,
            ];
        }

        $applications = [];
        foreach ($originalById as $id => $row) {
            [$status, $successor, $derivedRight] = $this->projectOriginalApplication(
                $id,
                $row,
                $transferFromOriginal,
                $derivedFromOriginal
            );
            $applications[] = [
                'id' => 'app:' . $id,
                'right_id' => $rootRightId,
                'amount' => $this->money($this->cents((string) $row['AMOUNT'])),
                'status' => $status,
                'successor_application_id' => $successor,
                'derived_right_id' => $derivedRight,
            ];
        }

        foreach ($derivedApplicationById as $id => $row) {
            $balanceId = (string) ($row['UUID_DERIVED_BALANCE'] ?? '');
            if ($balanceId === '' || !isset($derivedBalanceById[$balanceId])
                || $derivedBalanceById[$balanceId]['STATUS'] === 'REJECTED'
            ) {
                throw new \InvalidArgumentException('Derived application has no issued parent right.');
            }
            [$status, $successor, $derivedRight] = $this->projectDerivedApplication(
                $id,
                $row,
                $transferFromDerived,
                $derivedFromApplication
            );
            $applications[] = [
                'id' => 'dapp:' . $id,
                'right_id' => 'right:' . $balanceId,
                'amount' => $this->money($this->cents((string) $row['AMOUNT'])),
                'status' => $status,
                'successor_application_id' => $successor,
                'derived_right_id' => $derivedRight,
            ];
        }

        $transferRightMemo = [];
        foreach ($transferById as $id => $row) {
            if ($row['STATUS'] === 'CANCELLED'
                && !in_array(
                    (string) ($row['CLOSE_REASON'] ?? ''),
                    ['TRANSFERRED_TO_COURSE', 'CONVERTED_TO_DERIVED'],
                    true
                )
            ) {
                // A never-confirmed rejected review is not exposure. Once a
                // cancellation reason taxonomy is implemented it can be
                // explicitly ignored; unknown cancellations fail closed now.
                throw new \InvalidArgumentException('Cancelled transfer has unsupported close reason.');
            }

            $rightId = $this->transferRightId(
                $id,
                $transferById,
                $originalById,
                $derivedApplicationById,
                $derivedBalanceById,
                $transferRightMemo,
                []
            );

            [$status, $successor, $derivedRight] = $this->projectTransfer(
                $id,
                $row,
                $transferChildren,
                $derivedFromTransfer
            );
            $applications[] = [
                'id' => 'transfer:' . $id,
                'right_id' => $rightId,
                'amount' => $this->money($this->cents((string) $row['AMOUNT'])),
                'status' => $status,
                'successor_application_id' => $successor,
                'derived_right_id' => $derivedRight,
            ];
        }

        // A right born from a transfer must declare the same parent derived
        // balance that the transfer was moving, unless the transfer belongs
        // directly to the root JASOM right (then parent must be NULL).
        foreach ($derivedBalanceById as $balanceId => $balance) {
            if ($balance['STATUS'] === 'REJECTED') {
                continue;
            }
            $sourceTransfer = trim((string) ($balance['SOURCE_UUID_TRANSFER'] ?? ''));
            if ($sourceTransfer === '') {
                continue;
            }
            $rightId = $this->transferRightId(
                $sourceTransfer,
                $transferById,
                $originalById,
                $derivedApplicationById,
                $derivedBalanceById,
                $transferRightMemo,
                []
            );
            $declaredParent = trim((string) ($balance['PARENT_UUID_DERIVED_BALANCE'] ?? ''));
            if ($rightId === $rootRightId) {
                if ($declaredParent !== '') {
                    throw new \InvalidArgumentException('Root transfer child right cannot invent a derived parent.');
                }
            } else {
                $expectedParent = str_starts_with($rightId, 'right:')
                    ? substr($rightId, 6)
                    : '';
                if ($expectedParent === '' || $declaredParent !== $expectedParent) {
                    throw new \InvalidArgumentException('Transfer-derived right does not match the right being moved.');
                }
            }
        }

        // All non-rejected derived rights must point to an application that
        // actually exists in the projected graph. LineagePolicy will perform
        // the deeper amount/reachability/cycle checks afterwards.
        $appIds = array_fill_keys(array_column($applications, 'id'), true);
        foreach ($rights as $right) {
            if ($right['parent_application_id'] !== null
                && !isset($appIds[$right['parent_application_id']])
            ) {
                throw new \InvalidArgumentException('Derived right points to a missing logical predecessor.');
            }
        }

        return [
            'root_right_id' => $rootRightId,
            'rights' => $rights,
            'applications' => $applications,
        ];
    }

    private function projectOriginalApplication(
        string $id,
        array $row,
        array $transfers,
        array $derived
    ): array {
        return match ((string) $row['STATUS']) {
            'RESERVED' => ['RESERVED', null, null],
            'APPLIED' => ['ACTIVE', null, null],
            'RELEASED' => ['RELEASED', null, null],
            'REVERSED' => match ((string) ($row['REASON_CODE'] ?? '')) {
                'TRANSFERRED_TO_COURSE' => [
                    'REPLACED_BY_TRANSFER',
                    $this->requiredLogicalEdge($transfers, $id, 'transfer:'),
                    null,
                ],
                'CONVERTED_TO_DERIVED' => [
                    'REPLACED_BY_DERIVED',
                    null,
                    $this->requiredLogicalEdge($derived, $id, 'right:'),
                ],
                default => throw new \InvalidArgumentException('Reversed original application has unknown lineage reason.'),
            },
            default => throw new \InvalidArgumentException('Original application has unsupported state.'),
        };
    }

    private function projectDerivedApplication(
        string $id,
        array $row,
        array $transfers,
        array $derived
    ): array {
        return match ((string) $row['STATUS']) {
            'RESERVED' => ['RESERVED', null, null],
            'APPLIED' => ['ACTIVE', null, null],
            'RELEASED' => ['RELEASED', null, null],
            'CANCELLED' => ['CANCELLED', null, null],
            'TRANSFERRED' => [
                'REPLACED_BY_TRANSFER',
                $this->requiredLogicalEdge($transfers, $id, 'transfer:'),
                null,
            ],
            'CONVERTED_TO_DERIVED' => [
                'REPLACED_BY_DERIVED',
                null,
                $this->requiredLogicalEdge($derived, $id, 'right:'),
            ],
            default => throw new \InvalidArgumentException('Derived application has unsupported state.'),
        };
    }

    private function projectTransfer(
        string $id,
        array $row,
        array $children,
        array $derived
    ): array {
        if ($row['STATUS'] === 'CONFIRMED') {
            return ['ACTIVE', null, null];
        }
        if ($row['STATUS'] !== 'CANCELLED') {
            throw new \InvalidArgumentException('Transfer has unsupported current state.');
        }
        return match ((string) ($row['CLOSE_REASON'] ?? '')) {
            'TRANSFERRED_TO_COURSE' => [
                'REPLACED_BY_TRANSFER',
                $this->requiredLogicalEdge($children, $id, 'transfer:'),
                null,
            ],
            'CONVERTED_TO_DERIVED' => [
                'REPLACED_BY_DERIVED',
                null,
                $this->requiredLogicalEdge($derived, $id, 'right:'),
            ],
            default => throw new \InvalidArgumentException('Historical transfer has unknown lineage reason.'),
        };
    }

    private function transferRightId(
        string $id,
        array $transfers,
        array $originals,
        array $derivedApplications,
        array $derivedBalances,
        array &$memo,
        array $visiting
    ): string {
        if (isset($memo[$id])) {
            return $memo[$id];
        }
        if (isset($visiting[$id]) || !isset($transfers[$id])) {
            throw new \InvalidArgumentException('Transfer lineage is cyclic or missing.');
        }
        $visiting[$id] = true;
        $row = $transfers[$id];

        $original = trim((string) ($row['UUID_ORIGINAL_APPLICATION'] ?? ''));
        $derived = trim((string) ($row['UUID_DERIVED_APPLICATION'] ?? ''));
        $previous = trim((string) ($row['PREVIOUS_UUID_TRANSFER'] ?? ''));

        if ($original !== '' && $derived === '' && $previous === '') {
            if (!isset($originals[$original])) {
                throw new \InvalidArgumentException('Transfer references a missing original application.');
            }
            return $memo[$id] = 'root:' . (string) $row['ROOT_UUID_ENTITLEMENT'];
        }

        if ($derived !== '' && $original === '' && $previous === '') {
            if (!isset($derivedApplications[$derived])) {
                throw new \InvalidArgumentException('Transfer references a missing derived application.');
            }
            $balanceId = (string) $derivedApplications[$derived]['UUID_DERIVED_BALANCE'];
            if (!isset($derivedBalances[$balanceId])
                || $derivedBalances[$balanceId]['STATUS'] === 'REJECTED'
            ) {
                throw new \InvalidArgumentException('Transfer references a non-issued derived right.');
            }
            return $memo[$id] = 'right:' . $balanceId;
        }

        if ($previous !== '' && $original === '' && $derived === '') {
            return $memo[$id] = $this->transferRightId(
                $previous,
                $transfers,
                $originals,
                $derivedApplications,
                $derivedBalances,
                $memo,
                $visiting
            );
        }

        throw new \InvalidArgumentException('Transfer has ambiguous source columns.');
    }

    private function requiredLogicalEdge(
        array $map,
        string $sourceId,
        string $prefix
    ): string {
        if (!isset($map[$sourceId])) {
            throw new \InvalidArgumentException('Historical exposure is missing its successor.');
        }
        return $prefix . $map[$sourceId];
    }

    private function singleEdge(
        array &$map,
        string $source,
        string $target,
        string $name
    ): void {
        if (isset($map[$source]) && $map[$source] !== $target) {
            throw new \InvalidArgumentException('Promotional ' . $name . ' branches into multiple successors.');
        }
        $map[$source] = $target;
    }

    private function index(array $rows, string $key): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $id = (string) ($row[$key] ?? '');
            if ($id === '' || isset($indexed[$id])) {
                throw new \InvalidArgumentException('Duplicate or missing ledger identifier: ' . $key);
            }
            $indexed[$id] = $row;
        }
        return $indexed;
    }

    private function cents(string $amount): int
    {
        if (preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/D', trim($amount), $match) !== 1) {
            throw new \InvalidArgumentException('Invalid promotional ledger amount.');
        }
        return (int) $match[1] * 100 + (int) str_pad($match[2] ?? '', 2, '0');
    }

    private function money(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
