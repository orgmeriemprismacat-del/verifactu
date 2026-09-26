<?php

declare(strict_types=1);

namespace Prisma\Sif\Domain;

/**
 * Pure binding of confirmed JASOM refund evidence to the frozen UC-111 review.
 * The evidence adapter is responsible for authenticating the provider/account.
 */
final class NovicePromotionOriginRefundEvidencePolicy
{
    public function assertMatches(
        array $evidence,
        array $review,
        string $expectedRefundAmount,
        string $nowUtc
    ): void {
        $expected = [
            'review_uuid' => (string) ($review['UUID_REVIEW'] ?? ''),
            'root_uuid_entitlement' => (string) ($review['ROOT_UUID_ENTITLEMENT'] ?? ''),
            'origin_uuid_operation' => (string) ($review['ORIGIN_UUID_OPERATION'] ?? ''),
            'refunded_amount' => $expectedRefundAmount,
        ];
        foreach ($expected as $key => $value) {
            if ($value === '' || !isset($evidence[$key]) || !is_string($evidence[$key])
                || !hash_equals($value, $evidence[$key])
            ) {
                throw new \InvalidArgumentException('Origin refund evidence does not match the frozen review.');
            }
        }

        foreach (['refund_evidence_id', 'confirmed_at_utc'] as $key) {
            if (!isset($evidence[$key]) || !is_string($evidence[$key])
                || trim($evidence[$key]) === ''
            ) {
                throw new \InvalidArgumentException('Origin refund lacks confirmed external evidence.');
            }
        }
        if (strlen($evidence['refund_evidence_id']) > 140) {
            throw new \InvalidArgumentException('Origin refund evidence identifier is too long.');
        }

        $requestedAt = (string) ($review['REQUESTED_AT'] ?? '');
        $confirmedAt = (string) $evidence['confirmed_at_utc'];
        if (preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/D', $requestedAt) !== 1
            || preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/D', $confirmedAt) !== 1
            || preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/D', $nowUtc) !== 1
            || $confirmedAt < $requestedAt || $confirmedAt > $nowUtc
        ) {
            throw new \InvalidArgumentException('Origin refund confirmation chronology is invalid.');
        }
    }
}
