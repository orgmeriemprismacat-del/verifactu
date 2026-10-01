<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\CommercialEntitlementRepository;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;
use Prisma\Sif\Service\GiftEnrollmentStager;
use Prisma\Sif\Service\GiftEntitlementIssuerService;
use Prisma\Sif\Service\GiftRedemptionOrchestrator;
use Prisma\Sif\Service\GiftRedemptionService;
use Prisma\Sif\Service\GiftRedemptionTrustedContextResolver;
use Prisma\Sif\Service\LegacyGiftUsageReconciler;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class GiftRedemptionEndToEndTest
{
    public function testPaidGiftToRedeemedEnrollmentIsTraceableAndReplaySafe(): void
    {
        $db = TestDatabase::fresh();
        $this->createLegacyTables($db);

        $giftCode = 'GIFT-E2E-SECRET-001';
        $gift = [
            'ID' => 177,
            'CODI' => $giftCode,
            'IMPORT' => '120.00',
            'FACT_REL' => 987,
            'USAT' => null,
            'CCURS' => 'COURSE-E2E',
            'NOM_CURS' => 'Curs E2E regal',
        ];
        $this->insertLegacyGift($db, $gift);

        $purchaseEvidence = $this->createPaidGiftEvidence($db, $gift);

        $entitlements = new CommercialEntitlementRepository(new UuidGenerator());
        $issued = (new GiftEntitlementIssuerService(
            new UuidGenerator(),
            $entitlements
        ))->issue(
            $db,
            $gift,
            [
                'uuid_factura' => $purchaseEvidence['uuid_factura'],
                'uuid_payment' => $purchaseEvidence['uuid_payment'],
            ],
            'ORDER-GIFT-E2E-177',
            'REDSYS',
            'gift-e2e-test'
        );

        Assert::same('UNCLAIMED', $issued['holder_state']);
        Assert::same('ACTIVE', $issued['status']);

        $this->insertLegacyEnrollment(
            $db,
            501,
            2026,
            '09',
            'COURSE-E2E',
            '12.345.678-z',
            'Persona',
            'Regal E2E',
            987,
            $giftCode,
            9001
        );

        $chargesBefore = (int) $db->query(
            "SELECT COUNT(*) FROM payment_transaction
             WHERE TIPUS_MOVIMENT='CHARGE'"
        )->fetchColumn();

        $orchestrator = new GiftRedemptionOrchestrator(
            new GiftRedemptionTrustedContextResolver($entitlements),
            new GiftEnrollmentStager(new UuidGenerator(), $entitlements),
            new GiftRedemptionService(
                $entitlements,
                new EnrollmentFundMovementRepository(new UuidGenerator())
            ),
            new LegacyGiftUsageReconciler()
        );

        $first = $orchestrator->execute(
            $db,
            $db,
            501,
            $giftCode,
            'WEB',
            'UC018-E2E-FIRST',
            'gift-e2e-actor'
        );

        $second = $orchestrator->execute(
            $db,
            $db,
            501,
            $giftCode,
            'WEB',
            'UC018-E2E-REPLAY',
            'gift-e2e-actor'
        );

        Assert::same('CONSUMED', $first['redemption']['status']);
        Assert::same(false, $first['redemption']['idempotency_reused']);
        Assert::same('RECONCILED', $first['legacy_reconciliation']['status']);
        Assert::same(false, $first['legacy_reconciliation']['idempotency_reused']);

        Assert::same('CONSUMED', $second['redemption']['status']);
        Assert::same(true, $second['redemption']['idempotency_reused']);
        Assert::same('RECONCILED', $second['legacy_reconciliation']['status']);
        Assert::same(true, $second['legacy_reconciliation']['idempotency_reused']);

        Assert::same(
            $first['stage']['uuid_operation'],
            $second['stage']['uuid_operation']
        );
        Assert::same(
            $first['redemption']['fund_movement_uuid'],
            $second['redemption']['fund_movement_uuid']
        );

        $canonicalHolder = 'person:id:' . hash('sha256', '12345678Z');
        $entitlement = $db->query(
            "SELECT HOLDER_PARTY_KEY, STATUS, CONSUMED_UUID_OPERATION
             FROM commercial_entitlement
             WHERE ENTITLEMENT_TYPE='GIFT'"
        )->fetch(\PDO::FETCH_ASSOC);

        Assert::same($canonicalHolder, (string) $entitlement['HOLDER_PARTY_KEY']);
        Assert::same('CONSUMED', (string) $entitlement['STATUS']);
        Assert::same(
            (string) $first['stage']['uuid_operation'],
            (string) $entitlement['CONSUMED_UUID_OPERATION']
        );

        Assert::same(
            501,
            (int) $db->query(
                "SELECT USAT FROM regal WHERE ID=177"
            )->fetchColumn()
        );

        $destination = $db->prepare(
            "SELECT SOURCE_TYPE, SOURCE_ID, STATUS, CLASSIFICATION,
                    CLASSIFICATION_REASON, GROSS_AMOUNT,
                    DISCOUNT_AMOUNT, NET_AMOUNT
             FROM commercial_operation
             WHERE UUID_OPERATION = ?"
        );
        $destination->execute([(string) $first['stage']['uuid_operation']]);
        $destinationRow = $destination->fetch(\PDO::FETCH_ASSOC);

        Assert::same('INSCRIPCIO', (string) $destinationRow['SOURCE_TYPE']);
        Assert::same('501', (string) $destinationRow['SOURCE_ID']);
        Assert::same('COMPLETED', (string) $destinationRow['STATUS']);
        Assert::same('NON_BILLABLE', (string) $destinationRow['CLASSIFICATION']);
        Assert::same(
            'GIFT_REDEMPTION',
            (string) $destinationRow['CLASSIFICATION_REASON']
        );
        Assert::same('120.00', (string) $destinationRow['GROSS_AMOUNT']);
        Assert::same('120.00', (string) $destinationRow['DISCOUNT_AMOUNT']);
        Assert::same('0.00', (string) $destinationRow['NET_AMOUNT']);

        Assert::same(
            1,
            (int) $db->query(
                "SELECT COUNT(*) FROM enrollment_fund_movement
                 WHERE MOVEMENT_TYPE='COMPENSATION_ALLOCATION'"
            )->fetchColumn()
        );
        Assert::same(
            1,
            (int) $db->query(
                "SELECT COUNT(*) FROM commercial_entitlement_event
                 WHERE ACTION='CLAIM'"
            )->fetchColumn()
        );
        Assert::same(
            1,
            (int) $db->query(
                "SELECT COUNT(*) FROM commercial_entitlement_event
                 WHERE ACTION='RESERVE'"
            )->fetchColumn()
        );
        Assert::same(
            1,
            (int) $db->query(
                "SELECT COUNT(*) FROM commercial_entitlement_event
                 WHERE ACTION='CONSUME'"
            )->fetchColumn()
        );
        Assert::same(
            $chargesBefore,
            (int) $db->query(
                "SELECT COUNT(*) FROM payment_transaction
                 WHERE TIPUS_MOVIMENT='CHARGE'"
            )->fetchColumn()
        );
        Assert::same(
            1,
            (int) $db->query(
                "SELECT COUNT(*) FROM commercial_operation
                 WHERE OPERATION_TYPE='GIFT_PURCHASE'
                   AND SOURCE_TYPE='REGAL'
                   AND SOURCE_ID='177'"
            )->fetchColumn()
        );
        Assert::same(
            1,
            (int) $db->query(
                "SELECT COUNT(*) FROM commercial_operation
                 WHERE OPERATION_TYPE='ENROLLMENT'
                   AND SOURCE_TYPE='INSCRIPCIO'
                   AND SOURCE_ID='501'"
            )->fetchColumn()
        );
    }

    private function createLegacyTables(\PDO $db): void
    {
        $db->exec(
            'CREATE TEMPORARY TABLE regal (
                ID INT PRIMARY KEY,
                CODI VARCHAR(200) NOT NULL,
                IMPORT DECIMAL(12,2) NOT NULL,
                FACT_REL INT NULL,
                USAT INT NULL,
                CCURS VARCHAR(80) NULL,
                NOM_CURS VARCHAR(255) NULL
            )'
        );

        $db->exec(
            'CREATE TEMPORARY TABLE inscripcions (
                ID INT PRIMARY KEY,
                ANY INT NOT NULL,
                MES VARCHAR(12) NOT NULL,
                CURS VARCHAR(80) NOT NULL,
                DNI VARCHAR(30) NOT NULL,
                NOM VARCHAR(100) NOT NULL,
                COGNOMS VARCHAR(160) NOT NULL,
                A_PAGAR DECIMAL(12,2) NOT NULL,
                FACTURA_RELACIONADA INT NULL,
                pag_observacions VARCHAR(255) NULL,
                IDPAG INT NOT NULL,
                OBSERVACIONS VARCHAR(255) NULL
            )'
        );
    }

    private function insertLegacyGift(\PDO $db, array $gift): void
    {
        $statement = $db->prepare(
            'INSERT INTO regal
             (ID, CODI, IMPORT, FACT_REL, USAT, CCURS, NOM_CURS)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $gift['ID'],
            $gift['CODI'],
            $gift['IMPORT'],
            $gift['FACT_REL'],
            $gift['USAT'],
            $gift['CCURS'],
            $gift['NOM_CURS'],
        ]);
    }

    private function insertLegacyEnrollment(
        \PDO $db,
        int $id,
        int $year,
        string $month,
        string $course,
        string $dni,
        string $name,
        string $surnames,
        int $factRel,
        string $giftCode,
        int $idpag
    ): void {
        $statement = $db->prepare(
            'INSERT INTO inscripcions
             (ID, ANY, MES, CURS, DNI, NOM, COGNOMS, A_PAGAR,
              FACTURA_RELACIONADA, pag_observacions, IDPAG, OBSERVACIONS)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            $id,
            $year,
            $month,
            $course,
            $dni,
            $name,
            $surnames,
            '0.00',
            $factRel,
            $giftCode,
            $idpag,
            'CURS REGAL',
        ]);
    }

    private function createPaidGiftEvidence(\PDO $db, array $gift): array
    {
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC017|E2E|GIFT|INVOICE',
                'source_channel' => 'REDSYS',
                'emesa_abans_cobrament' => 1,
                'totals' => [
                    'import_base' => '120.00',
                    'taxable_base' => '120.00',
                    'total' => '120.00',
                ],
                'lines' => [[
                    'concept' => 'Curs regal E2E',
                    'detail' => 'Compra regal E2E',
                    'quantity' => '1.00',
                    'unit_price' => '120.00',
                    'base' => '120.00',
                    'import_base' => '120.00',
                    'discount_amount' => '0.00',
                    'taxable_base' => '120.00',
                    'iva_regim' => 'EXEMPT',
                    'iva_pct' => '0.00',
                    'iva_import' => '0.00',
                    'total' => '120.00',
                    'source_type' => 'REGAL',
                    'source_id' => (int) $gift['ID'],
                ]],
                'relations' => [[
                    'source_type' => 'REGAL',
                    'source_id' => (int) $gift['ID'],
                    'factura_relacionada' => (int) $gift['FACT_REL'],
                    'ds_order' => 'ORDER-GIFT-E2E-177',
                    'visible_alumne' => 0,
                ]],
            ])
        );

        $payment = RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' => 'UC017|E2E|GIFT|PAYMENT',
            'movement_type' => 'CHARGE',
            'method' => 'REDSYS',
            'source_channel' => 'WEB',
            'amount' => '120.00',
            'movement_date' => '2026-09-01 10:00:00',
            'reference' => 'ORDER-GIFT-E2E-177',
            'allocations' => [[
                'uuid_factura' => $invoice['uuid_factura'],
                'amount' => '120.00',
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ]);

        return [
            'uuid_factura' => (string) $invoice['uuid_factura'],
            'uuid_payment' => (string) $payment['uuid_payment'],
        ];
    }
}
