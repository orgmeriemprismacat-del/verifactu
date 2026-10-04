<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\UsocFinancingTermsRepository;
use Prisma\Sif\Service\UsocFinancingTermsService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class UsocFinancingTermsServiceTest
{
    public function testPreparesAndReusesImmutableValidatedUsocTerms(): void
    {
        $db = TestDatabase::fresh();
        $legacy = $this->legacyDb(4, 1, '75.00');
        $service = $this->service();

        $first = $service->prepare(
            $db,
            $legacy,
            'req-usoc-terms-1',
            880,
            980,
            '75.00',
            '25.00',
            'secretaria-test',
            ['ADMIN']
        );
        $retry = $service->prepare(
            $db,
            $legacy,
            'req-usoc-terms-1',
            880,
            980,
            '75.00',
            '25.00',
            'secretaria-test',
            ['ADMIN']
        );

        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $retry['idempotency_reused']);
        Assert::same((string) $first['UUID_TERMS'], (string) $retry['UUID_TERMS']);
        Assert::same('75.00', number_format((float) $first['STUDENT_AMOUNT'], 2, '.', ''));
        Assert::same('25.00', number_format((float) $first['ENTITY_AMOUNT'], 2, '.', ''));
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM usoc_financing_terms')->fetchColumn());

        $view = $service->view($db, 880, 980);
        Assert::same((string) $first['UUID_TERMS'], (string) $view['UUID_TERMS']);
    }

    public function testEquivalentNewRequestReusesExistingTerms(): void
    {
        $db = TestDatabase::fresh();
        $legacy = $this->legacyDb(4, 1, '75.00');
        $service = $this->service();

        $first = $service->prepare(
            $db, $legacy, 'req-usoc-terms-a', 880, 980,
            '75.00', '25.00', 'secretaria-a', ['ADMIN']
        );
        $second = $service->prepare(
            $db, $legacy, 'req-usoc-terms-b', 880, 980,
            '75.00', '25.00', 'secretaria-b', ['ADMIN']
        );

        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same((string) $first['UUID_TERMS'], (string) $second['UUID_TERMS']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM usoc_financing_terms')->fetchColumn());
    }

    public function testRejectsDifferentAmountsForExistingTerms(): void
    {
        $db = TestDatabase::fresh();
        $legacy = $this->legacyDb(4, 1, '75.00');
        $service = $this->service();

        $service->prepare(
            $db, $legacy, 'req-usoc-terms-fixed', 880, 980,
            '75.00', '25.00', 'secretaria-test', ['ADMIN']
        );

        $exception = Assert::throws(SifException::class, function () use ($db, $legacy, $service): void {
            $service->prepare(
                $db, $legacy, 'req-usoc-terms-change', 880, 980,
                '75.00', '30.00', 'secretaria-test', ['ADMIN']
            );
        }, 409);

        Assert::same(
            'USOC financing terms already exist with different amounts',
            $exception->getMessage()
        );
    }

    public function testRejectsStudentAmountDifferentFromLegacyAPagar(): void
    {
        $db = TestDatabase::fresh();
        $legacy = $this->legacyDb(4, 1, '75.00');

        $exception = Assert::throws(SifException::class, function () use ($db, $legacy): void {
            $this->service()->prepare(
                $db, $legacy, 'req-usoc-terms-drift', 880, 980,
                '74.00', '25.00', 'secretaria-test', ['ADMIN']
            );
        }, 409);

        Assert::same(
            'USOC financing student amount does not match legacy A_PAGAR',
            $exception->getMessage()
        );
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM usoc_financing_terms')->fetchColumn());
    }

    public function testRejectsTermsBeforeUsocValidation(): void
    {
        $db = TestDatabase::fresh();
        $legacy = $this->legacyDb(4, 0, '75.00');

        Assert::throws(SifException::class, function () use ($db, $legacy): void {
            $this->service()->prepare(
                $db, $legacy, 'req-usoc-terms-pending', 880, 980,
                '75.00', '25.00', 'secretaria-test', ['ADMIN']
            );
        }, 409);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM usoc_financing_terms')->fetchColumn());
    }

    private function service(): UsocFinancingTermsService
    {
        return new UsocFinancingTermsService(
            new UsocFinancingTermsRepository(new UuidGenerator())
        );
    }

    private function legacyDb(int $tipusDesc, int $validDesc, string $aPagar): \PDO
    {
        $dsn = (string) getenv('SIF_LEGACY_DB_DSN');
        $user = (string) getenv('SIF_LEGACY_DB_USER');
        $password = (string) getenv('SIF_LEGACY_DB_PASSWORD');
        if ($dsn === '') {
            throw new \RuntimeException('SIF_LEGACY_DB_DSN is required for USOC financing terms tests');
        }

        $db = new \PDO($dsn, $user, $password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
        $db->exec('DROP TABLE IF EXISTS inscripcions');
        $db->exec(
            'CREATE TABLE inscripcions (
                ID BIGINT PRIMARY KEY,
                IDPAG BIGINT NOT NULL,
                TIPUS_DESC INT NOT NULL,
                VALID_DESC INT NOT NULL,
                A_PAGAR DECIMAL(12,2) NOT NULL
            ) ENGINE=InnoDB'
        );
        $stmt = $db->prepare(
            'INSERT INTO inscripcions (ID, IDPAG, TIPUS_DESC, VALID_DESC, A_PAGAR)
             VALUES (880, 980, ?, ?, ?)'
        );
        $stmt->execute([$tipusDesc, $validDesc, $aPagar]);

        return $db;
    }
}
