<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Domain\NovicePromotionLineagePolicy;
use Prisma\Sif\Domain\NovicePromotionLineageProjector;
use Prisma\Sif\Tests\Support\Assert;

/**
 * Pure SQL-row -> graph projection examples. No MySQL.
 */
final class NovicePromotionLineageProjectorTest
{
    public function testDirectAppliedOriginalBecomesActiveGraphExposure(): void
    {
        $graph = $this->projector()->project(
            $this->root('90.00', '20.00'),
            [$this->original('app-1', '70.00', 'APPLIED')],
            [],
            [],
            []
        );
        Assert::same('ACTIVE', $graph['applications'][0]['status']);

        $plan = (new NovicePromotionLineagePolicy())->planOriginalRefund(
            $graph['root_right_id'], $graph['rights'], $graph['applications']
        );
        Assert::same('20.00', $plan['total_cancel_available']);
        Assert::same('70.00', $plan['total_recover_active']);
    }

    public function testFirstConfirmedTransferReplacesHistoricalOriginalWithoutDoubleExposure(): void
    {
        $original = $this->original('app-1', '70.00', 'REVERSED');
        $original['REASON_CODE'] = 'TRANSFERRED_TO_COURSE';

        $graph = $this->projector()->project(
            $this->root('90.00', '20.00'),
            [$original],
            [],
            [],
            [$this->transfer('transfer-1', '70.00', 'CONFIRMED', 'app-1')]
        );

        $nodes = $this->applicationsById($graph);
        Assert::same('REPLACED_BY_TRANSFER', $nodes['app-1']['status']);
        Assert::same('transfer:transfer-1', $nodes['app-1']['successor_application_id']);
        Assert::same('ACTIVE', $nodes['transfer:transfer-1']['status']);

        $plan = (new NovicePromotionLineagePolicy())->planOriginalRefund(
            $graph['root_right_id'], $graph['rights'], $graph['applications']
        );
        Assert::same('20.00', $plan['total_cancel_available']);
        Assert::same('70.00', $plan['total_recover_active']);
        Assert::same('transfer:transfer-1', $plan['recover_active_applications'][0]['application_id']);
    }

    public function testTransferredCourseConvertedToDerivedRightOnlyExposesDerivedDescendant(): void
    {
        $original = $this->original('app-1', '90.00', 'REVERSED');
        $original['REASON_CODE'] = 'TRANSFERRED_TO_COURSE';

        $transfer = $this->transfer('transfer-1', '90.00', 'CANCELLED', 'app-1');
        $transfer['CLOSE_REASON'] = 'CONVERTED_TO_DERIVED';

        $derived = $this->derivedRight('derived-1', '90.00', '50.00');
        $derived['SOURCE_UUID_TRANSFER'] = 'transfer-1';
        $derived['SOURCE_UUID_APPLICATION'] = null;

        $derivedApp = $this->derivedApplication('dapp-1', 'derived-1', '40.00', 'APPLIED');

        $graph = $this->projector()->project(
            $this->root('90.00', '0.00'),
            [$original],
            [$derived],
            [$derivedApp],
            [$transfer]
        );

        $nodes = $this->applicationsById($graph);
        Assert::same('REPLACED_BY_DERIVED', $nodes['transfer:transfer-1']['status']);
        Assert::same('derived-1', $nodes['transfer:transfer-1']['derived_right_id']);
        Assert::same('ACTIVE', $nodes['dapp-1']['status']);

        $plan = (new NovicePromotionLineagePolicy())->planOriginalRefund(
            $graph['root_right_id'], $graph['rights'], $graph['applications']
        );
        Assert::same('50.00', $plan['total_cancel_available']);
        Assert::same('40.00', $plan['total_recover_active']);
        Assert::same('dapp-1', $plan['recover_active_applications'][0]['application_id']);
    }

    public function testTwoConfirmedTransfersKeepOnlyLastDestinationActive(): void
    {
        $original = $this->original('app-1', '70.00', 'REVERSED');
        $original['REASON_CODE'] = 'TRANSFERRED_TO_COURSE';

        $first = $this->transfer('transfer-1', '70.00', 'CANCELLED', 'app-1');
        $first['CLOSE_REASON'] = 'TRANSFERRED_TO_COURSE';

        $second = $this->transfer('transfer-2', '70.00', 'CONFIRMED', null);
        $second['PREVIOUS_UUID_TRANSFER'] = 'transfer-1';

        $graph = $this->projector()->project(
            $this->root('90.00', '20.00'),
            [$original],
            [],
            [],
            [$first, $second]
        );

        $nodes = $this->applicationsById($graph);
        Assert::same('REPLACED_BY_TRANSFER', $nodes['transfer:transfer-1']['status']);
        Assert::same('transfer:transfer-2', $nodes['transfer:transfer-1']['successor_application_id']);
        Assert::same('ACTIVE', $nodes['transfer:transfer-2']['status']);

        $plan = (new NovicePromotionLineagePolicy())->planOriginalRefund(
            $graph['root_right_id'], $graph['rights'], $graph['applications']
        );
        Assert::same('20.00', $plan['total_cancel_available']);
        Assert::same('70.00', $plan['total_recover_active']);
    }

    public function testDerivedApplicationTransferredKeepsSameDerivedRightBudget(): void
    {
        $original = $this->original('app-1', '90.00', 'REVERSED');
        $original['REASON_CODE'] = 'CONVERTED_TO_DERIVED';

        $derived = $this->derivedRight('derived-1', '90.00', '50.00');
        $derived['SOURCE_UUID_APPLICATION'] = 'app-1';

        $dapp = $this->derivedApplication('dapp-1', 'derived-1', '40.00', 'TRANSFERRED');
        $transfer = $this->transfer('transfer-d1', '40.00', 'CONFIRMED', null);
        $transfer['UUID_DERIVED_APPLICATION'] = 'dapp-1';

        $graph = $this->projector()->project(
            $this->root('90.00', '0.00'),
            [$original],
            [$derived],
            [$dapp],
            [$transfer]
        );

        $nodes = $this->applicationsById($graph);
        Assert::same('REPLACED_BY_TRANSFER', $nodes['dapp-1']['status']);
        Assert::same('ACTIVE', $nodes['transfer:transfer-d1']['status']);
        Assert::same('derived-1', $nodes['transfer:transfer-d1']['right_id']);

        $plan = (new NovicePromotionLineagePolicy())->planOriginalRefund(
            $graph['root_right_id'], $graph['rights'], $graph['applications']
        );
        Assert::same('50.00', $plan['total_cancel_available']);
        Assert::same('40.00', $plan['total_recover_active']);
    }

    public function testPendingTransferBlocksRefundProjection(): void
    {
        $original = $this->original('app-1', '70.00', 'REVERSED');
        $original['REASON_CODE'] = 'TRANSFERRED_TO_COURSE';

        Assert::throws(\InvalidArgumentException::class, function () use ($original): void {
            $this->projector()->project(
                $this->root('90.00', '20.00'),
                [$original],
                [],
                [],
                [$this->transfer('transfer-1', '70.00', 'PENDING_FISCAL_REVIEW', 'app-1')]
            );
        });
    }

    public function testTransferBranchingFailsClosed(): void
    {
        $original = $this->original('app-1', '70.00', 'REVERSED');
        $original['REASON_CODE'] = 'TRANSFERRED_TO_COURSE';

        $first = $this->transfer('transfer-1', '70.00', 'CANCELLED', 'app-1');
        $first['CLOSE_REASON'] = 'TRANSFERRED_TO_COURSE';
        $second = $this->transfer('transfer-2', '70.00', 'CONFIRMED', null);
        $second['PREVIOUS_UUID_TRANSFER'] = 'transfer-1';
        $third = $this->transfer('transfer-3', '70.00', 'CONFIRMED', null);
        $third['PREVIOUS_UUID_TRANSFER'] = 'transfer-1';

        Assert::throws(\InvalidArgumentException::class, function () use (
            $original, $first, $second, $third
        ): void {
            $this->projector()->project(
                $this->root('90.00', '20.00'),
                [$original],
                [],
                [],
                [$first, $second, $third]
            );
        });
    }

    private function projector(): NovicePromotionLineageProjector
    {
        return new NovicePromotionLineageProjector();
    }

    private function root(string $issued, string $available): array
    {
        return [
            'UUID_ENTITLEMENT' => 'root-jasom',
            'ORIGINAL_CASH_AMOUNT' => $issued,
            'AVAILABLE_AMOUNT' => $available,
            'STATUS' => 'ACTIVE',
        ];
    }

    private function original(string $id, string $amount, string $status): array
    {
        return [
            'UUID_APPLICATION' => $id,
            'UUID_ENTITLEMENT' => 'root-jasom',
            'AMOUNT' => $amount,
            'STATUS' => $status,
            'REASON_CODE' => null,
        ];
    }

    private function derivedRight(string $id, string $issued, string $available): array
    {
        return [
            'UUID_DERIVED_BALANCE' => $id,
            'ROOT_UUID_ENTITLEMENT' => 'root-jasom',
            'SOURCE_UUID_APPLICATION' => null,
            'SOURCE_UUID_DERIVED_APPLICATION' => null,
            'SOURCE_UUID_TRANSFER' => null,
            'PROMOTIONAL_ORIGIN_AMOUNT' => $issued,
            'AVAILABLE_PROMOTIONAL_AMOUNT' => $available,
            'STATUS' => 'ACTIVE',
            'POLICY_SNAPSHOT_JSON' => json_encode([
                'review_decision' => ['promotional_forfeited_amount' => '0.00'],
            ], JSON_THROW_ON_ERROR),
        ];
    }

    private function derivedApplication(
        string $id,
        string $right,
        string $amount,
        string $status
    ): array {
        return [
            'UUID_DERIVED_APPLICATION' => $id,
            'UUID_DERIVED_BALANCE' => $right,
            'ROOT_UUID_ENTITLEMENT' => 'root-jasom',
            'AMOUNT' => $amount,
            'STATUS' => $status,
            'REASON_CODE' => null,
        ];
    }

    private function transfer(
        string $id,
        string $amount,
        string $status,
        ?string $sourceOriginal
    ): array {
        return [
            'UUID_TRANSFER' => $id,
            'ROOT_UUID_ENTITLEMENT' => 'root-jasom',
            'UUID_ORIGINAL_APPLICATION' => $sourceOriginal,
            'UUID_DERIVED_APPLICATION' => null,
            'PREVIOUS_UUID_TRANSFER' => null,
            'AMOUNT' => $amount,
            'STATUS' => $status,
            'CLOSE_REASON' => null,
        ];
    }

    private function applicationsById(array $graph): array
    {
        $result = [];
        foreach ($graph['applications'] as $application) {
            $result[$application['id']] = $application;
        }
        return $result;
    }
}
