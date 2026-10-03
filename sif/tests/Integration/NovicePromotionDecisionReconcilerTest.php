<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Service\NovicePromotionDecisionReconciler;
use Prisma\Sif\Service\NovicePromotionSecretaryDecisionProjector;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class NovicePromotionDecisionReconcilerTest
{
    public function testProjectsApprovedLegacyDecisionAfterOriginalBridgeFailure(): void
    {
        [$db, $uuid] = $this->stage(1);
        $result = $this->service()->run($db, $db, 'system:reconcile-test');

        Assert::same(1, $result['candidates']);
        Assert::same(1, $result['projected']);
        Assert::same(0, $result['conflicts']);
        Assert::same(0, $result['errors']);
        Assert::same('READY_FOR_PAYMENT', (string) $db->query('SELECT STATUS FROM commercial_operation')->fetchColumn());
        Assert::same('VALIDATED', (string) $db->query('SELECT STATUS FROM discount_validation')->fetchColumn());

        $again = $this->service()->run($db, $db, 'system:reconcile-test');
        Assert::same(0, $again['candidates']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM discount_validation')->fetchColumn());
    }

    public function testProjectsRejectedLegacyDecisionWithoutGrantingPromotion(): void
    {
        [$db] = $this->stage(2);
        $result = $this->service()->run($db, $db, 'system:reconcile-test');

        Assert::same(1, $result['projected']);
        Assert::same('READY_FOR_PAYMENT', (string) $db->query('SELECT STATUS FROM commercial_operation')->fetchColumn());
        Assert::same('REJECTED', (string) $db->query('SELECT STATUS FROM discount_validation')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM commercial_entitlement')->fetchColumn());
    }

    public function testLeavesPendingLegacyDecisionClosedForPayment(): void
    {
        [$db] = $this->stage(0);
        $result = $this->service()->run($db, $db, 'system:reconcile-test');

        Assert::same(1, $result['candidates']);
        Assert::same(1, $result['pending_legacy_decision']);
        Assert::same(0, $result['projected']);
        Assert::same('PENDING_VALIDATION', (string) $db->query('SELECT STATUS FROM commercial_operation')->fetchColumn());
        Assert::same('PENDING', (string) $db->query('SELECT STATUS FROM discount_validation')->fetchColumn());
    }

    public function testIdentityConflictIsReportedAndDoesNotOpenPayment(): void
    {
        [$db] = $this->stage(1);
        $db->exec("UPDATE inscripcions SET DNI='DIFFERENT-ID'");

        $result = $this->service()->run($db, $db, 'system:reconcile-test');

        Assert::same(1, $result['conflicts']);
        Assert::same(0, $result['projected']);
        Assert::same('PENDING_VALIDATION', (string) $db->query('SELECT STATUS FROM commercial_operation')->fetchColumn());
        Assert::same('PENDING', (string) $db->query('SELECT STATUS FROM discount_validation')->fetchColumn());
    }

    private function service(): NovicePromotionDecisionReconciler
    {
        return new NovicePromotionDecisionReconciler(
            new NovicePromotionSecretaryDecisionProjector(new UuidGenerator())
        );
    }

    private function stage(int $legacyDecision): array
    {
        $db = TestDatabase::fresh();
        $db->exec('CREATE TEMPORARY TABLE inscripcions (ID INT PRIMARY KEY, CURS VARCHAR(12) NOT NULL, DNI VARCHAR(20) NOT NULL)');
        $db->exec('CREATE TEMPORARY TABLE recent_titulat (ID INT PRIMARY KEY, ID_INSC INT NOT NULL, VALIDAT INT NOT NULL)');
        $db->exec("INSERT INTO inscripcions (ID, CURS, DNI) VALUES (10, 'JASOM', '12345678Z')");
        $db->prepare('INSERT INTO recent_titulat (ID, ID_INSC, VALIDAT) VALUES (1, 10, ?)')->execute([$legacyDecision]);

        $uuid = (new UuidGenerator())->generate();
        $db->prepare(
            'INSERT INTO commercial_operation
             (UUID_OPERATION, IDEMPOTENCY_KEY, OPERATION_TYPE, SOURCE_CHANNEL,
              SOURCE_TYPE, SOURCE_ID, PRODUCT_TYPE, PRODUCT_CODE, CLASSIFICATION,
              CLASSIFICATION_REASON, STATUS, CURRENCY, GROSS_AMOUNT, DISCOUNT_AMOUNT,
              NET_AMOUNT, PRICE_SNAPSHOT_JSON, TAX_SNAPSHOT_JSON)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $uuid, 'NOVICE|RECONCILE|OP|' . $uuid, 'ENROLLMENT', 'WEB',
            'CURS', '10', 'CURS', 'JASOM', 'PENDING_VALIDATION',
            'NOVICE_REVIEW', 'PENDING_VALIDATION', 'EUR',
            '90.00', '0.00', '90.00', '{}', '{}',
        ]);

        $db->prepare(
            'INSERT INTO commercial_operation_party
             (UUID_OPERATION, PARTY_KEY, PARTY_ROLE, NIF_CIF, NOM_RAO, SNAPSHOT_JSON)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$uuid, 'student:canonical:12345678Z', 'PARTICIPANT', '12345678Z', 'Persona de prova', '{}']);

        $uuidValidation = (new UuidGenerator())->generate();
        $db->prepare(
            'INSERT INTO discount_validation
             (UUID_VALIDATION, UUID_OPERATION, DISCOUNT_TYPE, SUBJECT_PARTY_KEY,
              STATUS, RULE_VERSION, RULE_SNAPSHOT_JSON, REQUESTED_AT, IDEMPOTENCY_KEY)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $uuidValidation, $uuid, 'NOVICE_TEACHER', 'student:canonical:12345678Z',
            'PENDING', 'NOVICE_JASOM_V1', '{"source":"test"}',
            '2026-09-22 07:00:00', 'NOVICE|RECONCILE|VALIDATION|' . $uuid,
        ]);

        return [$db, $uuid];
    }
}
