<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\LegacyCourseSnapshotRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Service\LegacyCourseInvoicePayloadBuilder;
use Prisma\Sif\Service\NovicePromotionCodePreparationService;
use Prisma\Sif\Service\NovicePromotionGrantService;
use Prisma\Sif\Service\NovicePromotionInvoiceLinkService;
use Prisma\Sif\Service\RedsysCourseInvoiceService;
use Prisma\Sif\Service\RedsysInvoicePayloadBuilder;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class NovicePromotionPostPaymentFlowTest
{
    public function testPartialThenFullPaymentThenDuplicateCallbackKeepsOneGrantAndOneCode(): void
    {
        $db = TestDatabase::fresh();
        $notifications = new RedsysNotificationRepository();
        $enrollmentId = 9111;
        $holder = 'person:uc111:flow';
        $this->stageApprovedJasom($db, $enrollmentId, $holder, '120.00');

        $service = new RedsysCourseInvoiceService(
            $notifications,
            new LegacyCourseSnapshotRepository(),
            new LegacyCourseInvoicePayloadBuilder(),
            new RedsysInvoicePayloadBuilder($notifications),
            IssueInvoiceTest::serviceFor($db),
            new NovicePromotionInvoiceLinkService(),
            new NovicePromotionGrantService(new UuidGenerator()),
            new NovicePromotionCodePreparationService(new UuidGenerator()),
            str_repeat('b', 64),
            'test-v1'
        );

        // 1) First installment: real invoice/payment exists, but no right yet.
        $notifications->recordReceived(
            $db,
            'UC111PARTIAL50',
            5011,
            '50.00',
            '0000',
            true,
            ['source' => 'uc111-flow-test'],
            'VALIDATED'
        );
        $partial = $service->issueFromIntentSnapshot(
            $db,
            'UC111PARTIAL50',
            $this->snapshot($enrollmentId, 5011, '50.00')
        );

        Assert::same('WAITING_FULL_PAYMENT', $partial['novice_promotion_sync']);
        Assert::same(false, array_key_exists('novice_promotion', $partial));
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM novice_promotion_grant')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM novice_promotion_code_outbox')->fetchColumn());

        // 2) Final installment: now the entire 120 EUR is reconciled.
        $notifications->recordReceived(
            $db,
            'UC111FINAL70',
            5012,
            '70.00',
            '0000',
            true,
            ['source' => 'uc111-flow-test'],
            'VALIDATED'
        );
        $full = $service->issueFromIntentSnapshot(
            $db,
            'UC111FINAL70',
            $this->snapshot($enrollmentId, 5012, '70.00')
        );

        Assert::same('ELIGIBLE_FOR_GRANT', $full['novice_promotion_sync']);
        Assert::same(false, $full['novice_promotion']['idempotency_reused']);
        Assert::same(false, $full['novice_promotion_code']['idempotency_reused']);
        Assert::same('120.00', $full['novice_promotion']['original_amount']);
        Assert::same('PREPARED', $full['novice_promotion_code']['delivery_status']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM novice_promotion_grant')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM novice_promotion_code_outbox')->fetchColumn());

        // 3) Duplicate callback/job replay: invoice/payment, grant and code are reused.
        $duplicate = $service->issueFromIntentSnapshot(
            $db,
            'UC111FINAL70',
            $this->snapshot($enrollmentId, 5012, '70.00')
        );

        Assert::same(true, $duplicate['idempotency_reused']);
        Assert::same(true, $duplicate['novice_promotion']['idempotency_reused']);
        Assert::same(true, $duplicate['novice_promotion_code']['idempotency_reused']);
        Assert::same(
            $full['novice_promotion']['uuid_entitlement'],
            $duplicate['novice_promotion']['uuid_entitlement']
        );

        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM novice_promotion_grant')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM novice_promotion_code_outbox')->fetchColumn());
        Assert::same(
            1,
            (int) $db->query("SELECT COUNT(*) FROM commercial_entitlement_event WHERE ACTION='ISSUE'")->fetchColumn()
        );
        Assert::same(
            1,
            (int) $db->query("SELECT COUNT(*) FROM commercial_entitlement_event WHERE ACTION='ACTIVATE'")->fetchColumn()
        );
    }

    private function stageApprovedJasom(\PDO $db, int $enrollmentId, string $holder, string $amount): void
    {
        $uuid = (new UuidGenerator())->generate();
        $validation = (new UuidGenerator())->generate();

        $stmt = $db->prepare(
            "INSERT INTO commercial_operation
             (UUID_OPERATION, IDEMPOTENCY_KEY, OPERATION_TYPE, SOURCE_CHANNEL,
              SOURCE_TYPE, SOURCE_ID, PRODUCT_TYPE, PRODUCT_CODE, PRODUCT_EDITION,
              CLASSIFICATION, CLASSIFICATION_REASON, STATUS, CURRENCY,
              GROSS_AMOUNT, DISCOUNT_AMOUNT, NET_AMOUNT, PRICE_SNAPSHOT_JSON, TAX_SNAPSHOT_JSON)
             VALUES (?, ?, 'ENROLLMENT', 'WEB', 'CURS', ?, 'CURS', 'JASOM', '2026/09',
                     'BILLABLE', 'NOVICE_DECIDED', 'READY_FOR_PAYMENT', 'EUR',
                     ?, '0.00', ?, ?, ?)"
        );
        $stmt->execute([
            $uuid,
            'NOVICE|JASOM|INSCRIPCIO:' . $enrollmentId,
            (string) $enrollmentId,
            $amount,
            $amount,
            json_encode(['source' => 'test', 'net_amount' => $amount], JSON_THROW_ON_ERROR),
            json_encode(['regime' => 'EXEMPT'], JSON_THROW_ON_ERROR),
        ]);

        $stmt = $db->prepare(
            "INSERT INTO commercial_operation_party
             (UUID_OPERATION, PARTY_KEY, PARTY_ROLE, NIF_CIF, NOM_RAO,
              PRODUCT_CODE, PRODUCT_EDITION, LINE_AMOUNT, SNAPSHOT_JSON)
             VALUES (?, ?, 'PARTICIPANT', '11111111H', 'Persona UC111',
                     'JASOM', '2026/09', ?, ?)"
        );
        $stmt->execute([
            $uuid,
            $holder,
            $amount,
            json_encode(['source' => 'test'], JSON_THROW_ON_ERROR),
        ]);

        $stmt = $db->prepare(
            "INSERT INTO discount_validation
             (UUID_VALIDATION, UUID_OPERATION, DISCOUNT_TYPE, SUBJECT_PARTY_KEY,
              STATUS, RULE_VERSION, RULE_SNAPSHOT_JSON, REQUESTED_AT,
              VALIDATED_AT, VALIDATED_BY, IDEMPOTENCY_KEY)
             VALUES (?, ?, 'NOVICE_TEACHER', ?, 'VALIDATED', 'NOVICE_JASOM_V1',
                     ?, '2026-09-29 10:00:00', '2026-09-29 10:05:00',
                     'secretaria:test', ?)"
        );
        $stmt->execute([
            $validation,
            $uuid,
            $holder,
            json_encode(['secretary_manual_review' => true], JSON_THROW_ON_ERROR),
            'NOVICE|DECISION|' . $uuid,
        ]);
    }

    private function snapshot(int $enrollmentId, int $idpag, string $amount): array
    {
        return [
            'inscription' => [
                'ID' => $enrollmentId,
                'ANY' => 2026,
                'MES' => '09',
                'CURS' => 'JASOM',
                'NOM' => 'Persona',
                'COGNOMS' => 'UC111',
                'DNI' => '11111111H',
                'CORREU' => 'uc111@example.test',
                'ADRECA' => 'Carrer Test 1',
                'Codi_Postal' => '08001',
                'Poblacio' => 'Barcelona',
                'FACTURA_RELACIONADA' => null,
                'A_PAGAR' => $amount,
                'INSC CURS' => '1',
                'PAGAMENT' => '0.00',
                'FRACCIO' => 1,
            ],
            'course' => [
                'NOM_CURS' => 'JASOM',
                'DATAI' => '2026-09-01',
                'DATAF' => '2026-09-30',
                'HORES' => '30',
            ],
            'payment' => [
                'idpag' => $idpag,
                'amount' => $amount,
            ],
        ];
    }
}
