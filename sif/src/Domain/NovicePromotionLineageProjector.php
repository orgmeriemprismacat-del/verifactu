<?php

declare(strict_types=1);

namespace Prisma\Sif\Domain;

/**
 * UC-111: project persisted SQL lineage rows into the pure graph expected by
 * NovicePromotionLineagePolicy.
 *
 * NO database access and NO refund effects. The caller must provide a
 * consistent snapshot captured under locks:
 * - root grant/right,
 * - every novice_promotion_application,
 * - every novice_promotion_derived_balance,
 * - every novice_promotion_derived_application,
 * - every novice_promotion_application_transfer.
 *
 * Confirmed transfer rows are represented as synthetic graph applications
 * "transfer:<uuid>". A predecessor marked REVERSED/TRANSFERRED_TO_COURSE or
 * TRANSFERRED is historical and points to the transfer node; a transfer closed
 * with CONVERTED_TO_DERIVED is historical and points to the derived right.
 */
final class NovicePromotionLineageProjector
{
    public function project(
        array $rootGrant,
        array $originalApplications,
        array $derivedBalances,
        array $derivedApplications,
        array $transfers
    ): array {
        $rootId = (string) ($rootGrant['UUID_ENTITLEMENT'] ?? '');
        if ($rootId === '') {
            throw new \InvalidArgumentException('Missing root novice entitlement.');
        }

        $rights = [[
            'id' => $rootId,
            'parent_application_id' => null,
            'issued' => $this->money($this->cents((string) ($rootGrant['ORIGINAL_CASH_AMOUNT'] ?? ''))),
            'available' => $this->money($this->cents((string) ($rootGrant['AVAILABLE_AMOUNT'] ?? ''))),
            'forfeited' => $this->money($this->cents((string) ($rootGrant['FORFEITED_AMOUNT'] ?? '0.00'))),
            'status' => $this->rightStatus((string) ($rootGrant['STATUS'] ?? '')),
        ]];

        $originalById = [];
        foreach ($originalApplications as $row) {
            $id = (string) ($row['UUID_APPLICATION'] ?? '');
            if ($id === '' || isset($originalById[$id])) {
                throw new \InvalidArgumentException('Duplicate original promotional application.');
            }
            if ((string) ($row['UUID_ENTITLEMENT'] ?? '') !== $rootId) {
                throw new \InvalidArgumentException('Original application belongs to another novice root.');
            }
            $originalById[$id] = $row;
        }

        $derivedById = [];
        foreach ($derivedApplications as $row) {
            $id = (string) ($row['UUID_DERIVED_APPLICATION'] ?? '');
            if ($id === '' || isset($derivedById[$id])) {
                throw new \InvalidArgumentException('Duplicate derived promotional application.');
            }
            if ((string) ($row['ROOT_UUID_ENTITLEMENT'] ?? '') !== $rootId) {
                throw new \InvalidArgumentException('Derived application belongs to another novice root.');
            }
            $derivedById[$id] = $row;
        }

        $transferById = [];
        $childTransferByOriginal = [];
        $childTransferByDerived = [];
        $childTransferByPrevious = [];
        foreach ($transfers as $row) {
            $id = (string) ($row['UUID_TRANSFER'] ?? '');
            if ($id === '' || isset($transferById[$id])) {
                throw new \InvalidArgumentException('Duplicate promotional transfer.');
            }
            if ((string) ($row['ROOT_UUID_ENTITLEMENT'] ?? '') !== $rootId) {
                throw new \InvalidArgumentException('Transfer belongs to another novice root.');
            }
            $transferById[$id] = $row;

            $sourceOriginal = $this->nullable($row['UUID_ORIGINAL_APPLICATION'] ?? null);
            $sourceDerived = $this->nullable($row['UUID_DERIVED_APPLICATION'] ?? null);
            $sourcePrevious = $this->nullable($row['PREVIOUS_UUID_TRANSFER'] ?? null);
            $sources = (int) ($sourceOriginal !== null)
                + (int) ($sourceDerived !== null)
                + (int) ($sourcePrevious !== null);
            if ($sources !== 1) {
                throw new \InvalidArgumentException('Transfer must have exactly one lineage source.');
            }

            if ($sourceOriginal !== null) {
                if (!isset($originalById[$sourceOriginal]) || isset($childTransferByOriginal[$sourceOriginal])) {
                    throw new \InvalidArgumentException('Original application has ambiguous transfer child.');
                }
                $childTransferByOriginal[$sourceOriginal] = $id;
            } elseif ($sourceDerived !== null) {
                if (!isset($derivedById[$sourceDerived]) || isset($childTransferByDerived[$sourceDerived])) {
                    throw new \InvalidArgumentException('Derived application has ambiguous transfer child.');
                }
                $childTransferByDerived[$sourceDerived] = $id;
            } else {
                if ($sourcePrevious === $id || isset($childTransferByPrevious[$sourcePrevious])) {
                    throw new \InvalidArgumentException('Transfer chain branches or loops directly.');
                }
                $childTransferByPrevious[$sourcePrevious] = $id;
            }
        }

        $childDerivedByOriginal = [];
        $childDerivedByDerived = [];
        $childDerivedByTransfer = [];
        foreach ($derivedBalances as $row) {
            $id = (string) ($row['UUID_DERIVED_BALANCE'] ?? '');
            if ($id === '' || $id === $rootId) {
                throw new \InvalidArgumentException('Invalid derived promotional right identifier.');
            }
            if ((string) ($row['ROOT_UUID_ENTITLEMENT'] ?? '') !== $rootId) {
                throw new \InvalidArgumentException('Derived right belongs to another novice root.');
            }

            $sourceOriginal = $this->nullable($row['SOURCE_UUID_APPLICATION'] ?? null);
            $sourceDerived = $this->nullable($row['SOURCE_UUID_DERIVED_APPLICATION'] ?? null);
            $sourceTransfer = $this->nullable($row['SOURCE_UUID_TRANSFER'] ?? null);
            $sources = (int) ($sourceOriginal !== null)
                + (int) ($sourceDerived !== null)
                + (int) ($sourceTransfer !== null);
            if ($sources !== 1) {
                throw new \InvalidArgumentException('Derived right must have exactly one source.');
            }

            if ($sourceOriginal !== null) {
                if (!isset($originalById[$sourceOriginal]) || isset($childDerivedByOriginal[$sourceOriginal])) {
                    throw new \InvalidArgumentException('Original application has ambiguous derived child.');
                }
                $parentApplicationId = $sourceOriginal;
                $childDerivedByOriginal[$sourceOriginal] = $id;
            } elseif ($sourceDerived !== null) {
                if (!isset($derivedById[$sourceDerived]) || isset($childDerivedByDerived[$sourceDerived])) {
                    throw new \InvalidArgumentException('Derived application has ambiguous derived child.');
                }
                $parentApplicationId = $sourceDerived;
                $childDerivedByDerived[$sourceDerived] = $id;
            } else {
                if (!isset($transferById[$sourceTransfer]) || isset($childDerivedByTransfer[$sourceTransfer])) {
                    throw new \InvalidArgumentException('Transfer has ambiguous derived child.');
                }
                $parentApplicationId = $this->transferNode($sourceTransfer);
                $childDerivedByTransfer[$sourceTransfer] = $id;
            }

            $rights[] = [
                'id' => $id,
                'parent_application_id' => $parentApplicationId,
                'issued' => $this->money($this->cents((string) ($row['PROMOTIONAL_ORIGIN_AMOUNT'] ?? ''))),
                'available' => $this->money($this->cents((string) ($row['AVAILABLE_PROMOTIONAL_AMOUNT'] ?? ''))),
                'forfeited' => $this->money($this->snapshotForfeited($row)),
                'status' => $this->rightStatus((string) ($row['STATUS'] ?? '')),
            ];
        }

        $applications = [];
        foreach ($originalById as $id => $row) {
            $applications[] = $this->applicationNode(
                $id,
                $rootId,
                (string) ($row['AMOUNT'] ?? ''),
                (string) ($row['STATUS'] ?? ''),
                (string) ($row['REASON_CODE'] ?? ''),
                $childTransferByOriginal[$id] ?? null,
                $childDerivedByOriginal[$id] ?? null,
                false
            );
        }

        $derivedBalanceIds = [];
        foreach ($derivedBalances as $row) {
            $derivedBalanceIds[(string) $row['UUID_DERIVED_BALANCE']] = true;
        }

        foreach ($derivedById as $id => $row) {
            $rightId = (string) ($row['UUID_DERIVED_BALANCE'] ?? '');
            if (!isset($derivedBalanceIds[$rightId])) {
                throw new \InvalidArgumentException('Derived application points to a missing derived right.');
            }
            $applications[] = $this->applicationNode(
                $id,
                $rightId,
                (string) ($row['AMOUNT'] ?? ''),
                (string) ($row['STATUS'] ?? ''),
                (string) ($row['REASON_CODE'] ?? ''),
                $childTransferByDerived[$id] ?? null,
                $childDerivedByDerived[$id] ?? null,
                true
            );
        }

        $resolvedTransferRight = [];
        foreach (array_keys($transferById) as $id) {
            $this->resolveTransferRight(
                $id,
                $transferById,
                $originalById,
                $derivedById,
                $resolvedTransferRight,
                []
            );
        }

        foreach ($transferById as $id => $row) {
            $nodeId = $this->transferNode($id);
            $status = (string) ($row['STATUS'] ?? '');
            $closeReason = (string) ($row['CLOSE_REASON'] ?? '');
            $successor = $childTransferByPrevious[$id] ?? null;
            $derivedChild = $childDerivedByTransfer[$id] ?? null;

            if ($status === 'CONFIRMED') {
                if ($successor !== null || $derivedChild !== null) {
                    throw new \InvalidArgumentException('Confirmed transfer cannot simultaneously have a replacement child.');
                }
                $graphStatus = 'ACTIVE';
                $successorNode = null;
                $derivedRight = null;
            } elseif ($status === 'CANCELLED' && $closeReason === 'CONVERTED_TO_DERIVED') {
                if ($derivedChild === null || $successor !== null) {
                    throw new \InvalidArgumentException('Converted transfer must point to exactly one derived right.');
                }
                $graphStatus = 'REPLACED_BY_DERIVED';
                $successorNode = null;
                $derivedRight = $derivedChild;
            } elseif ($status === 'CANCELLED' && $closeReason === 'TRANSFERRED_TO_COURSE') {
                if ($successor === null || $derivedChild !== null) {
                    throw new \InvalidArgumentException('Historical transfer must point to exactly one successor transfer.');
                }
                $graphStatus = 'REPLACED_BY_TRANSFER';
                $successorNode = $this->transferNode($successor);
                $derivedRight = null;
            } elseif ($status === 'PENDING_FISCAL_REVIEW') {
                throw new \InvalidArgumentException('Pending transfer must be reconciled before JASOM refund planning.');
            } else {
                throw new \InvalidArgumentException('Unsupported transfer state in lineage projection.');
            }

            $applications[] = [
                'id' => $nodeId,
                'right_id' => $resolvedTransferRight[$id],
                'amount' => $this->money($this->cents((string) ($row['AMOUNT'] ?? ''))),
                'status' => $graphStatus,
                'successor_application_id' => $successorNode,
                'derived_right_id' => $derivedRight,
            ];
        }

        return [
            'root_right_id' => $rootId,
            'rights' => $rights,
            'applications' => $applications,
        ];
    }

    private function applicationNode(
        string $id,
        string $rightId,
        string $amount,
        string $status,
        string $reason,
        ?string $transferChild,
        ?string $derivedChild,
        bool $derivedApplication
    ): array {
        if ($status === 'APPLIED') {
            if ($transferChild !== null || $derivedChild !== null) {
                throw new \InvalidArgumentException('Applied promotion cannot already have a replacement child.');
            }
            $graph = 'ACTIVE';
            $successor = null;
            $derived = null;
        } elseif (
            (!$derivedApplication && $status === 'REVERSED' && $reason === 'TRANSFERRED_TO_COURSE')
            || ($derivedApplication && $status === 'TRANSFERRED')
        ) {
            if ($transferChild === null || $derivedChild !== null) {
                throw new \InvalidArgumentException('Transferred application must have exactly one transfer child.');
            }
            $graph = 'REPLACED_BY_TRANSFER';
            $successor = $this->transferNode($transferChild);
            $derived = null;
        } elseif (
            (!$derivedApplication && $status === 'REVERSED' && $reason === 'CONVERTED_TO_DERIVED')
            || ($derivedApplication && $status === 'CONVERTED_TO_DERIVED')
        ) {
            if ($derivedChild === null || $transferChild !== null) {
                throw new \InvalidArgumentException('Converted application must have exactly one derived child.');
            }
            $graph = 'REPLACED_BY_DERIVED';
            $successor = null;
            $derived = $derivedChild;
        } elseif ($status === 'RESERVED') {
            $graph = 'RESERVED';
            $successor = null;
            $derived = null;
        } elseif ($status === 'RELEASED') {
            $graph = 'RELEASED';
            $successor = null;
            $derived = null;
        } elseif ($status === 'CANCELLED') {
            $graph = 'CANCELLED';
            $successor = null;
            $derived = null;
        } else {
            throw new \InvalidArgumentException('Unsupported application state in lineage projection.');
        }

        return [
            'id' => $id,
            'right_id' => $rightId,
            'amount' => $this->money($this->cents($amount)),
            'status' => $graph,
            'successor_application_id' => $successor,
            'derived_right_id' => $derived,
        ];
    }

    private function resolveTransferRight(
        string $id,
        array $transfers,
        array $originalApplications,
        array $derivedApplications,
        array &$cache,
        array $stack
    ): string {
        if (isset($cache[$id])) {
            return $cache[$id];
        }
        if (isset($stack[$id])) {
            throw new \InvalidArgumentException('Transfer lineage contains a cycle.');
        }
        $stack[$id] = true;
        $row = $transfers[$id];

        $original = $this->nullable($row['UUID_ORIGINAL_APPLICATION'] ?? null);
        if ($original !== null) {
            if (!isset($originalApplications[$original])) {
                throw new \InvalidArgumentException('Transfer source original application is missing.');
            }
            return $cache[$id] = (string) $originalApplications[$original]['UUID_ENTITLEMENT'];
        }

        $derived = $this->nullable($row['UUID_DERIVED_APPLICATION'] ?? null);
        if ($derived !== null) {
            if (!isset($derivedApplications[$derived])) {
                throw new \InvalidArgumentException('Transfer source derived application is missing.');
            }
            return $cache[$id] = (string) $derivedApplications[$derived]['UUID_DERIVED_BALANCE'];
        }

        $previous = $this->nullable($row['PREVIOUS_UUID_TRANSFER'] ?? null);
        if ($previous === null || !isset($transfers[$previous])) {
            throw new \InvalidArgumentException('Transfer predecessor is missing.');
        }
        return $cache[$id] = $this->resolveTransferRight(
            $previous,
            $transfers,
            $originalApplications,
            $derivedApplications,
            $cache,
            $stack
        );
    }

    private function snapshotForfeited(array $row): int
    {
        $snapshot = json_decode((string) ($row['POLICY_SNAPSHOT_JSON'] ?? '{}'), true);
        if (!is_array($snapshot)) {
            throw new \InvalidArgumentException('Derived right policy snapshot is invalid.');
        }
        $decision = $snapshot['review_decision'] ?? null;
        if (!is_array($decision)) {
            return 0;
        }
        return $this->cents((string) ($decision['promotional_forfeited_amount'] ?? '0.00'));
    }

    private function rightStatus(string $status): string
    {
        return match ($status) {
            'ACTIVE' => 'ACTIVE',
            'CANCELLED' => 'CANCELLED',
            'EXPIRED' => 'EXPIRED',
            default => throw new \InvalidArgumentException('Pending or rejected right cannot enter refund lineage.'),
        };
    }

    private function transferNode(string $uuid): string
    {
        return 'transfer:' . $uuid;
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function cents(string $amount): int
    {
        if (preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/D', trim($amount), $match) !== 1) {
            throw new \InvalidArgumentException('Invalid promotional lineage amount.');
        }
        return (int) $match[1] * 100 + (int) str_pad($match[2] ?? '', 2, '0');
    }

    private function money(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
