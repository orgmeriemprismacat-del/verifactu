<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\LegacyPrismaStudentPriceSnapshotResolver;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class LegacyPrismaStudentPriceSnapshotResolverTest
{
    public function testReconstructsHistoricalPrismaStudentPriceAtEnrollmentTime(): void
    {
        $db = $this->fixture();
        $result = (new LegacyPrismaStudentPriceSnapshotResolver())->resolve($db, $this->context());

        Assert::same('120.00', $result['gross_amount']);
        Assert::same('30.00', $result['discount_amount']);
        Assert::same('90.00', $result['net_amount']);
        Assert::same('LEGACY_AP_TARIFF_AT_ENROLLMENT_V1', $result['price_rule_version']);
        Assert::same(5, $result['price_source']['id_preu']);
    }

    public function testReconstructsHourScopedLegacyDiscountStoredInCursColumn(): void
    {
        $db = $this->fixture();
        $db->exec('DELETE FROM descomptes');
        $db->exec(
            "INSERT INTO descomptes
             (ID_PREU, TIPUS, DATAI, DATAF, CURS, HORES, MES, PREU)
             VALUES (5, 1, '2026-01-01', '2026-12-31', '30', 'IGNORED', '10', 90.00)"
        );

        $result = (new LegacyPrismaStudentPriceSnapshotResolver())->resolve($db, $this->context());

        Assert::same('120.00', $result['gross_amount']);
        Assert::same('30.00', $result['discount_amount']);
        Assert::same('90.00', $result['net_amount']);
    }

    public function testRejectsHistoricalTariffThatDoesNotMatchPersistedEnrollment(): void
    {
        $db = $this->fixture();
        $context = $this->context();
        $context['inscription']['A_PAGAR'] = '89.00';

        Assert::throws(SifException::class, static function () use ($db, $context): void {
            (new LegacyPrismaStudentPriceSnapshotResolver())->resolve($db, $context);
        }, 409);
    }

    public function testRejectsAmbiguousHistoricalPrismaStudentTariff(): void
    {
        $db = $this->fixture();
        $db->exec(
            "INSERT INTO descomptes
             (ID_PREU, TIPUS, DATAI, DATAF, CURS, HORES, MES, PREU)
             VALUES (5, 1, '2026-01-01', '2026-12-31', 'ABC', '30', '10', 90.00)"
        );

        Assert::throws(SifException::class, function () use ($db): void {
            (new LegacyPrismaStudentPriceSnapshotResolver())->resolve($db, $this->context());
        }, 409);
    }

    private function fixture(): \PDO
    {
        $db = TestDatabase::fresh();
        $db->exec(
            'CREATE TEMPORARY TABLE preu (
                ID INT NOT NULL,
                IMPORT DECIMAL(12,2) NOT NULL,
                DATAI DATETIME NOT NULL,
                DATAF DATETIME NULL
            )'
        );
        $db->exec(
            'CREATE TEMPORARY TABLE descomptes (
                ID_PREU INT NOT NULL,
                TIPUS INT NOT NULL,
                DATAI DATETIME NOT NULL,
                DATAF DATETIME NULL,
                CURS VARCHAR(20) NOT NULL,
                HORES VARCHAR(20) NOT NULL,
                MES VARCHAR(20) NOT NULL,
                PREU DECIMAL(12,2) NOT NULL
            )'
        );
        $db->exec(
            "INSERT INTO preu (ID, IMPORT, DATAI, DATAF)
             VALUES (5, 120.00, '2026-01-01', '2026-12-31')"
        );
        $db->exec(
            "INSERT INTO descomptes
             (ID_PREU, TIPUS, DATAI, DATAF, CURS, HORES, MES, PREU)
             VALUES (5, 1, '2026-01-01', '2026-12-31', 'TOTS', 'TOTS', 'TOTS', 90.00)"
        );

        return $db;
    }

    private function context(): array
    {
        return [
            'inscription' => [
                'ID' => 200,
                'IDPAG' => 900,
                'ANY' => 2026,
                'MES' => '10',
                'CURS' => 'ABC',
                'DATA_INSC' => '2026-09-30 10:00:00',
                'A_PAGAR' => '90.00',
            ],
            'course' => [
                'ID_PREU' => 5,
                'HORES' => 30,
                'NOM_CURS' => 'Curs de prova',
            ],
        ];
    }
}
