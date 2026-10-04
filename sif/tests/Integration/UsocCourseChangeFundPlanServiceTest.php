<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\UsocCourseChangeFundPlanService;
use Prisma\Sif\Tests\Support\Assert;

final class UsocCourseChangeFundPlanServiceTest
{
    public function testSeparatesStudentAndEntityCompensation(): void
    {
        $result = (new UsocCourseChangeFundPlanService())->plan(
            $this->lifecyclePlan('75.00', '10.00'),
            [
                'target_student_total' => '85.00',
                'target_entity_total' => '20.00',
            ]
        );

        Assert::same('75.00', $result['payers']['student']['compensate_amount']);
        Assert::same('10.00', $result['payers']['student']['amount_due']);
        Assert::same('0.00', $result['payers']['student']['excess_amount']);
        Assert::same('PARTIALLY_COMPENSATED', $result['payers']['student']['resolution']);

        Assert::same('10.00', $result['payers']['entity']['compensate_amount']);
        Assert::same('10.00', $result['payers']['entity']['amount_due']);
        Assert::same('0.00', $result['payers']['entity']['excess_amount']);
        Assert::same('PARTIALLY_COMPENSATED', $result['payers']['entity']['resolution']);

        Assert::same('85.00', $result['totals']['compensate_amount']);
        Assert::same('20.00', $result['totals']['amount_due']);
        Assert::same(true, $result['invariants']['never_cross_payer_funds']);
    }

    public function testKeepsExcessWithSamePayerForExplicitResolution(): void
    {
        $result = (new UsocCourseChangeFundPlanService())->plan(
            $this->lifecyclePlan('100.00', '25.00'),
            [
                'target_student_total' => '80.00',
                'target_entity_total' => '20.00',
            ]
        );

        Assert::same('80.00', $result['payers']['student']['compensate_amount']);
        Assert::same('20.00', $result['payers']['student']['excess_amount']);
        Assert::same('COMPENSATED_WITH_EXCESS', $result['payers']['student']['resolution']);

        Assert::same('20.00', $result['payers']['entity']['compensate_amount']);
        Assert::same('5.00', $result['payers']['entity']['excess_amount']);
        Assert::same('COMPENSATED_WITH_EXCESS', $result['payers']['entity']['resolution']);

        Assert::same('25.00', $result['totals']['excess_amount']);
        Assert::same(true, $result['payers']['student']['excess_requires_decision']);
        Assert::same(true, $result['payers']['entity']['excess_requires_decision']);
    }

    public function testNoEntityInvoiceMeansNoEntityFundsAndAmountDueIfTargetHasEntityPart(): void
    {
        $plan = $this->lifecyclePlan('75.00', '0.00');
        $plan['actions'][1] = [
            'payer_role' => 'entity',
            'invoice_uuid' => null,
            'invoice_action' => 'NONE',
            'economic_action' => 'NONE',
            'max_refundable' => '0.00',
            'reason' => 'INVOICE_NOT_ISSUED',
        ];

        $result = (new UsocCourseChangeFundPlanService())->plan(
            $plan,
            [
                'target_student_total' => '75.00',
                'target_entity_total' => '25.00',
            ]
        );

        Assert::same('0.00', $result['payers']['entity']['source_net_paid']);
        Assert::same('0.00', $result['payers']['entity']['compensate_amount']);
        Assert::same('25.00', $result['payers']['entity']['amount_due']);
        Assert::same('AMOUNT_DUE', $result['payers']['entity']['resolution']);
    }

    public function testRejectsNonCourseChangePlan(): void
    {
        $plan = $this->lifecyclePlan('75.00', '25.00');
        $plan['operation'] = 'cancellation';

        Assert::throws(SifException::class, static function () use ($plan): void {
            (new UsocCourseChangeFundPlanService())->plan(
                $plan,
                [
                    'target_student_total' => '75.00',
                    'target_entity_total' => '25.00',
                ]
            );
        }, 409);
    }

    private function lifecyclePlan(string $studentPaid, string $entityPaid): array
    {
        return [
            'ok' => true,
            'requires_usoc_orchestration' => true,
            'operation' => 'course_change',
            'id_insc' => 880,
            'idpag' => 980,
            'actions' => [
                [
                    'payer_role' => 'student',
                    'invoice_uuid' => 'student-invoice',
                    'invoice_action' => 'RECTIFY_BEFORE_REISSUE',
                    'economic_action' => 'RESOLVE_REAL_FUNDS',
                    'invoice_total' => '75.00',
                    'net_paid' => $studentPaid,
                    'max_refundable' => $studentPaid,
                ],
                [
                    'payer_role' => 'entity',
                    'invoice_uuid' => 'entity-invoice',
                    'invoice_action' => 'RECTIFY_BEFORE_REISSUE',
                    'economic_action' => 'RESOLVE_REAL_FUNDS',
                    'invoice_total' => '25.00',
                    'net_paid' => $entityPaid,
                    'max_refundable' => $entityPaid,
                ],
            ],
        ];
    }
}
