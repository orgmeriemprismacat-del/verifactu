<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\PrismaStudentDiscountPolicy;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyPrismaStudentHistoryRepository;
use Prisma\Sif\Service\PrismaStudentEnrollmentOfferResolver;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class PrismaStudentEnrollmentOfferResolverTest
{
    public function testEligibleStudentGetsAuthoritativeCurrentLegacyPrice(): void
    {
        $db = $this->fixture(true);
        $service = $this->service();

        $offer = $service->resolve($db, '12345678z', 2026, '10', 'abc');

        Assert::same('ELIGIBLE', $offer['status']);
        Assert::same('ALUMNE_PRISMA', $offer['discount_type']);
        Assert::same(PrismaStudentDiscountPolicy::RULE_VERSION, $offer['rule_version']);
        Assert::same('120.00', $offer['gross_amount']);
        Assert::same('30.00', $offer['discount_amount']);
        Assert::same('90.00', $offer['net_amount']);
        Assert::same('EUR', $offer['currency']);
        Assert::same(5, $offer['price_source']['id_preu']);
        Assert::same('ABC', $offer['course']['code']);
        Assert::same('30', $offer['course']['hours']);
    }

    public function testNonEligibleStudentReturnsDecisionWithoutTrustedPrice(): void
    {
        $db = $this->fixture(false);

        $offer = $this->service()->resolve($db, '12345678Z', 2026, '10', 'ABC');

        Assert::same('NOT_ELIGIBLE', $offer['status']);
        Assert::same(false, array_key_exists('net_amount', $offer));
        Assert::same(false, array_key_exists('gross_amount', $offer));
    }

    public function testEligibleStudentWithoutCurrentApTariffIsNotPayable(): void
    {
        $db = $this->fixture(true);
        $db->exec('DELETE FROM descomptes');

        $offer = $this->service()->resolve($db, '12345678Z', 2026, '10', 'ABC');

        Assert::same('ELIGIBLE_NO_PRICE', $offer['status']);
        Assert::same('120.00', $offer['gross_amount']);
        Assert::same(false, array_key_exists('net_amount', $offer));
    }

    public function testAmbiguousCurrentApTariffFailsClosed(): void
    {
        $db = $this->fixture(true);
        $db->exec(
            "INSERT INTO descomptes
             (ID_PREU, TIPUS, DATAI, DATAF, CURS, MES, PREU)
             VALUES (5, 1, '2026-01-01', '2026-12-31', 'ABC', '10', 90.00)"
        );

        Assert::throws(SifException::class, function () use ($db): void {
            $this->service()->resolve($db, '12345678Z', 2026, '10', 'ABC');
        }, 409);
    }

    public function testTariffCanMatchLegacyHoursStoredInDescomptesCurs(): void
    {
        $db = $this->fixture(true);
        $db->exec("UPDATE descomptes SET CURS = '30'");

        $offer = $this->service()->resolve($db, '12345678Z', 2026, '10', 'ABC');

        Assert::same('ELIGIBLE', $offer['status']);
        Assert::same('90.00', $offer['net_amount']);
    }

    public function testInvalidEditionMonthIsRejectedBeforeLookup(): void
    {
        $db = $this->fixture(true);

        Assert::throws(SifException::class, function () use ($db): void {
            $this->service()->resolve($db, '12345678Z', 2026, 'TOTS', 'ABC');
        }, 422);
    }

    private function service(): PrismaStudentEnrollmentOfferResolver
    {
        return new PrismaStudentEnrollmentOfferResolver(
            new LegacyPrismaStudentHistoryRepository(),
            new PrismaStudentDiscountPolicy()
        );
    }

    private function fixture(bool $eligibleHistory): \PDO
    {
        $db = TestDatabase::fresh();

        $db->exec(
            'CREATE TEMPORARY TABLE inscripcions (
                ID INT PRIMARY KEY,
                DNI VARCHAR(40) NOT NULL,
                A_PAGAR DECIMAL(12,2) NOT NULL DEFAULT 0,
                PAGAMENT DECIMAL(12,2) NOT NULL DEFAULT 0,
                OBSERVACIONS VARCHAR(255) NULL,
                GENERAT TINYINT NOT NULL DEFAULT 0,
                IDPAG INT NULL,
                FACTURA_RELACIONADA BIGINT NULL,
                `INSC CURS` VARCHAR(4) NOT NULL DEFAULT "1"
            )'
        );
        if ($eligibleHistory) {
            $db->exec(
                "INSERT INTO inscripcions
                 (ID, DNI, A_PAGAR, PAGAMENT, GENERAT, IDPAG, `INSC CURS`)
                 VALUES (100, '12345678Z', 120.00, 120.00, 0, 700, '1')"
            );
        }

        $db->exec(
            'CREATE TEMPORARY TABLE curs (
                `ANY` INT NOT NULL,
                MES CHAR(2) NOT NULL,
                CURS VARCHAR(80) NOT NULL,
                NOM_CURS VARCHAR(180) NOT NULL,
                HORES INT NOT NULL,
                ID_PREU INT NOT NULL
            )'
        );
        $db->exec(
            "INSERT INTO curs (`ANY`, MES, CURS, NOM_CURS, HORES, ID_PREU)
             VALUES (2026, '10', 'ABC', 'Curs de prova', 30, 5)"
        );

        $db->exec(
            'CREATE TEMPORARY TABLE preu (
                ID INT NOT NULL,
                IMPORT DECIMAL(12,2) NOT NULL,
                DATAI DATETIME NOT NULL,
                DATAF DATETIME NULL
            )'
        );
        $db->exec(
            "INSERT INTO preu (ID, IMPORT, DATAI, DATAF)
             VALUES (5, 120.00, '2026-01-01', '2030-12-31')"
        );

        $db->exec(
            'CREATE TEMPORARY TABLE descomptes (
                ID_PREU INT NOT NULL,
                TIPUS INT NOT NULL,
                DATAI DATETIME NOT NULL,
                DATAF DATETIME NULL,
                CURS VARCHAR(80) NOT NULL,
                MES VARCHAR(20) NOT NULL,
                PREU DECIMAL(12,2) NOT NULL
            )'
        );
        $db->exec(
            "INSERT INTO descomptes
             (ID_PREU, TIPUS, DATAI, DATAF, CURS, MES, PREU)
             VALUES (5, 1, '2026-01-01', '2030-12-31', 'TOTS', 'TOTS', 90.00)"
        );

        return $db;
    }
}
