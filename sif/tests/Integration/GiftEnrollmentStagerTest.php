<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\CommercialEntitlementRepository;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;
use Prisma\Sif\Service\GiftEnrollmentStager;
use Prisma\Sif\Service\GiftRedemptionService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class GiftEnrollmentStagerTest
{
    public function testStagesCommittedLegacyGiftEnrollmentOnceWithoutPersistingRawCode(): void
    {
        [$db, $code, $holder] = $this->fixture();
        $service = $this->stager();

        $first = $service->stage($db, $db, 501, $code, $holder, $this->price());
        $second = $service->stage($db, $db, 501, $code, $holder, $this->price());

        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_operation'], $second['uuid_operation']);
        Assert::same('RESERVED', $first['status']);
        Assert::same(
            'RESERVED',
            (string) $db->query('SELECT STATUS FROM commercial_entitlement')->fetchColumn()
        );
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM commercial_entitlement_event WHERE ACTION='RESERVE'"
        )->fetchColumn());
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM commercial_operation
             WHERE SOURCE_TYPE='INSCRIPCIO' AND SOURCE_ID='501'"
        )->fetchColumn());

        $operation = $db->query(
            "SELECT OPERATION_TYPE, CLASSIFICATION, CLASSIFICATION_REASON,
                    STATUS, GROSS_AMOUNT, DISCOUNT_AMOUNT, NET_AMOUNT,
                    PRICE_SNAPSHOT_JSON
             FROM commercial_operation
             WHERE SOURCE_TYPE='INSCRIPCIO' AND SOURCE_ID='501'"
        )->fetch(\PDO::FETCH_ASSOC);

        Assert::same('ENROLLMENT', $operation['OPERATION_TYPE']);
        Assert::same('NON_BILLABLE', $operation['CLASSIFICATION']);
        Assert::same('GIFT_REDEMPTION', $operation['CLASSIFICATION_REASON']);
        Assert::same('RESERVED', $operation['STATUS']);
        Assert::same('120.00', (string) $operation['GROSS_AMOUNT']);
        Assert::same('120.00', (string) $operation['DISCOUNT_AMOUNT']);
        Assert::same('0.00', (string) $operation['NET_AMOUNT']);
        Assert::same(
            false,
            str_contains((string) $operation['PRICE_SNAPSHOT_JSON'], $code)
        );

        $party = $db->query(
            "SELECT PARTY_KEY, NIF_CIF, PRODUCT_CODE, PRODUCT_EDITION, SNAPSHOT_JSON
             FROM commercial_operation_party
             WHERE PARTY_ROLE='PARTICIPANT'"
        )->fetch(\PDO::FETCH_ASSOC);

        Assert::same($holder, $party['PARTY_KEY']);
        Assert::same('12345678Z', $party['NIF_CIF']);
        Assert::same('COURSE-TEST', $party['PRODUCT_CODE']);
        Assert::same('2026/09', $party['PRODUCT_EDITION']);
        Assert::same(false, str_contains((string) $party['SNAPSHOT_JSON'], $code));
    }

    public function testSecondEnrollmentCannotStageSameReservedGift(): void
    {
        [$db, $code, $holder] = $this->fixture();
        $service = $this->stager();
        $first = $service->stage($db, $db, 501, $code, $holder, $this->price());

        $db->prepare(
            'INSERT INTO inscripcions
             (ID, ANY, MES, CURS, DNI, NOM, COGNOMS, A_PAGAR,
              FACTURA_RELACIONADA, pag_observacions, IDPAG, OBSERVACIONS)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            502, 2026, '09', 'COURSE-TEST', '12345678Z', 'Persona', 'De prova',
            '0.00', '987', $code, 9002, 'CURS REGAL',
        ]);

        Assert::throws(SifException::class, function () use (
            $db,
            $code,
            $holder,
            $service
        ): void {
            $service->stage($db, $db, 502, $code, $holder, $this->price());
        }, 409);

        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM commercial_operation WHERE SOURCE_TYPE='INSCRIPCIO'"
        )->fetchColumn());
        Assert::same($first['uuid_operation'], (string) $db->query(
            "SELECT UUID_OPERATION FROM commercial_operation
             WHERE SOURCE_TYPE='INSCRIPCIO'"
        )->fetchColumn());
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM commercial_entitlement_event WHERE ACTION='RESERVE'"
        )->fetchColumn());
    }

    public function testRejectsCanonicalParticipantThatDoesNotOwnGiftEntitlement(): void
    {
        [$db, $code] = $this->fixture();

        Assert::throws(SifException::class, function () use ($db, $code): void {
            $this->stager()->stage(
                $db,
                $db,
                501,
                $code,
                'student:canonical:other',
                $this->price()
            );
        }, 409);

        Assert::same(0, (int) $db->query(
            "SELECT COUNT(*) FROM commercial_operation WHERE SOURCE_TYPE='INSCRIPCIO'"
        )->fetchColumn());
    }

    public function testRejectsLegacyGiftAlreadyLinkedToAnotherEnrollment(): void
    {
        [$db, $code, $holder] = $this->fixture();
        $db->exec('UPDATE regal SET USAT = 999');

        Assert::throws(SifException::class, function () use ($db, $code, $holder): void {
            $this->stager()->stage($db, $db, 501, $code, $holder, $this->price());
        }, 409);
    }

    public function testRejectsTamperedLegacyGiftRelationAndCodeLink(): void
    {
        [$db, $code, $holder] = $this->fixture();

        $db->exec('UPDATE inscripcions SET FACTURA_RELACIONADA = 111');
        Assert::throws(SifException::class, function () use ($db, $code, $holder): void {
            $this->stager()->stage($db, $db, 501, $code, $holder, $this->price());
        }, 409);

        $db->exec("UPDATE inscripcions
                   SET FACTURA_RELACIONADA = 987, pag_observacions = 'OTHER-CODE'");
        Assert::throws(SifException::class, function () use ($db, $code, $holder): void {
            $this->stager()->stage($db, $db, 501, $code, $holder, $this->price());
        }, 409);
    }

    public function testRejectsBrowserLikePriceThatDoesNotMatchGiftOrEnrollment(): void
    {
        [$db, $code, $holder] = $this->fixture();
        $wrong = $this->price();
        $wrong['gross_amount'] = '119.00';

        Assert::throws(SifException::class, function () use (
            $db,
            $code,
            $holder,
            $wrong
        ): void {
            $this->stager()->stage($db, $db, 501, $code, $holder, $wrong);
        }, 409);

        $wrongProduct = $this->price();
        $wrongProduct['product_code'] = 'OTHER';
        Assert::throws(SifException::class, function () use (
            $db,
            $code,
            $holder,
            $wrongProduct
        ): void {
            $this->stager()->stage(
                $db,
                $db,
                501,
                $code,
                $holder,
                $wrongProduct
            );
        }, 409);
    }

    public function testStageThenRedeemSagaCompletesExactlyOnceWithoutNewCharge(): void
    {
        [$db, $code, $holder] = $this->fixture();
        $stager = $this->stager();
        $staged = $stager->stage(
            $db,
            $db,
            501,
            $code,
            $holder,
            $this->price()
        );

        $chargesBefore = (int) $db->query(
            "SELECT COUNT(*) FROM payment_transaction WHERE TIPUS_MOVIMENT='CHARGE'"
        )->fetchColumn();

        $redemption = $this->redemption();
        $command = [
            'code' => $code,
            'holder_party_key' => $holder,
            'destination_operation_uuid' => $staged['uuid_operation'],
            'idempotency_key' => $staged['redemption_idempotency_key'],
            'correlation_id' => 'UC018-SAGA-501',
            'actor_id' => 'web-gift-redemption',
        ];

        $first = $redemption->redeem($db, $command);
        $stageReplay = $stager->stage(
            $db,
            $db,
            501,
            $code,
            $holder,
            $this->price()
        );
        $retryCommand = $command;
        $retryCommand['correlation_id'] = 'UC018-SAGA-501-RETRY';
        $second = $redemption->redeem($db, $retryCommand);

        Assert::same('CONSUMED', $first['status']);
        Assert::same(true, $stageReplay['idempotency_reused']);
        Assert::same($staged['uuid_operation'], $stageReplay['uuid_operation']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same(
            'COMPLETED',
            (string) $db->query(
                "SELECT STATUS FROM commercial_operation
                 WHERE SOURCE_TYPE='INSCRIPCIO' AND SOURCE_ID='501'"
            )->fetchColumn()
        );
        Assert::same(
            'CONSUMED',
            (string) $db->query('SELECT STATUS FROM commercial_entitlement')->fetchColumn()
        );
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM enrollment_fund_movement
             WHERE MOVEMENT_TYPE='COMPENSATION_ALLOCATION'"
        )->fetchColumn());
        Assert::same($chargesBefore, (int) $db->query(
            "SELECT COUNT(*) FROM payment_transaction WHERE TIPUS_MOVIMENT='CHARGE'"
        )->fetchColumn());
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM commercial_entitlement_event WHERE ACTION='CONSUME'"
        )->fetchColumn());
    }

    public function testUnclaimedGiftIsClaimedInsideSameStagingTransaction(): void
    {
        [$db, $code, $holder] = $this->fixture();
        $hash = hash('sha256', $code);
        $unclaimed = CommercialEntitlementRepository::unclaimedGiftHolderKey($hash);
        $statement = $db->prepare(
            'UPDATE commercial_entitlement SET HOLDER_PARTY_KEY = ?'
        );
        $statement->execute([$unclaimed]);

        $result = $this->stager()->stage(
            $db,
            $db,
            501,
            $code,
            $holder,
            $this->price()
        );

        Assert::same('RESERVED', $result['status']);
        Assert::same(
            $holder,
            (string) $db->query(
                'SELECT HOLDER_PARTY_KEY FROM commercial_entitlement'
            )->fetchColumn()
        );
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM commercial_entitlement_event WHERE ACTION='CLAIM'"
        )->fetchColumn());
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM commercial_entitlement_event WHERE ACTION='RESERVE'"
        )->fetchColumn());
    }

    private function stager(): GiftEnrollmentStager
    {
        return new GiftEnrollmentStager(
            new UuidGenerator(),
            new CommercialEntitlementRepository(new UuidGenerator())
        );
    }

    private function redemption(): GiftRedemptionService
    {
        return new GiftRedemptionService(
            new CommercialEntitlementRepository(new UuidGenerator()),
            new EnrollmentFundMovementRepository(new UuidGenerator())
        );
    }

    private function price(): array
    {
        return [
            'product_code' => 'COURSE-TEST',
            'product_edition' => '2026/09',
            'gross_amount' => '120.00',
            'currency' => 'EUR',
            'price_rule_version' => 'gift-exact-v1',
            'tax_snapshot' => [
                'regime' => 'EXEMPT',
                'taxable_base' => '0.00',
                'tax' => '0.00',
                'reason' => 'GIFT_REDEMPTION_NON_BILLABLE',
            ],
        ];
    }

    private function fixture(): array
    {
        $db = TestDatabase::fresh();
        $code = 'GIFT-STAGE-SECRET-001';
        $holder = 'student:canonical:12345678Z';

        $db->exec(
            'CREATE TEMPORARY TABLE inscripcions (
                ID INT PRIMARY KEY,
                ANY INT NOT NULL,
                MES VARCHAR(12) NOT NULL,
                CURS VARCHAR(80) NOT NULL,
                DNI VARCHAR(20) NOT NULL,
                NOM VARCHAR(80) NOT NULL,
                COGNOMS VARCHAR(120) NOT NULL,
                A_PAGAR DECIMAL(12,2) NOT NULL,
                FACTURA_RELACIONADA VARCHAR(30) NULL,
                pag_observacions VARCHAR(255) NULL,
                IDPAG INT NOT NULL,
                OBSERVACIONS VARCHAR(255) NULL
            )'
        );
        $db->exec(
            'CREATE TEMPORARY TABLE regal (
                ID INT PRIMARY KEY,
                CODI VARCHAR(200) NOT NULL,
                IMPORT DECIMAL(12,2) NOT NULL,
                FACT_REL VARCHAR(30) NULL,
                USAT INT NULL,
                CCURS VARCHAR(80) NULL,
                NOM_CURS VARCHAR(255) NULL
            )'
        );

        $db->prepare(
            'INSERT INTO inscripcions
             (ID, ANY, MES, CURS, DNI, NOM, COGNOMS, A_PAGAR,
              FACTURA_RELACIONADA, pag_observacions, IDPAG, OBSERVACIONS)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            501,
            2026,
            '09',
            'COURSE-TEST',
            '12345678Z',
            'Persona',
            'De prova',
            '0.00',
            '987',
            $code,
            9001,
            'CURS REGAL',
        ]);

        $db->prepare(
            'INSERT INTO regal
             (ID, CODI, IMPORT, FACT_REL, USAT, CCURS, NOM_CURS)
             VALUES (?, ?, ?, ?, NULL, ?, ?)'
        )->execute([
            77,
            $code,
            '120.00',
            '987',
            'COURSE-TEST',
            'Curs de prova',
        ]);

        $uuid = new UuidGenerator();
        $origin = $uuid->generate();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC018|STAGER|INVOICE|' . $origin,
                'emesa_abans_cobrament' => 1,
                'totals' => [
                    'import_base' => '120.00',
                    'taxable_base' => '120.00',
                    'total' => '120.00',
                ],
                'lines' => [[
                    'concept' => 'Regal stager test',
                    'detail' => 'Gift origin',
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
                    'source_id' => 77,
                ]],
            ])
        );

        $payment = RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' => 'UC018|STAGER|PAYMENT|' . $origin,
            'movement_type' => 'CHARGE',
            'method' => 'REDSYS',
            'source_channel' => 'WEB',
            'amount' => '120.00',
            'movement_date' => '2026-09-01 10:00:00',
            'reference' => 'GIFT-STAGER-' . $origin,
            'allocations' => [[
                'uuid_factura' => $invoice['uuid_factura'],
                'amount' => '120.00',
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ]);

        $db->prepare(
            'INSERT INTO commercial_operation
             (UUID_OPERATION, IDEMPOTENCY_KEY, OPERATION_TYPE, SOURCE_CHANNEL,
              SOURCE_TYPE, SOURCE_ID, PRODUCT_TYPE, PRODUCT_CODE,
              CLASSIFICATION, CLASSIFICATION_REASON, STATUS, CURRENCY,
              GROSS_AMOUNT, DISCOUNT_AMOUNT, NET_AMOUNT, PRICE_SNAPSHOT_JSON,
              TAX_SNAPSHOT_JSON, UUID_FACTURA, UUID_PAYMENT)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $origin,
            'UC018|STAGER|ORIGIN|' . $origin,
            'GIFT_PURCHASE',
            'WEB',
            'REGAL',
            '77',
            'REGAL',
            'GIFT',
            'BILLABLE',
            'GIFT_PURCHASE',
            'PAID',
            'EUR',
            '120.00',
            '0.00',
            '120.00',
            '{}',
            '{}',
            $invoice['uuid_factura'],
            $payment['uuid_payment'],
        ]);

        $entitlement = $uuid->generate();
        $db->prepare(
            'INSERT INTO commercial_entitlement
             (UUID_ENTITLEMENT, ENTITLEMENT_TYPE, CODE_HASH, HOLDER_PARTY_KEY,
              ORIGIN_UUID_OPERATION, RULE_VERSION, RULE_SNAPSHOT_JSON, FACE_VALUE,
              CURRENCY, STATUS, ISSUED_AT, EXPIRES_AT, IDEMPOTENCY_KEY)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $entitlement,
            'GIFT',
            hash('sha256', $code),
            $holder,
            $origin,
            'GIFT_V1',
            '{}',
            '120.00',
            'EUR',
            'ACTIVE',
            '2026-09-01 00:00:00',
            '2027-09-01 00:00:00',
            'UC018|STAGER|ENT|' . $entitlement,
        ]);

        return [$db, $code, $holder];
    }
}
