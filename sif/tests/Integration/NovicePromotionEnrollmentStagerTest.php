<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\NovicePromotionEnrollmentStager;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class NovicePromotionEnrollmentStagerTest
{
    public function testStagesRealPendingJasomWithoutPaymentOrUnapprovedDiscount(): void
    {
        $db = $this->fixture(0);
        $service = new NovicePromotionEnrollmentStager(new UuidGenerator());

        $first = $service->stage($db, $db, 10, 'student:canonical:12345678Z', $this->price());
        $second = $service->stage($db, $db, 10, 'student:canonical:12345678Z', $this->price());

        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_operation'], $second['uuid_operation']);
        Assert::same('PENDING_VALIDATION', $first['status']);
        Assert::same('90.00', (string) $db->query('SELECT NET_AMOUNT FROM commercial_operation')->fetchColumn());
        Assert::same('10.00', (string) $db->query('SELECT DISCOUNT_AMOUNT FROM commercial_operation')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM discount_validation')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM commercial_entitlement')->fetchColumn());
    }

    public function testRejectsWrongCommercialNetPriceAgainstEnrollment(): void
    {
        $db = $this->fixture(0);
        $wrong = $this->price();
        $wrong['net_amount'] = '89.00';
        Assert::throws(SifException::class, static function () use ($db, $wrong): void {
            (new NovicePromotionEnrollmentStager(new UuidGenerator()))
                ->stage($db, $db, 10, 'student:canonical:12345678Z', $wrong);
        }, 409);
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM commercial_operation')->fetchColumn());
    }

    public function testRejectsInscriptionWithNoviceDecisionAlreadyRecorded(): void
    {
        $db = $this->fixture(1);
        Assert::throws(SifException::class, static function () use ($db): void {
            (new NovicePromotionEnrollmentStager(new UuidGenerator()))
                ->stage($db, $db, 10, 'student:canonical:12345678Z', $this->price());
        }, 409);
    }

    public function testRejectsNonJasomCourseEvenWhenRecentTitulatExists(): void
    {
        $db = $this->fixture(0);
        $db->exec("UPDATE inscripcions SET CURS = 'ALTRE'");
        Assert::throws(SifException::class, static function () use ($db): void {
            (new NovicePromotionEnrollmentStager(new UuidGenerator()))
                ->stage($db, $db, 10, 'student:canonical:12345678Z', $this->price());
        }, 409);
    }

    public function testDoesNotReplaceExistingEnrollmentParticipantWithAnotherCanonicalKey(): void
    {
        $db = $this->fixture(0);
        $service = new NovicePromotionEnrollmentStager(new UuidGenerator());
        $service->stage($db, $db, 10, 'student:canonical:12345678Z', $this->price());

        Assert::throws(SifException::class, static function () use ($db, $service): void {
            $service->stage($db, $db, 10, 'student:different', $this->price());
        }, 409);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM commercial_operation')->fetchColumn());
    }

    private function fixture(int $legacyStatus): \PDO
    {
        $db = TestDatabase::fresh();
        $db->exec(
            'CREATE TEMPORARY TABLE inscripcions
             (ID INT PRIMARY KEY, CURS VARCHAR(12) NOT NULL, DNI VARCHAR(20) NOT NULL,
              NOM VARCHAR(80) NOT NULL, COGNOMS VARCHAR(80) NOT NULL,
              A_PAGAR DECIMAL(12,2) NOT NULL, ANY INT NOT NULL, MES CHAR(2) NOT NULL)'
        );
        $db->exec(
            'CREATE TEMPORARY TABLE recent_titulat
             (ID INT PRIMARY KEY, ID_INSC INT NOT NULL, VALIDAT INT NOT NULL)'
        );
        $db->exec(
            "INSERT INTO inscripcions
             (ID, CURS, DNI, NOM, COGNOMS, A_PAGAR, ANY, MES)
             VALUES (10, 'JASOM', '12345678Z', 'Persona', 'De prova', 90.00, 2026, '09')"
        );
        $db->prepare('INSERT INTO recent_titulat (ID, ID_INSC, VALIDAT) VALUES (1, 10, ?)')
            ->execute([$legacyStatus]);

        return $db;
    }

    private function price(): array
    {
        return [
            'gross_amount' => '100.00',
            'discount_amount' => '10.00',
            'net_amount' => '90.00',
            'price_rule_version' => 'test-1',
            'tax_snapshot' => ['regime' => 'EXEMPT', 'tax' => '0.00'],
        ];
    }
}
