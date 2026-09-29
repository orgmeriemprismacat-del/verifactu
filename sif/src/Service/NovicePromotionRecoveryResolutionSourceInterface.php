<?php

declare(strict_types=1);

namespace Prisma\Sif\Service;

/**
 * Trusted external/accounting evidence for resolving a UC-111 recovery item.
 *
 * Implementations MUST independently verify the referenced settlement/waiver
 * in the authoritative backoffice/payment/accounting system. Browser data,
 * free-form operator text and the recovery row itself are not sufficient.
 */
interface NovicePromotionRecoveryResolutionSourceInterface
{
    /**
     * @return array<string, string>|null Mandatory keys:
     * recovery_uuid, resolution_id, resolution, resolved_by, evidence_ref,
     * resolved_at_utc, root_uuid_entitlement, source_kind, source_uuid,
     * uuid_destination_operation, amount.
     *
     * resolution: RECOVERED | WAIVED | CANCELLED
     */
    public function resolvedRecovery(string $uuidRecovery): ?array;
}
