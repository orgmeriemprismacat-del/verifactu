<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\UsocLifecycleExecutionRepository;
use Prisma\Sif\Service\UsocCourseChangeLegacyHandoffService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class UsocCourseChangeLegacyHandoffServiceTest
{
    public function testPendingLegacySourceKeepsExecutionReadyWithoutApplyingEffects(): void
    {
        [$db, $repository, $requestId] = $this->checkpoint();
        $legacy = $this->legacy('1', '0.00', null);

        $result = (new UsocCourseChangeLegacyHandoffService($repository))->confirm(
            $db,
            $legacy,
            $requestId,
            'secretaria-test'
        );

        Assert::same(false, $result['completed']);
        Assert::same(true, $result['ready_for_legacy']);
        Assert::same('DESTINATION_RESERVED', $result['phase']);

        $stored = $repository->findByRequestId($db, $requestId);
        Assert::same('REQUESTED', $stored['STATE']);
        $payload = json_decode((string) $stored['RESULT_JSON'], true);
        Assert::same('DESTINATION_RESERVED', $payload['phase']);
        Assert::same(false, $payload['effects_applied']);
    }

    public function testClosedLegacySourceAdvancesDurableHandoffAndRetryReusesIt(): void
    {
        [$db, $repository, $requestId] = $this->checkpoint();
        $legacy = $this->legacy('C', '0.00', '2026-10-03 15:00:00');

        $service = new UsocCourseChangeLegacyHandoffService($repository);
        $first = $service->confirm(
            $db,
            $legacy,
            $requestId,
            'secretaria-test'
        );
        $retry = $service->confirm(
            $db,
            $legacy,
            $requestId,
            'secretaria-test'
        );

        Assert::same(true, $first['completed']);
        Assert::same(false, $first['ready_for_legacy']);
        Assert::same('LEGACY_COMPLETED', $first['phase']);
        Assert::same(false, $first['idempotency_reused']);

        Assert::same(true, $retry['completed']);
        Assert::same('LEGACY_COMPLETED', $retry['phase']);
        Assert::same(true, $retry['idempotency_reused']);

        $stored = $repository->findByRequestId($db, $requestId);
        Assert::same('REQUESTED', $stored['STATE']);
        $payload = json_decode((string) $stored['RESULT_JSON'], true);
        Assert::same('LEGACY_COMPLETED', $payload['phase']);
        Assert::same(true, $payload['source_closed']);
        Assert::same(true, $payload['legacy_handoff_completed']);
        Assert::same('C', $payload['legacy_source_status']);
        Assert::same('2026-10-03 15:00:00', $payload['legacy_source_closed_at']);
        Assert::same(false, $payload['effects_applied']);
    }

    public function testPartialLegacyClosureBecomesDurableReviewRequired(): void
    {
        [$db, $repository, $requestId] = $this->checkpoint();
        $legacy = $this->legacy('C', '5.00', '2026-10-03 15:00:00');

        Assert::throws(
            SifException::class,
            static function () use ($db, $legacy, $repository, $requestId): void {
                (new UsocCourseChangeLegacyHandoffService($repository))->confirm(
                    $db,
                    $legacy,
                    $requestId,
                    'secretaria-test'
                );
            },
            409
        );

        $stored = $repository->findByRequestId($db, $requestId);
        Assert::same('REVIEW_REQUIRED', $stored['STATE']);
        Assert::same(
            'LEGACY_SOURCE_NOT_CLOSED_CLEANLY',
            $stored['REVIEW_REASON']
        );
        $payload = json_decode((string) $stored['RESULT_JSON'], true);
        Assert::same('LEGACY_REVIEW_REQUIRED', $payload['phase']);
        Assert::same(false, $payload['legacy_handoff_completed']);
    }

    public function testDestinationMismatchFailsBeforeAdvancingCheckpoint(): void
    {
        [$db, $repository, $requestId] = $this->checkpoint();
        $legacy = $this->legacy('C', '0.00', '2026-10-03 15:00:00');
        $legacy->rows[1880]['A_PAGAR'] = '96.00';

        Assert::throws(
            SifException::class,
            static function () use ($db, $legacy, $repository, $requestId): void {
                (new UsocCourseChangeLegacyHandoffService($repository))->confirm(
                    $db,
                    $legacy,
                    $requestId,
                    'secretaria-test'
                );
            },
            409
        );

        $stored = $repository->findByRequestId($db, $requestId);
        Assert::same('REQUESTED', $stored['STATE']);
        $payload = json_decode((string) $stored['RESULT_JSON'], true);
        Assert::same('DESTINATION_RESERVED', $payload['phase']);
    }

    private function checkpoint(): array
    {
        $db = TestDatabase::fresh();
        $repository = new UsocLifecycleExecutionRepository(new UuidGenerator());
        $requestId = 'uc013-course-change-880-handoff';

        $request = [
            'target' => [
                'year' => '2027',
                'month' => '01',
                'course' => 'CURS-B',
                'kind' => 'COURSE',
                'title' => 'Curs B',
                'price_id' => 77,
                'target_standard_course_amount' => '120.00',
                'target_student_course_amount' => '90.00',
                'management_fee' => '5.00',
            ],
        ];
        $plan = [
            'target' => [
                'target_student_total' => '95.00',
                'target_entity_total' => '30.00',
            ],
        ];

        $db->beginTransaction();
        try {
            $repository->begin(
                $db,
                $requestId,
                'USOC|COURSE_CHANGE|ID_INSC:880',
                880,
                980,
                'COURSE_CHANGE',
                'secretaria-test',
                ['ADMIN'],
                $request,
                $plan
            );
            $repository->recordRequestedResult(
                $db,
                $requestId,
                [
                    'phase' => 'DESTINATION_RESERVED',
                    'source_id_insc' => 880,
                    'destination_id_insc' => 1880,
                    'source_idpag' => 980,
                    'destination_idpag' => 1980,
                    'reservation_marker' => 'SIF-USOC-CC:' . str_repeat('a', 32),
                    'target_student_total' => '95.00',
                    'effects_applied' => false,
                    'source_closed' => false,
                ]
            );
            $db->commit();
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }

        return [$db, $repository, $requestId];
    }

    private function legacy(
        string $sourceStatus,
        string $sourcePayment,
        ?string $closedAt
    ): UsocCourseChangeLegacyHandoffFakePdo {
        return new UsocCourseChangeLegacyHandoffFakePdo([
            880 => [
                'ID' => 880,
                'IDPAG' => 980,
                'TIPUS_DESC' => 4,
                'VALID_DESC' => 1,
                'INSC CURS' => $sourceStatus,
                'PAGAMENT' => $sourcePayment,
                'DATA_BAIXA' => $closedAt,
            ],
            1880 => [
                'ID' => 1880,
                'IDPAG' => 1980,
                'ANY' => 2027,
                'MES' => '01',
                'CURS' => 'CURS-B',
                'A_PAGAR' => '95.00',
                'PAGAMENT' => '0.00',
                'TIPUS_DESC' => 4,
                'VALID_DESC' => 1,
                'INSC CURS' => '0',
                'pag_observacions' => 'SIF-USOC-CC:' . str_repeat('a', 32),
            ],
        ]);
    }
}

final class UsocCourseChangeLegacyHandoffFakePdo extends \PDO
{
    public function __construct(public array $rows)
    {
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        return new UsocCourseChangeLegacyHandoffFakeStatement($this);
    }
}

final class UsocCourseChangeLegacyHandoffFakeStatement extends \PDOStatement
{
    private array $rows = [];

    public function __construct(
        private UsocCourseChangeLegacyHandoffFakePdo $db
    ) {
    }

    public function execute(?array $params = null): bool
    {
        $id = (int) (($params ?? [])[0] ?? 0);
        $row = $this->db->rows[$id] ?? null;
        $this->rows = is_array($row) ? [$row] : [];

        return true;
    }

    public function fetchAll(
        int $mode = \PDO::FETCH_DEFAULT,
        mixed ...$args
    ): array {
        return $this->rows;
    }
}
