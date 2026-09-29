<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

use Prisma\Sif\Domain\NovicePromotionLineagePolicy;
use Prisma\Sif\Exception\SifException;

/**
 * UC-111 / DEC-23: build a NO-SIDE-EFFECT root-JASOM refund plan from the
 * authoritative locked SQL lineage.
 *
 * The caller must already own a transaction. The returned plan is a proposal
 * for authorized review: cancel still-available root/derived balances and
 * recover only CURRENT active promotional applications. No invoice, refund,
 * charge, entitlement state or balance is changed here.
 */
final class NovicePromotionRootRefundPlanService
{
    public function __construct(
        private NovicePromotionLineageSnapshotService $snapshots
            = new NovicePromotionLineageSnapshotService(),
        private NovicePromotionLineagePolicy $policy
            = new NovicePromotionLineagePolicy()
    ) {
    }

    public function planLocked(\PDO $db, string $rootUuid): array
    {
        if (!$db->inTransaction()) {
            throw new \LogicException('Root refund planning must run inside the caller transaction.');
        }

        $snapshot = $this->snapshots->projectLocked($db, $rootUuid);
        try {
            $plan = $this->policy->planOriginalRefund(
                $snapshot['graph']['root_right_id'],
                $snapshot['graph']['rights'],
                $snapshot['graph']['applications']
            );
        } catch (\InvalidArgumentException $exception) {
            throw SifException::conflict(
                'Novice promotion cannot be refunded safely: '
                . $exception->getMessage()
            );
        }

        return [
            'root' => $snapshot['root'],
            'ledger_counts' => $snapshot['ledger_counts'],
            'cancel_available' => $plan['cancel_available'],
            'recover_active_applications' => $plan['recover_active_applications'],
            'total_cancel_available' => $plan['total_cancel_available'],
            'total_recover_active' => $plan['total_recover_active'],
            'review_required' => true,
            'execution_performed' => false,
        ];
    }
}
