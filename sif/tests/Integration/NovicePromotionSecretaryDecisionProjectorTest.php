<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\NovicePromotionSecretaryDecisionProjector;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class NovicePromotionSecretaryDecisionProjectorTest
{
    public function testApprovedDecisionOpensPaymentAndIsIdempotent(): void
    {
        [$db, $uuid] = $this->stage(1);
        $projector = new NovicePromotionSecretaryDecisionProjector(new UuidGenerator());
        $decidedAt = new \DateTimeImmutable('2026-09-22 10:00:00', new \DateTimeZone('Europe/Madrid'));

        $first = $projector->projectDecision($db, $db, $uuid, 'secretaria-test', $decidedAt);
        $repeat = $projector->projectDecision($db, $db, $uuid, 'secretaria-test', $decidedAt);

        Assert::same('VALIDATED', $first['decision']);
        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $repeat['idempotency_reused']);
        Assert::same($first['uuid_validation'], $repeat['uuid_validation']);
        Assert::same('READY_FOR_PAYMENT', (string) $db->query('SELECT STATUS FROM commercial_operation')->fetchColumn());
        Assert::same('BILLABLE', (string) $db->query('SELECT CLASSIFICATION FROM commercial_operation')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM discount_validation')->fetchColumn());
        Assert::same('2026-09-22 08:00:00', (string) $db->query('SELECT VALIDATED_AT FROM discount_validation')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
    }

    public function testRejectedDecisionOpensNormalPaymentWithoutNoviceGrant(): void
    {
        [$db, $uuid] = $this->stage(2);
        $result = (new NovicePromotionSecretaryDecisionProjector(new UuidGenerator()))
            ->projectDecision($db, $db, $uuid, 'secretaria-test');

        Assert::same('REJECTED', $result['decision']);
        Assert::same('READY_FOR_PAYMENT', (string) $db->query('SELECT STATUS FROM commercial_operation')->fetchColumn());
        Assert::same(null, $db->query('SELECT VALIDATED_AT FROM discount_validation')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM commercial_entitlement')->fetchColumn());
    }

    public function testPendingDecisionNeverOpensPayment(): void
    {
        [$db, $uuid] = $this->stage(0);
        Assert::throws(SifException::class, static function () use ($db, $uuid): void {
            (new NovicePromotionSecretaryDecisionProjector(new UuidGenerator()))
                ->projectDecision($db, $db, $uuid, 'secretaria-test');
        }, 409);

        Assert::same('PENDING_VALIDATION', (string) $db->query('SELECT STATUS FROM commercial_operation')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM discount_validation')->fetchColumn());
    }

    public function testWrongLegacyHolderCannotApproveSifParticipant(): void
    {
        [$db, $uuid] = $this->stage(1);
        $db->exec("UPDATE inscripcions SET DNI = 'DIFFERENT-ID'");

        Assert::throws(SifException::class, static function () use ($db, $uuid): void {
            (new NovicePromotionSecretaryDecisionProjector(new UuidGenerator()))
                ->projectDecision($db, $db, $uuid, 'secretaria-test');
        }, 409);

        Assert::same('PENDING_VALIDATION', (string) $db->query('SELECT STATUS FROM commercial_operation')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM discount_validation')->fetchColumn());
    }

    public function testDecisionCannotBeNewlyProjectedAfterPaymentGateOpened(): void
    {
        [$db, $uuid] = $this->stage(1);
        $db->prepare("UPDATE commercial_operation SET STATUS = 'READY_FOR_PAYMENT' WHERE UUID_OPERATION = ?")
            ->execute([$uuid]);

        Assert::throws(SifException::class, static function () use ($db, $uuid): void {
            (new NovicePromotionSecretaryDecisionProjector(new UuidGenerator()))
                ->projectDecision($db, $db, $uuid, 'secretaria-test');
        }, 409);
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM discount_validation')->fetchColumn());
    }

    private function stage(int $legacyDecision): array
    {
        $db = TestDatabase::fresh();
        // TEMPORARY tables provide synthetic legacy data inside this TEST ONLY.
        // In the application this class receives a distinct legacy read connection.
        $db->exec(
            'CREATE TEMPORARY TABLE inscripcions (ID INT PRIMARY KEY, CURS VARCHAR(12) NOT NULL, DNI VARCHAR(20) NOT NULL)'
        );
        $db->exec(
            'CREATE TEMPORARY TABLE recent_titulat (ID INT PRIMARY KEY, ID_INSC INT NOT NULL, VALIDAT INT NOT NULL)'
        );
        $db->exec("INSERT INTO inscripcions (ID, CURS, DNI) VALUES (10, 'JASOM', '12345678Z')");
        $db->prepare('INSERT INTO recent_titulat (ID, ID_INSC, VALIDAT) VALUES (1, 10, ?)')
            ->execute([$legacyDecision]);

        $uuid = (new UuidGenerator())->generate();
        $db->prepare(
            'INSERT INTO commercial_operation
             (UUID_OPERATION, IDEMPOTENCY_KEY, OPERATION_TYPE, SOURCE_CHANNEL,
              SOURCE_TYPE, SOURCE_ID, PRODUCT_TYPE, PRODUCT_CODE, CLASSIFICATION,
              CLASSIFICATION_REASON, STATUS, CURRENCY, GROSS_AMOUNT, DISCOUNT_AMOUNT,
              NET_AMOUNT, PRICE_SNAPSHOT_JSON, TAX_SNAPSHOT_JSON)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $uuid, 'NOVICE|PROJECTOR|' . $uuid, 'ENROLLMENT', 'WEB',
            'CURS', '10', 'CURS', 'JASOM', 'PENDING_VALIDATION',
            'NOVICE_REVIEW', 'PENDING_VALIDATION', 'EUR',
            '90.00', '0.00', '90.00', '{}', '{}',
        ]);

        $db->prepare(
            'INSERT INTO commercial_operation_party
             (UUID_OPERATION, PARTY_KEY, PARTY_ROLE, NIF_CIF, NOM_RAO, SNAPSHOT_JSON)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$uuid, 'student:canonical:12345678Z', 'PARTICIPANT', '12345678Z', 'Persona de prova', '{}']);

        return [$db, $uuid];
    }
}
