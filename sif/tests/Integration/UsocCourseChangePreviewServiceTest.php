<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\UsocFinancingCaseRepository;
use Prisma\Sif\Service\UsocCourseChangeFundPlanService;
use Prisma\Sif\Service\UsocCourseChangePreviewService;
use Prisma\Sif\Service\UsocCourseChangeTargetResolver;
use Prisma\Sif\Service\UsocLifecycleGuardService;
use Prisma\Sif\Service\UsocLifecyclePlanService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class UsocCourseChangePreviewServiceTest
{
    public function testBuildsTargetAndFundPlanWithoutFiscalOrEconomicEffects(): void
    {
        $db = TestDatabase::fresh();

        $student = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'REDSYS|USOC_ALUMNE|IDPAG:991|ORDER:CC991',
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
                    'source_id' => 891,
                    'idpag' => 991,
                    'ds_order' => 'CC991',
                    'visible_alumne' => 1,
                ]],
                'payment' => [
                    'idempotency_key' => 'PAYMENT|REDSYS|ORDER:CC991',
                    'movement_type' => 'CHARGE',
                    'method' => 'REDSYS',
                    'source_channel' => 'REDSYS',
                    'amount' => '75.00',
                    'movement_date' => '2026-10-02 10:00:00',
                    'provider_ref' => 'CC991',
                    'ds_order' => 'CC991',
                    'idpag' => 991,
                ],
            ])
        );

        $entity = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'INTRANET|USOC_ENTITAT|ID_INSC:891|CC',
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
                    'source_id' => 891,
                    'relation_type' => 'USOC_ENTITY',
                    'idpag' => 991,
                    'visible_alumne' => 0,
                ]],
            ])
        );

        $cases = new UsocFinancingCaseRepository(new UuidGenerator());
        $cases->recordStudentInvoice(
            $db, 891, 991, $student['uuid_factura'], '75.00', '25.00', 'CC991'
        );
        $cases->recordEntityInvoice(
            $db, 891, 991, $student['uuid_factura'], $entity['uuid_factura'], '75.00', '25.00', 'CC991'
        );

        RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' => 'TRANSFERENCIA|USOC|CC991|10',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '10.00',
            'movement_date' => '2026-10-02 10:30:00',
            'reference' => 'USOC-CC991-10',
            'allocations' => [[
                'uuid_factura' => $entity['uuid_factura'],
                'amount' => '10.00',
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ]);

        $service = $this->service($cases);
        $result = $service->preview(
            $db,
            891,
            991,
            [
                'target_standard_course_amount' => '120.00',
                'target_student_course_amount' => '90.00',
                'management_fee' => '5.00',
            ]
        );

        Assert::same(true, $result['ok']);
        Assert::same('95.00', $result['target']['target_student_total']);
        Assert::same('30.00', $result['target']['target_entity_total']);

        Assert::same('75.00', $result['fund_plan']['payers']['student']['compensate_amount']);
        Assert::same('20.00', $result['fund_plan']['payers']['student']['amount_due']);

        Assert::same('10.00', $result['fund_plan']['payers']['entity']['compensate_amount']);
        Assert::same('20.00', $result['fund_plan']['payers']['entity']['amount_due']);

        Assert::same(false, $result['can_execute']);
        Assert::same('EXECUTOR_NOT_IMPLEMENTED', $result['execution_status']);
        Assert::same(true, $result['invariants']['preview_has_no_fiscal_effect']);
        Assert::same(true, $result['invariants']['preview_has_no_economic_effect']);
        Assert::same(true, $result['invariants']['target_prices_are_server_resolved']);

        $invoiceCount = (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn();
        $paymentCount = (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn();

        Assert::same(2, $invoiceCount);
        Assert::same(2, $paymentCount);
    }

    public function testRejectsPreviewWhenNoUsocOrchestrationExists(): void
    {
        $db = TestDatabase::fresh();
        $cases = new UsocFinancingCaseRepository(new UuidGenerator());

        Assert::throws(SifException::class, function () use ($db, $cases): void {
            $this->service($cases)->preview(
                $db,
                999,
                999,
                [
                    'target_standard_course_amount' => '100.00',
                    'target_student_course_amount' => '75.00',
                    'management_fee' => '0.00',
                ]
            );
        }, 409);
    }

    private function service(UsocFinancingCaseRepository $cases): UsocCourseChangePreviewService
    {
        $guard = new UsocLifecycleGuardService($cases);

        return new UsocCourseChangePreviewService(
            new UsocLifecyclePlanService($cases, $guard),
            new UsocCourseChangeTargetResolver(),
            new UsocCourseChangeFundPlanService()
        );
    }
}
