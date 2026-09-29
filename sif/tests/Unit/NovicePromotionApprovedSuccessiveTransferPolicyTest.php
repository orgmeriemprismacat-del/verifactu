<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Domain\NovicePromotionApprovedSuccessiveTransferPolicy;
use Prisma\Sif\Tests\Support\Assert;

/** Pure binding checks; no MySQL, fiscal issuer or backoffice connector. */
final class NovicePromotionApprovedSuccessiveTransferPolicyTest
{
    public function testDerivedApplicationTransferApprovalMatchesExactReview(): void
    {
        [$approval, $review] = $this->fixture('DERIVED_APPLICATION', 'derived-app-1');
        (new NovicePromotionApprovedSuccessiveTransferPolicy())->assertMatches(
            $approval, 'transfer-2', $review, 'DERIVED_APPLICATION', 'derived-app-1',
            '2026-09-27 00:00:00'
        );
        Assert::same('40.00', $approval['approved_promotional_amount']);
    }

    public function testPreviousTransferApprovalMatchesExactReview(): void
    {
        [$approval, $review] = $this->fixture('PREVIOUS_TRANSFER', 'transfer-1');
        (new NovicePromotionApprovedSuccessiveTransferPolicy())->assertMatches(
            $approval, 'transfer-2', $review, 'PREVIOUS_TRANSFER', 'transfer-1',
            '2026-09-27 00:00:00'
        );
        Assert::same('PREVIOUS_TRANSFER', $approval['source_kind']);
    }

    public function testCannotSwitchSourceKindOrSourceId(): void
    {
        [$approval, $review] = $this->fixture('PREVIOUS_TRANSFER', 'transfer-1');
        $approval['source_kind'] = 'DERIVED_APPLICATION';
        Assert::throws(\InvalidArgumentException::class, static function () use ($approval, $review): void {
            (new NovicePromotionApprovedSuccessiveTransferPolicy())->assertMatches(
                $approval, 'transfer-2', $review, 'PREVIOUS_TRANSFER', 'transfer-1',
                '2026-09-27 00:00:00'
            );
        });

        [$approval, $review] = $this->fixture('PREVIOUS_TRANSFER', 'transfer-1');
        $approval['source_id'] = 'another-transfer';
        Assert::throws(\InvalidArgumentException::class, static function () use ($approval, $review): void {
            (new NovicePromotionApprovedSuccessiveTransferPolicy())->assertMatches(
                $approval, 'transfer-2', $review, 'PREVIOUS_TRANSFER', 'transfer-1',
                '2026-09-27 00:00:00'
            );
        });
    }

    public function testCannotChangeDestinationRectificativeOrAmount(): void
    {
        foreach ([
            ['uuid_new_operation', 'other-course'],
            ['uuid_rectificative', 'other-rectificative'],
            ['approved_promotional_amount', '40.01'],
        ] as [$field, $value]) {
            [$approval, $review] = $this->fixture('DERIVED_APPLICATION', 'derived-app-1');
            $approval[$field] = $value;
            Assert::throws(\InvalidArgumentException::class, static function () use ($approval, $review): void {
                (new NovicePromotionApprovedSuccessiveTransferPolicy())->assertMatches(
                    $approval, 'transfer-2', $review, 'DERIVED_APPLICATION', 'derived-app-1',
                    '2026-09-27 00:00:00'
                );
            });
        }
    }

    public function testPendingOrFutureApprovalFailsClosed(): void
    {
        [$approval, $review] = $this->fixture('PREVIOUS_TRANSFER', 'transfer-1');
        $approval['decision'] = 'PENDING';
        Assert::throws(\InvalidArgumentException::class, static function () use ($approval, $review): void {
            (new NovicePromotionApprovedSuccessiveTransferPolicy())->assertMatches(
                $approval, 'transfer-2', $review, 'PREVIOUS_TRANSFER', 'transfer-1',
                '2026-09-27 00:00:00'
            );
        });

        [$approval, $review] = $this->fixture('PREVIOUS_TRANSFER', 'transfer-1');
        $approval['approved_at_utc'] = '2026-09-27 00:00:01';
        Assert::throws(\InvalidArgumentException::class, static function () use ($approval, $review): void {
            (new NovicePromotionApprovedSuccessiveTransferPolicy())->assertMatches(
                $approval, 'transfer-2', $review, 'PREVIOUS_TRANSFER', 'transfer-1',
                '2026-09-27 00:00:00'
            );
        });
    }

    private function fixture(string $kind, string $sourceId): array
    {
        $review = [
            'CREATED_AT' => '2026-09-26 22:00:00',
            'UUID_RECTIFICATIVE_FACTURA' => 'rectificative-3',
            'TO_UUID_OPERATION' => 'course-c',
            'AMOUNT' => '40.00',
            'POLICY_EVIDENCE_REF' => 'policy-transfer-3',
        ];
        $approval = [
            'review_uuid' => 'transfer-2',
            'decision_type' => 'NOVICE_SUCCESSIVE_DESTINATION_TRANSFER',
            'decision_id' => 'decision-transfer-2',
            'decision' => 'APPROVED',
            'reviewer_id' => 'authenticated-secretariat',
            'evidence_ref' => 'policy-transfer-3',
            'approved_at_utc' => '2026-09-26 22:10:00',
            'source_kind' => $kind,
            'source_id' => $sourceId,
            'uuid_rectificative' => 'rectificative-3',
            'uuid_new_operation' => 'course-c',
            'approved_promotional_amount' => '40.00',
        ];
        return [$approval, $review];
    }
}
