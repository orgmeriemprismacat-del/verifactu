<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\UsocFinancingCaseRepository;
use Prisma\Sif\Repository\UsocLifecycleExecutionRepository;
use Prisma\Sif\Service\UsocCourseChangeDestinationBindingService;
use Prisma\Sif\Service\UsocCourseChangeExecutionPreparationService;
use Prisma\Sif\Service\UsocCourseChangeFundPlanService;
use Prisma\Sif\Service\UsocCourseChangePreviewService;
use Prisma\Sif\Service\UsocCourseChangeTargetResolver;
use Prisma\Sif\Service\UsocLifecycleGuardService;
use Prisma\Sif\Service\UsocLifecyclePlanService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class UsocCourseChangeDestinationBindingServiceTest
{
    public function testBindsReservedDestinationToRequestedCheckpoint(): void
    {
        $db = TestDatabase::fresh();
        $cases = $this->seedCase($db, 895, 995);
        $repo = new UsocLifecycleExecutionRepository(new UuidGenerator());

        $this->preparation($cases, $repo)->prepare(
            $db,
            895,
            995,
            'uc013-bind-895-1',
            'operator-1',
            ['GESTIO'],
            $this->target()
        );

        $service = new UsocCourseChangeDestinationBindingService($repo);
        $result = $service->bind(
            $db,
            'uc013-bind-895-1',
            895,
            995,
            1895,
            1995,
            'SIF-USOC-CC:' . str_repeat('a', 32),
            '95.00'
        );

        Assert::same(true, $result['ok']);
        Assert::same('REQUESTED', $result['state']);
        Assert::same('course_change', $result['operation']);
        Assert::same(1895, $result['destination']['destination_id_insc']);
        Assert::same(995, $result['destination']['source_idpag']);
        Assert::same(1995, $result['destination']['destination_idpag']);
        Assert::same('DESTINATION_RESERVED', $result['destination']['phase']);
        Assert::same(false, $result['destination']['effects_applied']);
        Assert::same(false, $result['destination']['source_closed']);
        Assert::same(false, $result['idempotency_reused']);

        $row = $db->query(
            "SELECT STATE, RESULT_JSON
             FROM usoc_lifecycle_execution
             WHERE REQUEST_ID = 'uc013-bind-895-1'"
        )->fetch(\PDO::FETCH_ASSOC);
        $stored = json_decode((string) $row['RESULT_JSON'], true);

        Assert::same('REQUESTED', $row['STATE']);
        Assert::same(1895, $stored['destination_id_insc']);
        Assert::same(995, $stored['source_idpag']);
        Assert::same(1995, $stored['destination_idpag']);
        Assert::same('95.00', $stored['target_student_total']);
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
    }

    public function testRetryReusesSameDestinationBinding(): void
    {
        $db = TestDatabase::fresh();
        $cases = $this->seedCase($db, 896, 996);
        $repo = new UsocLifecycleExecutionRepository(new UuidGenerator());

        $this->preparation($cases, $repo)->prepare(
            $db,
            896,
            996,
            'uc013-bind-896-1',
            'operator-1',
            ['GESTIO'],
            $this->target()
        );

        $service = new UsocCourseChangeDestinationBindingService($repo);
        $first = $service->bind(
            $db,
            'uc013-bind-896-1',
            896,
            996,
            1896,
            1996,
            'SIF-USOC-CC:' . str_repeat('b', 32),
            '95.00'
        );
        $second = $service->bind(
            $db,
            'uc013-bind-896-1',
            896,
            996,
            1896,
            1996,
            'SIF-USOC-CC:' . str_repeat('b', 32),
            '95.00'
        );

        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same(
            $first['destination']['destination_id_insc'],
            $second['destination']['destination_id_insc']
        );
    }

    public function testDifferentDestinationForSameCheckpointConflicts(): void
    {
        $db = TestDatabase::fresh();
        $cases = $this->seedCase($db, 897, 997);
        $repo = new UsocLifecycleExecutionRepository(new UuidGenerator());

        $this->preparation($cases, $repo)->prepare(
            $db,
            897,
            997,
            'uc013-bind-897-1',
            'operator-1',
            ['GESTIO'],
            $this->target()
        );

        $service = new UsocCourseChangeDestinationBindingService($repo);
        $service->bind(
            $db,
            'uc013-bind-897-1',
            897,
            997,
            1897,
            1997,
            'SIF-USOC-CC:' . str_repeat('c', 32),
            '95.00'
        );

        Assert::throws(SifException::class, static function () use ($db, $service): void {
            $service->bind(
                $db,
                'uc013-bind-897-1',
                897,
                997,
                2897,
                1997,
                'SIF-USOC-CC:' . str_repeat('c', 32),
                '95.00'
            );
        }, 409);
    }

    public function testDifferentDestinationIdpagForSameCheckpointConflicts(): void
    {
        $db = TestDatabase::fresh();
        $cases = $this->seedCase($db, 899, 999);
        $repo = new UsocLifecycleExecutionRepository(new UuidGenerator());

        $this->preparation($cases, $repo)->prepare(
            $db,
            899,
            999,
            'uc013-bind-899-1',
            'operator-1',
            ['GESTIO'],
            $this->target()
        );

        $service = new UsocCourseChangeDestinationBindingService($repo);
        $service->bind(
            $db,
            'uc013-bind-899-1',
            899,
            999,
            1899,
            1999,
            'SIF-USOC-CC:' . str_repeat('e', 32),
            '95.00'
        );

        Assert::throws(SifException::class, static function () use ($db, $service): void {
            $service->bind(
                $db,
                'uc013-bind-899-1',
                899,
                999,
                1899,
                2999,
                'SIF-USOC-CC:' . str_repeat('e', 32),
                '95.00'
            );
        }, 409);
    }

    public function testAmountDifferentFromFrozenPreviewConflicts(): void
    {
        $db = TestDatabase::fresh();
        $cases = $this->seedCase($db, 898, 998);
        $repo = new UsocLifecycleExecutionRepository(new UuidGenerator());

        $this->preparation($cases, $repo)->prepare(
            $db,
            898,
            998,
            'uc013-bind-898-1',
            'operator-1',
            ['GESTIO'],
            $this->target()
        );

        $service = new UsocCourseChangeDestinationBindingService($repo);

        Assert::throws(SifException::class, static function () use ($db, $service): void {
            $service->bind(
                $db,
                'uc013-bind-898-1',
                898,
                998,
                1898,
                1998,
                'SIF-USOC-CC:' . str_repeat('d', 32),
                '94.00'
            );
        }, 409);
    }

    private function preparation(
        UsocFinancingCaseRepository $cases,
        UsocLifecycleExecutionRepository $repo
    ): UsocCourseChangeExecutionPreparationService {
        $guard = new UsocLifecycleGuardService($cases);
        $preview = new UsocCourseChangePreviewService(
            new UsocLifecyclePlanService($cases, $guard),
            new UsocCourseChangeTargetResolver(),
            new UsocCourseChangeFundPlanService()
        );

        return new UsocCourseChangeExecutionPreparationService($preview, $repo);
    }

    private function seedCase(
        \PDO $db,
        int $idInsc,
        int $idpag
    ): UsocFinancingCaseRepository {
        $student = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'TEST|UC013|BIND|STUDENT|' . $idInsc,
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
                'idempotency_key' => 'TEST|UC013|BIND|ENTITY|' . $idInsc,
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
            'BIND-' . $idInsc
        );
        $cases->recordEntityInvoice(
            $db,
            $idInsc,
            $idpag,
            $student['uuid_factura'],
            $entity['uuid_factura'],
            '75.00',
            '25.00',
            'BIND-' . $idInsc
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
