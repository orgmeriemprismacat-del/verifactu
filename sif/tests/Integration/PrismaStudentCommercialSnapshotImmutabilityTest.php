<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\PrismaStudentDiscountPolicy;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\LegacyPrismaStudentHistoryRepository;
use Prisma\Sif\Repository\RedsysPaymentIntentRepository;
use Prisma\Sif\Service\PrismaStudentCourseCheckoutService;
use Prisma\Sif\Service\RedsysPaymentIntentService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class PrismaStudentCommercialSnapshotImmutabilityTest
{
    public function testAcceptedPriceSnapshotCannotBeSilentlyRewritten(): void
    {
        $db = TestDatabase::fresh();
        $this->createLegacyEnrollmentFixture($db);
        $service = new PrismaStudentCourseCheckoutService(
            new LegacyPrismaStudentHistoryRepository(),
            new PrismaStudentDiscountPolicy(),
            new RedsysPaymentIntentService(new RedsysPaymentIntentRepository(), new UuidGenerator()),
            new UuidGenerator()
        );

        $request = [
            'ds_order' => 'UC020SNAP001',
            'terminal' => '1',
            'created_by' => 'web-checkout',
        ];
        $originalPrice = $this->price('PRICE-2026-10');

        $first = $service->stageAndCreateIntent(
            $db,
            $db,
            200,
            'student:canonical:12345678Z',
            $originalPrice,
            $request
        );

        $changedPrice = $this->price('PRICE-CHANGED-AFTER-OFFER');

        Assert::throws(SifException::class, static function () use (
            $db,
            $service,
            $changedPrice,
            $request
        ): void {
            $service->stageAndCreateIntent(
                $db,
                $db,
                200,
                'student:canonical:12345678Z',
                $changedPrice,
                $request
            );
        }, 409);

        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM commercial_operation')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM discount_validation')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM redsys_payment_intent')->fetchColumn());

        $stored = $db->query(
            'SELECT CLASSIFICATION, STATUS, PRICE_SNAPSHOT_JSON, UUID_INTENT FROM commercial_operation'
        )->fetch(\PDO::FETCH_ASSOC);
        $storedSnapshot = json_decode(
            (string) $stored['PRICE_SNAPSHOT_JSON'],
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        Assert::same('BILLABLE', $stored['CLASSIFICATION']);
        Assert::same('INTENT_CREATED', $stored['STATUS']);
        Assert::same('PRICE-2026-10', $storedSnapshot['price_rule_version']);
        Assert::same($first['uuid_intent'], (string) $stored['UUID_INTENT']);
    }

    private function createLegacyEnrollmentFixture(\PDO $db): void
    {
        $db->exec(
            'CREATE TEMPORARY TABLE inscripcions (
                ID INT PRIMARY KEY,
                IDPAG INT NULL,
                ANY INT NOT NULL,
                MES CHAR(2) NOT NULL,
                CURS VARCHAR(20) NOT NULL,
                DATA_INSC DATETIME NOT NULL,
                NOM VARCHAR(80) NOT NULL,
                COGNOMS VARCHAR(80) NOT NULL,
                DNI VARCHAR(20) NOT NULL,
                A_PAGAR DECIMAL(12,2) NOT NULL,
                PAGAMENT DECIMAL(12,2) NOT NULL DEFAULT 0,
                OBSERVACIONS VARCHAR(255) NULL,
                GENERAT TINYINT NOT NULL DEFAULT 0,
                FACTURA_RELACIONADA BIGINT NULL,
                `INSC CURS` VARCHAR(4) NOT NULL DEFAULT "1"
            )'
        );

        $db->exec(
            "INSERT INTO inscripcions
             (ID, IDPAG, ANY, MES, CURS, DATA_INSC, NOM, COGNOMS, DNI, A_PAGAR, PAGAMENT, GENERAT, `INSC CURS`)
             VALUES
             (200, 900, 2026, '10', 'ABC', '2026-09-30 10:00:00',
              'Maria', 'Exemple', '12345678Z', 90.00, 0.00, 0, '1'),
             (100, 700, 2025, '09', 'OLD', '2025-08-20 10:00:00',
              'Maria', 'Exemple', '12345678Z', 120.00, 120.00, 0, '1')"
        );
    }

    private function price(string $ruleVersion): array
    {
        return [
            'gross_amount' => '120.00',
            'discount_amount' => '30.00',
            'net_amount' => '90.00',
            'course_title' => 'Curs de prova',
            'price_rule_version' => $ruleVersion,
            'tax_snapshot' => [
                'regime' => 'EXEMPT',
                'tax' => '0.00',
            ],
        ];
    }
}
