<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\PrismaStudentDiscountPolicy;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\CommercialOperationPartyRepository;
use Prisma\Sif\Repository\CommercialOperationRepository;
use Prisma\Sif\Repository\DiscountValidationRepository;
use Prisma\Sif\Repository\LegacyCourseSnapshotRepository;
use Prisma\Sif\Repository\LegacyPrismaStudentHistoryRepository;
use Prisma\Sif\Repository\OperationalEventRepository;
use Prisma\Sif\Repository\RedsysPaymentIntentRepository;
use Prisma\Sif\Service\CommercialOfferService;
use Prisma\Sif\Service\LegacyPrismaStudentPriceSnapshotResolver;
use Prisma\Sif\Service\PrismaStudentCourseCheckoutService;
use Prisma\Sif\Service\RedsysCoursePaymentIntentService;
use Prisma\Sif\Service\RedsysDsOrderGenerator;
use Prisma\Sif\Service\RedsysPaymentIntentService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class RedsysCoursePaymentIntentPrismaStudentTest
{
    public function testPrismaStudentEnrollmentStagesCommercialDecisionBeforeIntent(): void
    {
        $db = $this->fixture(false);

        $result = $this->service($db)->create($db, $db, [
            'idpag' => 900,
            'requested_amount' => '90.00',
            'terminal' => '1',
            'ds_order' => 'UC020REAL001',
            'created_by' => 'pay-prisma-cat',
        ]);

        Assert::same('90.00', $result['amount']);
        Assert::same('INTENT_CREATED', $result['status']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM commercial_operation')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM discount_validation')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM commercial_operation_party')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM operational_event')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM redsys_payment_intent')->fetchColumn());

        $operation = $db->query('SELECT * FROM commercial_operation')->fetch(\PDO::FETCH_ASSOC);
        Assert::same('120.00', (string) $operation['GROSS_AMOUNT']);
        Assert::same('30.00', (string) $operation['DISCOUNT_AMOUNT']);
        Assert::same('90.00', (string) $operation['NET_AMOUNT']);
        Assert::same('INTENT_CREATED', $operation['STATUS']);

        $intent = $db->query("SELECT * FROM redsys_payment_intent WHERE DS_ORDER='UC020REAL001'")
            ->fetch(\PDO::FETCH_ASSOC);
        $snapshot = json_decode((string) $intent['SNAPSHOT_JSON'], true);
        Assert::same('ALUMNE_PRISMA', $snapshot['discount']['origin']);
        Assert::same('FIXED_PRICE', $snapshot['discount']['mode']);
        Assert::same('120.00', $snapshot['discount']['base']);
        Assert::same('30.00', $snapshot['discount']['amount']);
        Assert::same(PrismaStudentDiscountPolicy::RULE_VERSION, $snapshot['discount']['rule_version']);
    }

    public function testPrismaStudentFractionalPaymentFailsClosedUntilFiscalModelExists(): void
    {
        $db = $this->fixture(true);

        Assert::throws(SifException::class, function () use ($db): void {
            $this->service($db)->create($db, $db, [
                'idpag' => 900,
                'requested_amount' => '45.00',
                'terminal' => '1',
                'ds_order' => 'UC020REAL002',
            ]);
        }, 409);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM commercial_operation')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM redsys_payment_intent')->fetchColumn());
    }

    public function testPrismaStudentCheckoutRejectsAmbiguousHistoricalDiscountPrice(): void
    {
        $db = $this->fixture(false);
        $db->exec(
            "INSERT INTO descomptes
             (ID_PREU, TIPUS, DATAI, DATAF, CURS, HORES, MES, PREU)
             VALUES (5, 1, '2026-01-01', '2026-12-31', 'ABC', '30', '10', 90.00)"
        );

        Assert::throws(SifException::class, function () use ($db): void {
            $this->service($db)->create($db, $db, [
                'idpag' => 900,
                'requested_amount' => '90.00',
                'terminal' => '1',
                'ds_order' => 'UC020REAL003',
            ]);
        }, 409);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM redsys_payment_intent')->fetchColumn());
    }

    private function service(\PDO $db): RedsysCoursePaymentIntentService
    {
        $uuid = new UuidGenerator();
        $transactions = new TransactionRunner($db);
        $operations = new CommercialOperationRepository();
        $intentRepository = new RedsysPaymentIntentRepository();
        $intentService = new RedsysPaymentIntentService($intentRepository, $uuid);
        $offers = new CommercialOfferService(
            $transactions,
            $operations,
            new DiscountValidationRepository(),
            new OperationalEventRepository($uuid),
            $uuid,
            new CommercialOperationPartyRepository()
        );

        return new RedsysCoursePaymentIntentService(
            new LegacyCourseSnapshotRepository(),
            $intentService,
            new RedsysDsOrderGenerator(),
            new PrismaStudentCourseCheckoutService(
                new LegacyPrismaStudentHistoryRepository(),
                new PrismaStudentDiscountPolicy(),
                $offers,
                $operations,
                $intentRepository,
                $intentService,
                $transactions
            ),
            new LegacyPrismaStudentPriceSnapshotResolver()
        );
    }

    private function fixture(bool $fractional): \PDO
    {
        $db = TestDatabase::fresh();

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
                CORREU VARCHAR(180) NULL,
                ADRECA VARCHAR(180) NULL,
                Codi_Postal VARCHAR(20) NULL,
                Poblacio VARCHAR(100) NULL,
                FACTURA_RELACIONADA BIGINT NULL,
                A_PAGAR DECIMAL(12,2) NOT NULL,
                `INSC CURS` VARCHAR(4) NOT NULL,
                PAGAMENT DECIMAL(12,2) NOT NULL DEFAULT 0,
                FRACCIONAT TINYINT NOT NULL DEFAULT 0,
                FRACCIO VARCHAR(20) NULL,
                TIPUS_DESC INT NOT NULL DEFAULT 0,
                VALID_DESC INT NOT NULL DEFAULT 1,
                OBSERVACIONS VARCHAR(255) NULL,
                GENERAT TINYINT NOT NULL DEFAULT 0
            )'
        );
        $db->exec(
            'CREATE TEMPORARY TABLE curs (
                ANY INT NOT NULL,
                MES CHAR(2) NOT NULL,
                CURS VARCHAR(20) NOT NULL,
                NOM_CURS VARCHAR(180) NOT NULL,
                DATAI DATE NOT NULL,
                DATAF DATE NOT NULL,
                HORES INT NOT NULL,
                ID_PREU INT NOT NULL
            )'
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
            "INSERT INTO inscripcions
             (ID, IDPAG, ANY, MES, CURS, DATA_INSC, NOM, COGNOMS, DNI, A_PAGAR,
              `INSC CURS`, PAGAMENT, FRACCIONAT, TIPUS_DESC, VALID_DESC, GENERAT)
             VALUES
             (200, 900, 2026, '10', 'ABC', '2026-09-30 10:00:00', 'Maria', 'Exemple',
              '12345678Z', 90.00, '1', 0.00, " . ($fractional ? '1' : '0') . ", 1, 1, 0),
             (100, 700, 2025, '09', 'OLD', '2025-08-20 10:00:00', 'Maria', 'Exemple',
              '12345678Z', 120.00, '1', 120.00, 0, 0, 1, 0)"
        );
        $db->exec(
            "INSERT INTO curs (ANY, MES, CURS, NOM_CURS, DATAI, DATAF, HORES, ID_PREU)
             VALUES (2026, '10', 'ABC', 'Curs de prova', '2026-10-01', '2026-10-31', 30, 5)"
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
}
