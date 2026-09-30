<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\CommercialEntitlementRepository;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;
use Prisma\Sif\Service\GiftRedemptionService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class GiftRedemptionServiceTest
{
    public function testPreviewReadsPaidGiftWithoutMutation(): void
    {
        [$db, $code, $holder] = $this->fixture();
        $service = $this->service();
        $before = (int) $db->query('SELECT COUNT(*) FROM commercial_entitlement_event')->fetchColumn();

        $result = $service->preview(
            $db,
            $code,
            $holder,
            new \DateTimeImmutable('2026-09-30 10:00:00', new \DateTimeZone('UTC'))
        );

        Assert::same('ACTIVE', $result['status']);
        Assert::same('120.00', $result['face_value']);
        Assert::same($before, (int) $db->query('SELECT COUNT(*) FROM commercial_entitlement_event')->fetchColumn());
        Assert::same('ACTIVE', (string) $db->query('SELECT STATUS FROM commercial_entitlement')->fetchColumn());
    }

    public function testRedeemConsumesGiftOnceForMaterializedEnrollmentWithoutNewCharge(): void
    {
        [$db, $code, $holder, $destination] = $this->fixture(true);
        $service = $this->service();
        $chargesBefore = (int) $db->query(
            "SELECT COUNT(*) FROM payment_transaction WHERE TIPUS_MOVIMENT='CHARGE'"
        )->fetchColumn();

        $command = $this->command($code, $holder, $destination);
        $first = $service->redeem($db, $command);
        $second = $service->redeem($db, $command);

        Assert::same('CONSUMED', $first['status']);
        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same('501', $first['enrollment_id']);
        Assert::same($chargesBefore, (int) $db->query(
            "SELECT COUNT(*) FROM payment_transaction WHERE TIPUS_MOVIMENT='CHARGE'"
        )->fetchColumn());
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM enrollment_fund_movement
             WHERE MOVEMENT_TYPE='COMPENSATION_ALLOCATION'"
        )->fetchColumn());
        $movement = $db->query(
            "SELECT m.UUID_PAYMENT, m.ID_INSC_DESTI, m.IMPORT, m.UUID_OPERATION,
                    p.TIPUS_MOVIMENT, p.ESTAT
             FROM enrollment_fund_movement m
             JOIN payment_transaction p ON p.UUID_PAYMENT = m.UUID_PAYMENT
             WHERE m.MOVEMENT_TYPE='COMPENSATION_ALLOCATION'"
        )->fetch(\PDO::FETCH_ASSOC);
        Assert::same('501', (string) $movement['ID_INSC_DESTI']);
        Assert::same('120.00', (string) $movement['IMPORT']);
        Assert::same($destination, (string) $movement['UUID_OPERATION']);
        Assert::same('CHARGE', (string) $movement['TIPUS_MOVIMENT']);
        Assert::same('CONFIRMED', (string) $movement['ESTAT']);
        Assert::same((string) $first['fund_movement_uuid'], (string) $second['fund_movement_uuid']);
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM commercial_entitlement_event WHERE ACTION='RESERVE'"
        )->fetchColumn());
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM commercial_entitlement_event WHERE ACTION='CONSUME'"
        )->fetchColumn());
    }

    public function testConsumedGiftCannotMoveToDifferentEnrollment(): void
    {
        [$db, $code, $holder, $destination] = $this->fixture(true);
        $service = $this->service();
        $service->redeem($db, $this->command($code, $holder, $destination));

        $other = $this->createDestination($db, $holder, 777);
        Assert::throws(SifException::class, function () use ($db, $service, $code, $holder, $other): void {
            $service->redeem($db, $this->command($code, $holder, $other, 'UC018|OTHER'));
        }, 409);

        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM commercial_entitlement_event WHERE ACTION='CONSUME'"
        )->fetchColumn());
    }

    public function testDestinationWithDifferentValueIsBlockedBeforeConsumption(): void
    {
        [$db, $code, $holder, $destination] = $this->fixture(true);
        $db->prepare(
            "UPDATE commercial_operation
             SET GROSS_AMOUNT='150.00', DISCOUNT_AMOUNT='120.00', NET_AMOUNT='30.00'
             WHERE UUID_OPERATION = ?"
        )->execute([$destination]);

        Assert::throws(SifException::class, function () use (
            $db,
            $code,
            $holder,
            $destination
        ): void {
            $this->service()->redeem(
                $db,
                $this->command($code, $holder, $destination)
            );
        }, 409);

        Assert::same(
            'ACTIVE',
            (string) $db->query('SELECT STATUS FROM commercial_entitlement')->fetchColumn()
        );
        Assert::same(
            0,
            (int) $db->query(
                "SELECT COUNT(*) FROM enrollment_fund_movement
                 WHERE MOVEMENT_TYPE='COMPENSATION_ALLOCATION'"
            )->fetchColumn()
        );
    }

    public function testReplayBackfillsOrReusesSingleCompensationAllocation(): void
    {
        [$db, $code, $holder, $destination] = $this->fixture(true);
        $service = $this->service();

        $first = $service->redeem($db, $this->command($code, $holder, $destination));
        $second = $service->redeem(
            $db,
            $this->command($code, $holder, $destination, 'UC018|REDEEM|SECOND-KEY')
        );

        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['fund_movement_uuid'], $second['fund_movement_uuid']);
        Assert::same(
            1,
            (int) $db->query(
                "SELECT COUNT(*) FROM enrollment_fund_movement
                 WHERE MOVEMENT_TYPE='COMPENSATION_ALLOCATION'"
            )->fetchColumn()
        );
    }

    public function testWrongHolderGetsNeutralNotFound(): void
    {
        [$db, $code] = $this->fixture();
        Assert::throws(SifException::class, function () use ($db, $code): void {
            $this->service()->preview($db, $code, 'student:other');
        }, 404);
    }

    public function testExpiredGiftCannotBePreviewedOrConsumed(): void
    {
        [$db, $code, $holder, $destination] = $this->fixture(true, '2026-09-01 00:00:00');
        $now = new \DateTimeImmutable('2026-09-30 10:00:00', new \DateTimeZone('UTC'));

        Assert::throws(SifException::class, function () use ($db, $code, $holder, $now): void {
            $this->service()->preview($db, $code, $holder, $now);
        }, 409);

        Assert::throws(SifException::class, function () use ($db, $code, $holder, $destination, $now): void {
            $this->service()->redeem($db, $this->command($code, $holder, $destination), $now);
        }, 409);
        Assert::same('ACTIVE', (string) $db->query('SELECT STATUS FROM commercial_entitlement')->fetchColumn());
    }

    public function testUnreconciledPurchaseCannotBackGift(): void
    {
        [$db, $code, $holder] = $this->fixture();
        $db->exec("UPDATE payment_transaction SET ESTAT='PENDING'");

        Assert::throws(SifException::class, function () use ($db, $code, $holder): void {
            $this->service()->preview($db, $code, $holder);
        }, 409);
    }

    public function testDestinationMustAlreadyBeMaterializedAndOwnedByHolder(): void
    {
        [$db, $code, $holder] = $this->fixture();
        $wrong = $this->createDestination($db, 'student:someone-else', 888);

        Assert::throws(SifException::class, function () use ($db, $code, $holder, $wrong): void {
            $this->service()->redeem($db, $this->command($code, $holder, $wrong));
        }, 409);

        Assert::same('ACTIVE', (string) $db->query('SELECT STATUS FROM commercial_entitlement')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM commercial_entitlement_event')->fetchColumn());
    }

    private function service(): GiftRedemptionService
    {
        return new GiftRedemptionService(
            new CommercialEntitlementRepository(new UuidGenerator()),
            new EnrollmentFundMovementRepository(new UuidGenerator())
        );
    }

    private function command(
        string $code,
        string $holder,
        string $destination,
        string $key = 'UC018|REDEEM|ONE'
    ): array {
        return [
            'code' => $code,
            'holder_party_key' => $holder,
            'destination_operation_uuid' => $destination,
            'idempotency_key' => $key,
            'correlation_id' => 'UC018-CORR-1',
            'actor_id' => $holder,
        ];
    }

    private function fixture(bool $withDestination = false, ?string $expiresAt = '2027-09-30 00:00:00'): array
    {
        $db = TestDatabase::fresh();
        $holder = 'student:gift:1';
        $code = 'GIFT-TEST-SECRET-001';
        $origin = $this->createPaidOrigin($db);
        $uuid = (new UuidGenerator())->generate();

        $db->prepare(
            'INSERT INTO commercial_entitlement
             (UUID_ENTITLEMENT, ENTITLEMENT_TYPE, CODE_HASH, HOLDER_PARTY_KEY,
              ORIGIN_UUID_OPERATION, RULE_VERSION, RULE_SNAPSHOT_JSON, FACE_VALUE,
              CURRENCY, STATUS, ISSUED_AT, EXPIRES_AT, IDEMPOTENCY_KEY)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $uuid,
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
            $expiresAt,
            'TEST|GIFT|' . $uuid,
        ]);

        $destination = $withDestination ? $this->createDestination($db, $holder, 501) : null;

        return [$db, $code, $holder, $destination];
    }

    private function createPaidOrigin(\PDO $db): string
    {
        $uuid = new UuidGenerator();
        $operation = $uuid->generate();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'UC018|ORIGIN|INVOICE|' . $operation,
            'emesa_abans_cobrament' => 1,
            'totals' => [
                'import_base' => '120.00',
                'taxable_base' => '120.00',
                'total' => '120.00',
            ],
            'lines' => [[
                'concept' => 'Regal test',
                'detail' => 'Regal test',
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
        ]));

        $payment = RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' => 'UC018|ORIGIN|PAYMENT|' . $operation,
            'movement_type' => 'CHARGE',
            'method' => 'REDSYS',
            'source_channel' => 'WEB',
            'amount' => '120.00',
            'movement_date' => '2026-09-01 10:00:00',
            'reference' => 'GIFT-ORIGIN-' . $operation,
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
            $operation,
            'UC018|ORIGIN|OP|' . $operation,
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

        return $operation;
    }

    private function createDestination(\PDO $db, string $holder, int $idInsc): string
    {
        $operation = (new UuidGenerator())->generate();
        $db->prepare(
            'INSERT INTO commercial_operation
             (UUID_OPERATION, IDEMPOTENCY_KEY, OPERATION_TYPE, SOURCE_CHANNEL,
              SOURCE_TYPE, SOURCE_ID, PRODUCT_TYPE, PRODUCT_CODE,
              CLASSIFICATION, CLASSIFICATION_REASON, STATUS, CURRENCY,
              GROSS_AMOUNT, DISCOUNT_AMOUNT, NET_AMOUNT, PRICE_SNAPSHOT_JSON,
              TAX_SNAPSHOT_JSON)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $operation,
            'UC018|DEST|' . $operation,
            'ENROLLMENT',
            'WEB',
            'INSCRIPCIO',
            (string) $idInsc,
            'CURS',
            'COURSE-TEST',
            'NON_BILLABLE',
            'GIFT_REDEMPTION',
            'CONFIRMED',
            'EUR',
            '120.00',
            '120.00',
            '0.00',
            '{}',
            '{}',
        ]);

        $db->prepare(
            'INSERT INTO commercial_operation_party
             (UUID_OPERATION, PARTY_KEY, PARTY_ROLE, NOM_RAO, SNAPSHOT_JSON)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$operation, $holder, 'PARTICIPANT', 'Gift holder', '{}']);

        return $operation;
    }
}
