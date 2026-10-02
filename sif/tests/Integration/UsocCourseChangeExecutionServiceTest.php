<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\OperationalEventRepository;
use Prisma\Sif\Repository\RectificationRepository;
use Prisma\Sif\Repository\UsocFinancingCaseRepository;
use Prisma\Sif\Repository\UsocLifecycleExecutionRepository;
use Prisma\Sif\Service\ManualRectificationPayloadBuilder;
use Prisma\Sif\Service\ManualRectificationService;
use Prisma\Sif\Service\UsocCourseChangeExecutionPreparationService;
use Prisma\Sif\Service\UsocCourseChangeExecutionService;
use Prisma\Sif\Service\UsocCourseChangeFundPlanService;
use Prisma\Sif\Service\UsocCourseChangeIdempotency;
use Prisma\Sif\Service\UsocCourseChangeInvoicePayloadBuilder;
use Prisma\Sif\Service\UsocCourseChangePreviewService;
use Prisma\Sif\Service\UsocCourseChangeTargetResolver;
use Prisma\Sif\Service\UsocLifecycleGuardService;
use Prisma\Sif\Service\UsocLifecyclePlanService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class UsocCourseChangeExecutionServiceTest
{
    public function testExecutesRectificationReissueAndPerPayerCompensationIdempotently(): void
    {
        [$db, $cases, $student, $entity] = $this->sourceCase();
        $requestId = 'uc013-course-change-891-exec';
        $actor = 'secretaria-test';
        $target = $this->target('120.00', '90.00', '5.00');

        $this->preparation($cases)->prepare(
            $db, 891, 991, $requestId, $actor, ['ADMIN'], $target
        );

        $service = $this->executor($db, $cases);
        $input = [
            'effective_at' => '2026-10-02 18:00:00',
            'entity_billing' => $this->entityBilling(),
        ];

        $first = $service->execute(
            $db, 891, 991, $requestId, $actor, ['ADMIN'], 892, $input
        );
        $retry = $service->execute(
            $db, 891, 991, $requestId, $actor, ['ADMIN'], 892, $input
        );

        Assert::same(true, $first['ok']);
        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $retry['idempotency_reused']);
        Assert::same(892, $first['target_id_insc']);
        Assert::same('75.00', $first['student']['economic']['compensate_amount']);
        Assert::same('20.00', $first['student']['economic']['amount_due']);
        Assert::same('10.00', $first['entity']['economic']['compensate_amount']);
        Assert::same('20.00', $first['entity']['economic']['amount_due']);
        Assert::same(false, $first['requires_follow_up']);
        Assert::same('PARTIAL', $first['student']['payment_status']);
        Assert::same('PARTIAL', $first['entity']['payment_status']);
        Assert::same('COURSE_CHANGE_PENDING_COLLECTION', $first['case_status']);

        Assert::same(6, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM factura_rectificacio')->fetchColumn());
        Assert::same(4, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(2, (int) $db->query(
            "SELECT COUNT(*) FROM payment_transaction WHERE TIPUS_MOVIMENT = 'COMPENSATION'"
        )->fetchColumn());
        Assert::same(2, (int) $db->query(
            "SELECT COUNT(*) FROM enrollment_fund_movement WHERE MOVEMENT_TYPE = 'COMPENSATION_ALLOCATION'"
        )->fetchColumn());
        Assert::same(2, (int) $db->query(
            "SELECT COUNT(*) FROM operational_event WHERE OPERATION_TYPE LIKE 'USOC_COURSE_CHANGE_%'"
        )->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM usoc_financing_case')->fetchColumn());

        Assert::same('RECTIFIED', (string) $db->query(
            'SELECT ESTAT_FACTURA FROM factura WHERE UUID_FACTURA = ' . $db->quote($student['uuid_factura'])
        )->fetchColumn());
        Assert::same('RECTIFIED', (string) $db->query(
            'SELECT ESTAT_FACTURA FROM factura WHERE UUID_FACTURA = ' . $db->quote($entity['uuid_factura'])
        )->fetchColumn());
        Assert::same('COMPLETED', (string) $db->query(
            'SELECT STATE FROM usoc_lifecycle_execution WHERE REQUEST_ID = ' . $db->quote($requestId)
        )->fetchColumn());

        $targetCase = $cases->findByInscriptionAndIdpag($db, 892, 991);
        Assert::same('COURSE_CHANGE_PENDING_COLLECTION', $targetCase['STATUS']);
        Assert::same($first['student']['target_invoice_uuid'], $targetCase['UUID_STUDENT_INVOICE']);
        Assert::same($first['entity']['target_invoice_uuid'], $targetCase['UUID_ENTITY_INVOICE']);
    }

    public function testLowerTargetKeepsExcessExplicitWithoutFabricatingRefundOrCredit(): void
    {
        [$db, $cases] = $this->sourceCase();
        $requestId = 'uc013-course-change-891-lower';
        $target = $this->target('80.00', '60.00', '0.00');

        $this->preparation($cases)->prepare(
            $db, 891, 991, $requestId, 'secretaria-test', ['ADMIN'], $target
        );

        $result = $this->executor($db, $cases)->execute(
            $db,
            891,
            991,
            $requestId,
            'secretaria-test',
            ['ADMIN'],
            893,
            [
                'effective_at' => '2026-10-02 18:10:00',
                'entity_billing' => $this->entityBilling(),
            ]
        );

        Assert::same(true, $result['requires_follow_up']);
        Assert::same('15.00', $result['student']['economic']['excess_amount']);
        Assert::same('PENDING_EXPLICIT_RESOLUTION', $result['student']['economic']['excess_resolution']);
        Assert::same(0, (int) $db->query(
            "SELECT COUNT(*) FROM payment_transaction WHERE TIPUS_MOVIMENT = 'REFUND'"
        )->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM credit_balance')->fetchColumn());
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM operational_event WHERE STATUS = 'COMPLETED_WITH_PENDING'"
        )->fetchColumn());
    }

    public function testRejectsDifferentTargetOnCompletedRetry(): void
    {
        [$db, $cases] = $this->sourceCase();
        $requestId = 'uc013-course-change-891-target-bind';
        $target = $this->target('100.00', '75.00', '0.00');
        $this->preparation($cases)->prepare(
            $db, 891, 991, $requestId, 'secretaria-test', ['ADMIN'], $target
        );
        $service = $this->executor($db, $cases);
        $input = [
            'effective_at' => '2026-10-02 18:20:00',
            'entity_billing' => $this->entityBilling(),
        ];
        $service->execute(
            $db, 891, 991, $requestId, 'secretaria-test', ['ADMIN'], 894, $input
        );

        Assert::throws(SifException::class, static function () use ($service, $db, $requestId, $input): void {
            $service->execute(
                $db, 891, 991, $requestId, 'secretaria-test', ['ADMIN'], 895, $input
            );
        }, 409);
    }

    private function sourceCase(): array
    {
        $db = TestDatabase::fresh();

        $student = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'REDSYS|USOC_ALUMNE|IDPAG:991|ORDER:CC991EXEC',
                'source_channel' => 'REDSYS',
                'totals' => [
                    'import_base' => '100.00',
                    'discount' => '25.00',
                    'taxable_base' => '75.00',
                    'total' => '75.00',
                ],
                'lines' => [[
                    'unit_price' => '100.00',
                    'base' => '100.00',
                    'import_base' => '100.00',
                    'discount_amount' => '25.00',
                    'taxable_base' => '75.00',
                    'total' => '75.00',
                ]],
                'relations' => [[
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => 891,
                    'idpag' => 991,
                    'ds_order' => 'CC991EXEC',
                    'visible_alumne' => 1,
                ]],
                'payment' => [
                    'idempotency_key' => 'PAYMENT|REDSYS|ORDER:CC991EXEC',
                    'movement_type' => 'CHARGE',
                    'method' => 'REDSYS',
                    'source_channel' => 'REDSYS',
                    'amount' => '75.00',
                    'movement_date' => '2026-10-02 10:00:00',
                    'provider_ref' => 'CC991EXEC',
                    'ds_order' => 'CC991EXEC',
                    'idpag' => 991,
                ],
            ])
        );

        $entity = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'INTRANET|USOC_ENTITAT|ID_INSC:891|CCEXEC',
                'source_channel' => 'INTRANET',
                'emesa_abans_cobrament' => 1,
                'billing' => $this->entityBilling(),
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
            $db, 891, 991, $student['uuid_factura'], '75.00', '25.00', 'CC991EXEC'
        );
        $cases->recordEntityInvoice(
            $db, 891, 991, $student['uuid_factura'], $entity['uuid_factura'], '75.00', '25.00', 'CC991EXEC'
        );

        RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' => 'TRANSFERENCIA|USOC|CC991EXEC|10',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '10.00',
            'movement_date' => '2026-10-02 10:30:00',
            'reference' => 'USOC-CC991EXEC-10',
            'allocations' => [[
                'uuid_factura' => $entity['uuid_factura'],
                'amount' => '10.00',
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ]);

        return [$db, $cases, $student, $entity];
    }

    private function preparation(UsocFinancingCaseRepository $cases): UsocCourseChangeExecutionPreparationService
    {
        $preview = new UsocCourseChangePreviewService(
            new UsocLifecyclePlanService($cases, new UsocLifecycleGuardService($cases)),
            new UsocCourseChangeTargetResolver(),
            new UsocCourseChangeFundPlanService()
        );

        return new UsocCourseChangeExecutionPreparationService(
            $preview,
            new UsocLifecycleExecutionRepository(new UuidGenerator())
        );
    }

    private function executor(\PDO $db, UsocFinancingCaseRepository $cases): UsocCourseChangeExecutionService
    {
        $keys = new UsocCourseChangeIdempotency();

        return new UsocCourseChangeExecutionService(
            new UsocLifecycleExecutionRepository(new UuidGenerator()),
            new ManualPaymentInvoiceRepository(),
            new ManualRectificationService(
                new ManualPaymentInvoiceRepository(),
                new RectificationRepository(),
                new ManualRectificationPayloadBuilder(),
                IssueInvoiceTest::serviceFor($db)
            ),
            new UsocCourseChangeInvoicePayloadBuilder($keys),
            IssueInvoiceTest::serviceFor($db),
            RegisterPaymentTest::paymentServiceFor($db),
            new EnrollmentFundMovementRepository(new UuidGenerator()),
            $cases,
            new OperationalEventRepository(new UuidGenerator()),
            $keys
        );
    }

    private function target(string $standard, string $student, string $fee): array
    {
        return [
            'year' => '2026',
            'month' => '11',
            'course' => 'DEST',
            'kind' => 'COURSE',
            'title' => 'Curs destí USOC',
            'price_id' => 200,
            'target_standard_course_amount' => $standard,
            'target_student_course_amount' => $student,
            'management_fee' => $fee,
        ];
    }

    private function entityBilling(): array
    {
        return [
            'name' => 'Entitat USOC Test',
            'nif' => 'G12345678',
            'address' => 'Carrer Entitat 1',
            'cp' => '08001',
            'city' => 'Barcelona',
            'province' => 'Barcelona',
            'country' => 'ES',
            'email' => 'usoc@example.test',
        ];
    }
}
