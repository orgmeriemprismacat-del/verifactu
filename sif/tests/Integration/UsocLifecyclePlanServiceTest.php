<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\UsocFinancingCaseRepository;
use Prisma\Sif\Service\UsocLifecycleGuardService;
use Prisma\Sif\Service\UsocLifecyclePlanService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class UsocLifecyclePlanServiceTest
{
    public function testCancellationPlanSeparatesPayersAndCapsRefundByRealFunds(): void
    {
        $db = TestDatabase::fresh();

        $student = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'REDSYS|USOC_ALUMNE|IDPAG:980|ORDER:PLAN980',
                'source_channel' => 'REDSYS',
                'totals' => [
                    'import_base' => '75.00',
                    'taxable_base' => '75.00',
                    'total' => '75.00',
                ],
                'lines' => [[
                    'unit_price' => '75.00',
                    'base' => '75.00',
                    'import_base' => '75.00',
                    'taxable_base' => '75.00',
                    'total' => '75.00',
                ]],
                'relations' => [[
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => 880,
                    'idpag' => 980,
                    'ds_order' => 'PLAN980',
                    'visible_alumne' => 1,
                ]],
                'payment' => [
                    'idempotency_key' => 'PAYMENT|REDSYS|ORDER:PLAN980',
                    'movement_type' => 'CHARGE',
                    'method' => 'REDSYS',
                    'source_channel' => 'REDSYS',
                    'amount' => '75.00',
                    'movement_date' => '2026-09-30 10:00:00',
                    'provider_ref' => 'PLAN980',
                    'ds_order' => 'PLAN980',
                    'idpag' => 980,
                ],
            ])
        );

        $entity = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'INTRANET|USOC_ENTITAT|ID_INSC:880|PLAN',
                'source_channel' => 'INTRANET',
                'emesa_abans_cobrament' => 1,
                'totals' => [
                    'import_base' => '25.00',
                    'taxable_base' => '25.00',
                    'total' => '25.00',
                ],
                'lines' => [[
                    'unit_price' => '25.00',
                    'base' => '25.00',
                    'import_base' => '25.00',
                    'taxable_base' => '25.00',
                    'total' => '25.00',
                ]],
                'relations' => [[
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => 880,
                    'relation_type' => 'USOC_ENTITY',
                    'idpag' => 980,
                    'visible_alumne' => 0,
                ]],
            ])
        );

        $cases = new UsocFinancingCaseRepository(new UuidGenerator());
        $cases->recordStudentInvoice(
            $db, 880, 980, $student['uuid_factura'], '75.00', '25.00', 'PLAN980'
        );
        $cases->recordEntityInvoice(
            $db, 880, 980, $student['uuid_factura'], $entity['uuid_factura'], '75.00', '25.00', 'PLAN980'
        );

        RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' => 'TRANSFERENCIA|USOC|PLAN|10',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '10.00',
            'movement_date' => '2026-09-30 11:00:00',
            'reference' => 'USOC-PLAN-10',
            'allocations' => [[
                'uuid_factura' => $entity['uuid_factura'],
                'amount' => '10.00',
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ]);

        $service = new UsocLifecyclePlanService(
            $cases,
            new UsocLifecycleGuardService($cases)
        );
        $plan = $service->plan($db, 880, 980, 'cancellation');

        Assert::same(true, $plan['requires_usoc_orchestration']);
        Assert::same(true, $plan['invariants']['never_cross_payer_funds']);
        Assert::same(true, $plan['invariants']['never_refund_uncollected_amounts']);
        Assert::same(2, count($plan['actions']));

        $studentAction = $plan['actions'][0];
        $entityAction = $plan['actions'][1];

        Assert::same('student', $studentAction['payer_role']);
        Assert::same('75.00', $studentAction['net_paid']);
        Assert::same('75.00', $studentAction['max_refundable']);
        Assert::same('RESOLVE_REAL_FUNDS', $studentAction['economic_action']);

        Assert::same('entity', $entityAction['payer_role']);
        Assert::same('10.00', $entityAction['net_paid']);
        Assert::same('10.00', $entityAction['max_refundable']);
        Assert::same('RESOLVE_REAL_FUNDS', $entityAction['economic_action']);
    }

    public function testCourseChangePlanNeverRefundsEntityWhenEntityInvoiceNotIssued(): void
    {
        $db = TestDatabase::fresh();
        $student = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'REDSYS|USOC_ALUMNE|IDPAG:981|ORDER:PLAN981',
                'source_channel' => 'REDSYS',
                'totals' => [
                    'import_base' => '75.00',
                    'taxable_base' => '75.00',
                    'total' => '75.00',
                ],
                'lines' => [[
                    'unit_price' => '75.00',
                    'base' => '75.00',
                    'import_base' => '75.00',
                    'taxable_base' => '75.00',
                    'total' => '75.00',
                ]],
                'relations' => [[
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => 881,
                    'idpag' => 981,
                    'ds_order' => 'PLAN981',
                    'visible_alumne' => 1,
                ]],
            ])
        );

        $cases = new UsocFinancingCaseRepository(new UuidGenerator());
        $cases->recordStudentInvoice(
            $db, 881, 981, $student['uuid_factura'], '75.00', '25.00', 'PLAN981'
        );

        $service = new UsocLifecyclePlanService(
            $cases,
            new UsocLifecycleGuardService($cases)
        );
        $plan = $service->plan($db, 881, 981, 'course_change');
        $entityAction = $plan['actions'][1];

        Assert::same('entity', $entityAction['payer_role']);
        Assert::same(null, $entityAction['invoice_uuid']);
        Assert::same('NONE', $entityAction['invoice_action']);
        Assert::same('NONE', $entityAction['economic_action']);
        Assert::same('0.00', $entityAction['max_refundable']);
        Assert::same('INVOICE_NOT_ISSUED', $entityAction['reason']);
    }
}
