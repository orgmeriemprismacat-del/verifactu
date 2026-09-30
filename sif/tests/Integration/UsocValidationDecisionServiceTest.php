<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\UsocValidationDecisionRepository;
use Prisma\Sif\Service\UsocValidationDecisionService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class UsocValidationDecisionServiceTest
{
    public function testBeginCreatesRequestedAndRetryAfterLegacyMutationAutoCommitsWithoutReapply(): void
    {
        $db = TestDatabase::fresh();
        $legacy = $this->legacyDb(4, 0);
        $service = $this->service();

        $first = $service->begin(
            $db,
            $legacy,
            'req-usoc-validation-1',
            880,
            1,
            'secretaria-test',
            ['ADMIN']
        );

        Assert::same(true, $first['tracked']);
        Assert::same('REQUESTED', $first['state']);
        Assert::same(true, $first['should_apply_legacy']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM usoc_validation_decision')->fetchColumn());

        $legacy->exec('UPDATE inscripcions SET VALID_DESC = 1 WHERE ID = 880');

        $retry = $service->begin(
            $db,
            $legacy,
            'req-usoc-validation-1',
            880,
            1,
            'secretaria-test',
            ['ADMIN']
        );

        Assert::same(true, $retry['tracked']);
        Assert::same('COMMITTED', $retry['state']);
        Assert::same(false, $retry['should_apply_legacy']);
        Assert::same(1, $retry['legacy_valid_desc']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM usoc_validation_decision')->fetchColumn());
        Assert::same('COMMITTED', (string) $db->query('SELECT STATE FROM usoc_validation_decision')->fetchColumn());
    }

    public function testCompleteCommitsOnlyAfterLegacyReachedDesiredState(): void
    {
        $db = TestDatabase::fresh();
        $legacy = $this->legacyDb(4, 0);
        $service = $this->service();

        $service->begin(
            $db,
            $legacy,
            'req-usoc-validation-2',
            880,
            2,
            'secretaria-test',
            ['ADMIN']
        );

        $pending = $service->complete($db, $legacy, 'req-usoc-validation-2', 'secretaria-test');
        Assert::same('REQUESTED', $pending['state']);
        Assert::same(true, $pending['should_apply_legacy']);

        $legacy->exec('UPDATE inscripcions SET VALID_DESC = 2 WHERE ID = 880');

        $committed = $service->complete($db, $legacy, 'req-usoc-validation-2', 'secretaria-test');
        Assert::same('COMMITTED', $committed['state']);
        Assert::same(false, $committed['should_apply_legacy']);
        Assert::same(2, $committed['legacy_valid_desc']);
    }

    public function testConflictingLegacyDecisionMovesRequestToReviewRequired(): void
    {
        $db = TestDatabase::fresh();
        $legacy = $this->legacyDb(4, 0);
        $service = $this->service();

        $service->begin(
            $db,
            $legacy,
            'req-usoc-validation-3',
            880,
            1,
            'secretaria-test',
            ['ADMIN']
        );

        $legacy->exec('UPDATE inscripcions SET VALID_DESC = 2 WHERE ID = 880');

        $result = $service->complete($db, $legacy, 'req-usoc-validation-3', 'secretaria-test');

        Assert::same('REVIEW_REQUIRED', $result['state']);
        Assert::same(false, $result['should_apply_legacy']);
        Assert::same('LEGACY_DECISION_CONFLICT', $result['review_reason']);
    }

    public function testSameRequestIdCannotBeReusedForDifferentDecision(): void
    {
        $db = TestDatabase::fresh();
        $legacy = $this->legacyDb(4, 0);
        $service = $this->service();

        $service->begin(
            $db,
            $legacy,
            'req-usoc-validation-4',
            880,
            1,
            'secretaria-test',
            ['ADMIN']
        );

        Assert::throws(SifException::class, function () use ($db, $legacy, $service): void {
            $service->begin(
                $db,
                $legacy,
                'req-usoc-validation-4',
                880,
                2,
                'secretaria-test',
                ['ADMIN']
            );
        }, 409);
    }

    public function testNonUsocDiscountIsNotTrackedAndMayContinueLegacyFlow(): void
    {
        $db = TestDatabase::fresh();
        $legacy = $this->legacyDb(2, 0);

        $result = $this->service()->begin(
            $db,
            $legacy,
            'req-other-discount-1',
            880,
            1,
            'secretaria-test',
            ['ADMIN']
        );

        Assert::same(false, $result['tracked']);
        Assert::same('NOT_USOC', $result['reason']);
        Assert::same(true, $result['should_apply_legacy']);
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM usoc_validation_decision')->fetchColumn());
    }

    public function testCompleteRejectsAnotherActor(): void
    {
        $db = TestDatabase::fresh();
        $legacy = $this->legacyDb(4, 0);
        $service = $this->service();

        $service->begin(
            $db,
            $legacy,
            'req-usoc-validation-5',
            880,
            1,
            'secretaria-a',
            ['ADMIN']
        );

        Assert::throws(SifException::class, function () use ($db, $legacy, $service): void {
            $service->complete($db, $legacy, 'req-usoc-validation-5', 'secretaria-b');
        }, 403);
    }

    private function service(): UsocValidationDecisionService
    {
        return new UsocValidationDecisionService(
            new UsocValidationDecisionRepository(new UuidGenerator())
        );
    }

    private function legacyDb(int $tipusDesc, int $validDesc): \PDO
    {
        $dsn = (string) getenv('SIF_LEGACY_DB_DSN');
        $user = (string) getenv('SIF_LEGACY_DB_USER');
        $password = (string) getenv('SIF_LEGACY_DB_PASSWORD');
        if ($dsn === '') {
            throw new \RuntimeException('SIF_LEGACY_DB_DSN is required for USOC validation decision tests');
        }

        $db = new \PDO($dsn, $user, $password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $db->exec('DROP TABLE IF EXISTS inscripcions');
        $db->exec(
            'CREATE TABLE inscripcions (
                ID BIGINT PRIMARY KEY,
                TIPUS_DESC INT NOT NULL,
                VALID_DESC INT NOT NULL
            ) ENGINE=InnoDB'
        );
        $stmt = $db->prepare(
            'INSERT INTO inscripcions (ID, TIPUS_DESC, VALID_DESC) VALUES (880, ?, ?)'
        );
        $stmt->execute([$tipusDesc, $validDesc]);

        return $db;
    }
}
