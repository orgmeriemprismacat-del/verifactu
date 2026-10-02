<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\UsocFinancingCaseRepository;
use Prisma\Sif\Repository\UsocLifecycleExecutionRepository;
use Prisma\Sif\Service\UsocCourseChangeExecutionPreparationService;
use Prisma\Sif\Service\UsocCourseChangeFundPlanService;
use Prisma\Sif\Service\UsocCourseChangePreviewService;
use Prisma\Sif\Service\UsocCourseChangeTargetResolver;
use Prisma\Sif\Service\UsocLifecycleGuardService;
use Prisma\Sif\Service\UsocLifecyclePlanService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class UsocCourseChangeExecutionPreparationServiceTest
{
    public function testPersistsRequestedCheckpointWithoutApplyingEffects(): void
    {
        $db = TestDatabase::fresh();
        $cases = $this->seedCase($db, 892, 992);

        $service = $this->service($cases);
        $first = $service->prepare(
            $db,
            892,
            992,
            'uc013-course-change-892-1',
            'operator-1',
            ['GESTIO'],
            $this->target()
        );

        Assert::same(true, $first['ok']);
        Assert::same('REQUESTED', $first['state']);
        Assert::same('course_change', $first['operation']);
        Assert::same(false, $first['effects_applied']);
        Assert::same(false, $first['idempotency_reused']);

        $row = $db->query(
            "SELECT OPERATION, STATE, REQUEST_JSON, PLAN_JSON
             FROM usoc_lifecycle_execution
             WHERE REQUEST_ID = 'uc013-course-change-892-1'"
        )->fetch(\PDO::FETCH_ASSOC);

        Assert::same('COURSE_CHANGE', $row['OPERATION']);
        Assert::same('REQUESTED', $row['STATE']);

        $request = json_decode((string) $row['REQUEST_JSON'], true);
        $plan = json_decode((string) $row['PLAN_JSON'], true);

        Assert::same('120.00', $request['target']['target_standard_course_amount']);
        Assert::same('90.00', $request['target']['target_student_course_amount']);
        Assert::same(false, $plan['can_execute']);
        Assert::same('EXECUTOR_NOT_IMPLEMENTED', $plan['execution_status']);

        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
    }

    public function testRetryReusesSameRequestedCheckpoint(): void
    {
        $db = TestDatabase::fresh();
        $cases = $this->seedCase($db, 893, 993);
        $service = $this->service($cases);

        $first = $service->prepare(
            $db,
            893,
            993,
            'uc013-course-change-893-1',
            'operator-1',
            ['GESTIO'],
            $this->target()
        );
        $second = $service->prepare(
            $db,
            893,
            993,
            'uc013-course-change-893-1',
            'operator-1',
            ['GESTIO'],
            $this->target()
        );

        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_execution'], $second['uuid_execution']);
        Assert::same(
            1,
            (int) $db->query(
                "SELECT COUNT(*) FROM usoc_lifecycle_execution
                 WHERE REQUEST_ID = 'uc013-course-change-893-1'"
            )->fetchColumn()
        );
    }

    public function testSameRequestIdWithDifferentTargetConflicts(): void
    {
        $db = TestDatabase::fresh();
        $cases = $this->seedCase($db, 894, 994);
        $service = $this->service($cases);

        $service->prepare(
            $db,
            894,
            994,
            'uc013-course-change-894-1',
            'operator-1',
            ['GESTIO'],
            $this->target()
        );

        $changed = $this->target();
        $changed['target_student_course_amount'] = '89.00';

        Assert::throws(SifException::class, static function () use (
            $db,
            $service,
            $changed
        ): void {
            $service->prepare(
                $db,
                894,
                994,
                'uc013-course-change-894-1',
                'operator-1',
                ['GESTIO'],
                $changed
            );
        }, 409);
    }

    private function service(
        UsocFinancingCaseRepository $cases
    ): UsocCourseChangeExecutionPreparationService {
        $guard = new UsocLifecycleGuardService($cases);
        $preview = new UsocCourseChangePreviewService(
            new UsocLifecyclePlanService($cases, $guard),
            new UsocCourseChangeTargetResolver(),
            new UsocCourseChangeFundPlanService()
        );

        return new UsocCourseChangeExecutionPreparationService(
            $preview,
            new UsocLifecycleExecutionRepository(new UuidGenerator())
        );
    }

    private function seedCase(
        \PDO $db,
        int $idInsc,
        int $idpag
    ): UsocFinancingCaseRepository {
        $student = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'TEST|UC013|CC|STUDENT|' . $idInsc,
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
                    'source_id' => $idInsc,
                    'idpag' => $idpag,
                    'visible_alumne' => 1,
                ]],
            ])
        );

        $entity = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'TEST|UC013|CC|ENTITY|' . $idInsc,
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
                    'source_id' => $idInsc,
                    'relation_type' => 'USOC_ENTITY',
                    'idpag' => $idpag,
                    'visible_alumne' => 0,
                ]],
            ])
        );

        $cases = new UsocFinancingCaseRepository(new UuidGenerator());
        $cases->recordStudentInvoice(
            $db,
            $idInsc,
            $idpag,
            $student['uuid_factura'],
            '75.00',
            '25.00',
            'CC-PREP-' . $idInsc
        );
        $cases->recordEntityInvoice(
            $db,
            $idInsc,
            $idpag,
            $student['uuid_factura'],
            $entity['uuid_factura'],
            '75.00',
            '25.00',
            'CC-PREP-' . $idInsc
        );

        return $cases;
    }

    private function target(): array
    {
        return [
            'year' => '2027',
            'month' => '01',
            'course' => 'CURS-B',
            'kind' => 'COURSE',
            'title' => 'Curs B',
            'price_id' => 2,
            'target_standard_course_amount' => '120.00',
            'target_student_course_amount' => '90.00',
            'management_fee' => '5.00',
        ];
    }
}
