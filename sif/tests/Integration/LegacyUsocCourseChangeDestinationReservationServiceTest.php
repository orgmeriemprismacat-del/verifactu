<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

require_once dirname(__DIR__, 3)
    . '/codi-drive/intranet-actual/LegacyUsocCourseChangeDestinationStoreInterface.php';
require_once dirname(__DIR__, 3)
    . '/codi-drive/intranet-actual/LegacyUsocCourseChangeDestinationReservationService.php';

final class LegacyUsocCourseChangeDestinationReservationServiceTest
{
    public function testCreatesProvisionalDestinationWithoutCopyingPayment(): void
    {
        $store = new LegacyUsocCourseChangeDestinationFakeStore();
        $service = new \LegacyUsocCourseChangeDestinationReservationService($store);

        $result = $service->reserve(
            'uc013-course-change-880-1',
            880,
            '2027',
            '01',
            'CURS-B',
            '95.00'
        );

        Assert::same(true, $result['ok']);
        Assert::same(880, $result['source_id_insc']);
        Assert::same(1880, $result['destination_id_insc']);
        Assert::same(980, $result['idpag']);
        Assert::same('95.00', $result['target_student_total']);
        Assert::same('0.00', $result['legacy_payment']);
        Assert::same('0', $result['legacy_status']);
        Assert::same(false, $result['idempotency_reused']);
        Assert::same(false, $result['effects_applied']);
        Assert::same(false, $result['source_closed']);
        Assert::same(1, $store->insertCalls);
        Assert::same(1, $store->acquireCalls);
        Assert::same(1, $store->releaseCalls);
    }

    public function testRetryReusesSameDestination(): void
    {
        $store = new LegacyUsocCourseChangeDestinationFakeStore();
        $service = new \LegacyUsocCourseChangeDestinationReservationService($store);

        $first = $service->reserve(
            'uc013-course-change-880-1',
            880,
            '2027',
            '01',
            'CURS-B',
            '95.00'
        );
        $second = $service->reserve(
            'uc013-course-change-880-1',
            880,
            '2027',
            '01',
            'CURS-B',
            '95.00'
        );

        Assert::same($first['destination_id_insc'], $second['destination_id_insc']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same(1, $store->insertCalls);
        Assert::same(2, $store->releaseCalls);
    }

    public function testSameRequestWithDifferentTargetConflictsAndReleasesLock(): void
    {
        $store = new LegacyUsocCourseChangeDestinationFakeStore();
        $service = new \LegacyUsocCourseChangeDestinationReservationService($store);

        $service->reserve(
            'uc013-course-change-880-1',
            880,
            '2027',
            '01',
            'CURS-B',
            '95.00'
        );

        Assert::throws(\RuntimeException::class, static function () use ($service): void {
            $service->reserve(
                'uc013-course-change-880-1',
                880,
                '2027',
                '02',
                'CURS-C',
                '90.00'
            );
        }, 409);

        Assert::same(1, $store->insertCalls);
        Assert::same(2, $store->releaseCalls);
    }

    public function testInvalidSourceFailsClosedAndReleasesLock(): void
    {
        $store = new LegacyUsocCourseChangeDestinationFakeStore();
        $store->source['valid_desc'] = 0;
        $service = new \LegacyUsocCourseChangeDestinationReservationService($store);

        Assert::throws(\RuntimeException::class, static function () use ($service): void {
            $service->reserve(
                'uc013-course-change-880-2',
                880,
                '2027',
                '01',
                'CURS-B',
                '95.00'
            );
        }, 409);

        Assert::same(0, $store->insertCalls);
        Assert::same(1, $store->releaseCalls);
    }
}

final class LegacyUsocCourseChangeDestinationFakeStore
    implements \LegacyUsocCourseChangeDestinationStoreInterface
{
    public array $source = [
        'id' => 880,
        'idpag' => 980,
        'tipus_desc' => 4,
        'valid_desc' => 1,
        'status' => '1',
    ];

    public array $rows = [];
    public int $insertCalls = 0;
    public int $acquireCalls = 0;
    public int $releaseCalls = 0;

    public function acquire(string $lockName, int $timeoutSeconds = 10): void
    {
        $this->acquireCalls++;
    }

    public function release(string $lockName): void
    {
        $this->releaseCalls++;
    }

    public function source(int $idInsc): array
    {
        return $this->source;
    }

    public function findByMarker(string $marker): ?array
    {
        return $this->rows[$marker] ?? null;
    }

    public function insertFromSource(
        int $sourceId,
        string $targetYear,
        string $targetMonth,
        string $targetCourse,
        string $targetStudentTotal,
        string $marker
    ): array {
        $this->insertCalls++;

        $row = [
            'id' => 1000 + $sourceId,
            'idpag' => $this->source['idpag'],
            'year' => $targetYear,
            'month' => $targetMonth,
            'course' => $targetCourse,
            'a_pagar' => $targetStudentTotal,
            'pagament' => '0.00',
            'tipus_desc' => 4,
            'valid_desc' => 1,
            'status' => '0',
            'marker' => $marker,
        ];

        $this->rows[$marker] = $row;
        return $row;
    }
}
