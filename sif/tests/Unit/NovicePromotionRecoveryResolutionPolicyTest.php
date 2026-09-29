<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Domain\NovicePromotionRecoveryResolutionPolicy;
use Prisma\Sif\Tests\Support\Assert;

final class NovicePromotionRecoveryResolutionPolicyTest
{
    public function testRecoveredEvidenceMatchesExactPendingItem(): void
    {
        [$evidence, $recovery] = $this->fixture('RECOVERED');
        (new NovicePromotionRecoveryResolutionPolicy())->assertMatches(
            $evidence, $recovery, '2026-09-27 01:00:00'
        );
        Assert::same('40.00', $evidence['amount']);
    }

    public function testWaiverAlsoRequiresExactAmountAndSource(): void
    {
        [$evidence, $recovery] = $this->fixture('WAIVED');
        $evidence['source_uuid'] = 'other-transfer';
        Assert::throws(\InvalidArgumentException::class, static function () use ($evidence, $recovery): void {
            (new NovicePromotionRecoveryResolutionPolicy())->assertMatches(
                $evidence, $recovery, '2026-09-27 01:00:00'
            );
        });

        [$evidence, $recovery] = $this->fixture('WAIVED');
        $evidence['amount'] = '39.99';
        Assert::throws(\InvalidArgumentException::class, static function () use ($evidence, $recovery): void {
            (new NovicePromotionRecoveryResolutionPolicy())->assertMatches(
                $evidence, $recovery, '2026-09-27 01:00:00'
            );
        });
    }

    public function testDifferentDestinationCannotResolveRecovery(): void
    {
        [$evidence, $recovery] = $this->fixture('RECOVERED');
        $evidence['uuid_destination_operation'] = 'another-course';
        Assert::throws(\InvalidArgumentException::class, static function () use ($evidence, $recovery): void {
            (new NovicePromotionRecoveryResolutionPolicy())->assertMatches(
                $evidence, $recovery, '2026-09-27 01:00:00'
            );
        });
    }

    public function testFutureOrPredatingEvidenceFailsClosed(): void
    {
        [$evidence, $recovery] = $this->fixture('RECOVERED');
        $evidence['resolved_at_utc'] = '2026-09-27 00:29:59';
        Assert::throws(\InvalidArgumentException::class, static function () use ($evidence, $recovery): void {
            (new NovicePromotionRecoveryResolutionPolicy())->assertMatches(
                $evidence, $recovery, '2026-09-27 01:00:00'
            );
        });

        [$evidence, $recovery] = $this->fixture('RECOVERED');
        $evidence['resolved_at_utc'] = '2026-09-27 01:00:01';
        Assert::throws(\InvalidArgumentException::class, static function () use ($evidence, $recovery): void {
            (new NovicePromotionRecoveryResolutionPolicy())->assertMatches(
                $evidence, $recovery, '2026-09-27 01:00:00'
            );
        });
    }

    public function testAlreadyResolvedItemCannotBeResolvedAgain(): void
    {
        [$evidence, $recovery] = $this->fixture('RECOVERED');
        $recovery['STATUS'] = 'RECOVERED';
        Assert::throws(\InvalidArgumentException::class, static function () use ($evidence, $recovery): void {
            (new NovicePromotionRecoveryResolutionPolicy())->assertMatches(
                $evidence, $recovery, '2026-09-27 01:00:00'
            );
        });
    }

    private function fixture(string $resolution): array
    {
        $recovery = [
            'UUID_RECOVERY' => 'recovery-1',
            'ROOT_UUID_ENTITLEMENT' => 'root-1',
            'SOURCE_KIND' => 'TRANSFER',
            'SOURCE_UUID' => 'transfer-3',
            'UUID_DESTINATION_OPERATION' => 'course-d',
            'AMOUNT' => '40.00',
            'STATUS' => 'PENDING_RECOVERY',
            'CREATED_AT' => '2026-09-27 00:30:00',
        ];
        $evidence = [
            'recovery_uuid' => 'recovery-1',
            'resolution_id' => 'external-resolution-1',
            'resolution' => $resolution,
            'resolved_by' => 'accounting-user',
            'evidence_ref' => 'accounting-record-1',
            'resolved_at_utc' => '2026-09-27 00:45:00',
            'root_uuid_entitlement' => 'root-1',
            'source_kind' => 'TRANSFER',
            'source_uuid' => 'transfer-3',
            'uuid_destination_operation' => 'course-d',
            'amount' => '40.00',
        ];
        return [$evidence, $recovery];
    }
}
