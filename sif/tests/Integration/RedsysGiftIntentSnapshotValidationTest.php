<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\RedsysPaymentIntentRepository;
use Prisma\Sif\Service\RedsysPaymentIntentService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class RedsysGiftIntentSnapshotValidationTest
{
    public function testGenericIntentRejectsGiftSourceMismatch(): void
    {
        $db = TestDatabase::fresh();
        $input = $this->input();
        $input['source_id'] = '78';

        Assert::throws(SifException::class, function () use ($db, $input): void {
            $this->service()->create($db, $input);
        }, 422);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM redsys_payment_intent')->fetchColumn());
    }

    public function testGenericIntentRejectsGiftAmountMismatch(): void
    {
        $db = TestDatabase::fresh();
        $input = $this->input();
        $input['expected_amount'] = '119.99';

        Assert::throws(SifException::class, function () use ($db, $input): void {
            $this->service()->create($db, $input);
        }, 409);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM redsys_payment_intent')->fetchColumn());
    }

    public function testGenericIntentRejectsAlreadyInvoicedGiftSnapshot(): void
    {
        $db = TestDatabase::fresh();
        $input = $this->input();
        $input['snapshot']['gift']['FACT_REL'] = 1001;

        Assert::throws(SifException::class, function () use ($db, $input): void {
            $this->service()->create($db, $input);
        }, 409);
    }

    public function testGenericIntentRejectsSifPaidProjectionMarker(): void
    {
        $db = TestDatabase::fresh();
        $input = $this->input();
        $input['snapshot']['gift']['OBSERVACIONS'] =
            "Reserva\nSIF A2026/77 PAID 11111111-1111-4111-8111-111111111111";

        Assert::throws(SifException::class, function () use ($db, $input): void {
            $this->service()->create($db, $input);
        }, 409);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM redsys_payment_intent')->fetchColumn());
    }

    public function testGenericIntentRejectsConfirmedGiftPurchaseAlreadyInSif(): void
    {
        $db = TestDatabase::fresh();
        $db->prepare(
            "INSERT INTO commercial_operation
             (UUID_OPERATION, IDEMPOTENCY_KEY, OPERATION_TYPE, SOURCE_CHANNEL,
              SOURCE_TYPE, SOURCE_ID, PRODUCT_TYPE, PRODUCT_CODE, CLASSIFICATION,
              CLASSIFICATION_REASON, STATUS, CURRENCY, GROSS_AMOUNT,
              DISCOUNT_AMOUNT, NET_AMOUNT, PRICE_SNAPSHOT_JSON, TAX_SNAPSHOT_JSON,
              UUID_FACTURA, UUID_PAYMENT, CREATED_BY)
             VALUES (?, ?, 'GIFT_PURCHASE', 'REDSYS', 'REGAL', '77', 'REGAL',
                     'GIFT', 'BILLABLE', 'GIFT_PURCHASE', 'PAID', 'EUR',
                     120.00, 0.00, 120.00, '{}', '{}', NULL, NULL, 'test')"
        )->execute([
            '44444444-4444-4444-8444-444444444444',
            'GIFT|PURCHASE|REGAL:77',
        ]);

        Assert::throws(SifException::class, function () use ($db): void {
            $this->service()->create($db, $this->input());
        }, 409);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM redsys_payment_intent')->fetchColumn());
    }

    public function testGenericIntentAcceptsConsistentGiftSnapshot(): void
    {
        $db = TestDatabase::fresh();
        $result = $this->service()->create($db, $this->input());

        Assert::same(false, $result['idempotency_reused']);
        Assert::same(
            1,
            (int) $db->query("SELECT COUNT(*) FROM redsys_payment_intent WHERE SOURCE_TYPE='REGAL'")
                ->fetchColumn()
        );
    }

    private function service(): RedsysPaymentIntentService
    {
        return new RedsysPaymentIntentService(
            new RedsysPaymentIntentRepository(),
            new UuidGenerator()
        );
    }

    private function input(): array
    {
        return [
            'ds_order' => '770000000010',
            'idpag' => null,
            'source_type' => 'REGAL',
            'source_id' => '77',
            'expected_amount' => '120.00',
            'currency' => 'EUR',
            'terminal' => '1',
            'snapshot' => [
                'gift' => [
                    'ID' => 77,
                    'CODI' => 'REGAL-77',
                    'IMPORT' => '120.00',
                    'FACT_REL' => 0,
                ],
            ],
            'created_by' => 'test',
        ];
    }
}
