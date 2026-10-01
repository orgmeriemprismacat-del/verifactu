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
use Prisma\Sif\Repository\LegacyPrismaStudentHistoryRepository;
use Prisma\Sif\Repository\OperationalEventRepository;
use Prisma\Sif\Repository\RedsysPaymentIntentRepository;
use Prisma\Sif\Service\CommercialOfferService;
use Prisma\Sif\Service\PrismaStudentCourseCheckoutService;
use Prisma\Sif\Service\RedsysPaymentIntentService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class PrismaStudentCourseCheckoutServiceTest
{
    public function testStagesValidationOperationAndIntentFromSingleAuthoritativeSnapshot(): void
    {
        $db = $this->fixture(true);
        $service = $this->service($db);

        $result = $service->stageAndCreateIntent(
            $db,
            $db,
            200,
            'student:canonical:12345678Z',
            $this->price(),
            [
                'ds_order' => 'UC020ORDER1',
                'terminal' => '1',
                'created_by' => 'web-checkout',
                // Deliberately untrusted/conflicting fields: service must overwrite them.
                'source_type' => 'PACK',
                'source_id' => '999',
                'idpag' => 999,
                'expected_amount' => '1.00',
                'snapshot' => ['tampered' => true],
            ]
        );

        Assert::same('INTENT_CREATED', $result['status']);
        Assert::same('90.00', $result['amount']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM commercial_operation')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM discount_validation')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM commercial_operation_party')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM operational_event')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM redsys_payment_intent')->fetchColumn());

        $operation = $db->query('SELECT * FROM commercial_operation')->fetch(\PDO::FETCH_ASSOC);
        Assert::same('ALUMNE_PRISMA_VALIDATED', $operation['CLASSIFICATION_REASON']);
        Assert::same('120.00', (string) $operation['GROSS_AMOUNT']);
        Assert::same('30.00', (string) $operation['DISCOUNT_AMOUNT']);
        Assert::same('90.00', (string) $operation['NET_AMOUNT']);
        Assert::same($result['uuid_intent'], $operation['UUID_INTENT']);

        $validation = $db->query('SELECT * FROM discount_validation')->fetch(\PDO::FETCH_ASSOC);
        Assert::same('ALUMNE_PRISMA', $validation['DISCOUNT_TYPE']);
        Assert::same('VALIDATED', $validation['STATUS']);
        Assert::same(PrismaStudentDiscountPolicy::RULE_VERSION, $validation['RULE_VERSION']);
        Assert::same('30.00', (string) $validation['RESULT_DISCOUNT_AMOUNT']);

        $intent = (new RedsysPaymentIntentRepository())->findByDsOrder($db, 'UC020ORDER1');
        $snapshot = json_decode((string) $intent['SNAPSHOT_JSON'], true);
        Assert::same('CURS', $intent['SOURCE_TYPE']);
        Assert::same('200', (string) $intent['SOURCE_ID']);
        Assert::same(900, (int) $intent['IDPAG']);
        Assert::same('90.00', number_format((float) $intent['EXPECTED_AMOUNT'], 2, '.', ''));
        Assert::same('ALUMNE_PRISMA', $snapshot['discount']['origin']);
        Assert::same(false, trim((string) $snapshot['discount']['validation_uuid']) === '');
        Assert::same(200, (int) $snapshot['inscription']['ID']);
    }

    public function testEquivalentRetryReusesOperationValidationAndIntent(): void
    {
        $db = $this->fixture(true);
        $service = $this->service($db);
        $request = [
            'ds_order' => 'UC020ORDER2',
            'terminal' => '1',
            'created_by' => 'web-checkout',
        ];

        $first = $service->stageAndCreateIntent(
            $db, $db, 200, 'student:canonical:12345678Z', $this->price(), $request
        );
        $second = $service->stageAndCreateIntent(
            $db, $db, 200, 'student:canonical:12345678Z', $this->price(), $request
        );

        Assert::same($first['uuid_operation'], $second['uuid_operation']);
        Assert::same($first['uuid_validation'], $second['uuid_validation']);
        Assert::same($first['uuid_intent'], $second['uuid_intent']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM commercial_operation')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM discount_validation')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM commercial_operation_party')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM operational_event')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM redsys_payment_intent')->fetchColumn());
    }

    public function testRetryWithAnotherDsOrderCannotReplaceLinkedIntent(): void
    {
        $db = $this->fixture(true);
        $service = $this->service($db);

        $service->stageAndCreateIntent(
            $db,
            $db,
            200,
            'student:canonical:12345678Z',
            $this->price(),
            ['ds_order' => 'UC020ORDER2A', 'terminal' => '1', 'created_by' => 'web-checkout']
        );

        $price = $this->price();
        Assert::throws(SifException::class, static function () use ($db, $service, $price): void {
            $service->stageAndCreateIntent(
                $db,
                $db,
                200,
                'student:canonical:12345678Z',
                $price,
                ['ds_order' => 'UC020ORDER2B', 'terminal' => '1', 'created_by' => 'web-checkout']
            );
        }, 409);

        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM redsys_payment_intent')->fetchColumn());
        Assert::same('UC020ORDER2A', (string) $db->query('SELECT DS_ORDER FROM redsys_payment_intent')->fetchColumn());
    }

    public function testIneligibleEnrollmentCreatesNoCommercialState(): void
    {
        $db = $this->fixture(false);
        $service = $this->service($db);

        $price = $this->price();
        Assert::throws(SifException::class, static function () use ($db, $service, $price): void {
            $service->stageAndCreateIntent(
                $db,
                $db,
                200,
                'student:canonical:12345678Z',
                $price,
                ['ds_order' => 'UC020ORDER3', 'terminal' => '1']
            );
        }, 409);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM commercial_operation')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM discount_validation')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM redsys_payment_intent')->fetchColumn());
    }

    public function testTrustedPriceMustMatchRealEnrollmentNet(): void
    {
        $db = $this->fixture(true);
        $service = $this->service($db);
        $price = $this->price();
        $price['net_amount'] = '89.00';
        $price['discount_amount'] = '31.00';

        Assert::throws(SifException::class, static function () use ($db, $service, $price): void {
            $service->stageAndCreateIntent(
                $db,
                $db,
                200,
                'student:canonical:12345678Z',
                $price,
                ['ds_order' => 'UC020ORDER4', 'terminal' => '1']
            );
        }, 409);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM commercial_operation')->fetchColumn());
    }

    private function service(\PDO $db): PrismaStudentCourseCheckoutService
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

        return new PrismaStudentCourseCheckoutService(
            new LegacyPrismaStudentHistoryRepository(),
            new PrismaStudentDiscountPolicy(),
            $offers,
            $operations,
            $intentRepository,
            $intentService,
            $transactions
        );
    }

    private function fixture(bool $withEligibleHistory): \PDO
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
             (ID, IDPAG, ANY, MES, CURS, DATA_INSC, NOM, COGNOMS, DNI, CORREU, A_PAGAR, PAGAMENT, GENERAT, `INSC CURS`)
             VALUES
             (200, 900, 2026, '10', 'ABC', '2026-09-30 10:00:00', 'Maria', 'Exemple',
              '12345678Z', 'maria@example.invalid', 90.00, 0.00, 0, '1')"
        );

        if ($withEligibleHistory) {
            $db->exec(
                "INSERT INTO inscripcions
                 (ID, IDPAG, ANY, MES, CURS, DATA_INSC, NOM, COGNOMS, DNI, CORREU, A_PAGAR, PAGAMENT, GENERAT, `INSC CURS`)
                 VALUES
                 (100, 700, 2025, '09', 'OLD', '2025-08-20 10:00:00', 'Maria', 'Exemple',
                  '12345678Z', 'maria@example.invalid', 120.00, 120.00, 0, '1')"
            );
        }

        return $db;
    }

    private function price(): array
    {
        return [
            'gross_amount' => '120.00',
            'discount_amount' => '30.00',
            'net_amount' => '90.00',
            'course_title' => 'Curs de prova',
            'price_rule_version' => 'PRICE-2026-10',
            'tax_snapshot' => ['regime' => 'EXEMPT', 'tax' => '0.00'],
        ];
    }
}
