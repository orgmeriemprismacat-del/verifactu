<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Service\RedsysInvoicePayloadBuilder;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class RedsysInvoicePayloadBuilderTest
{
    public function testBuildsIssueInvoicePaymentPayloadFromValidatedNotification(): void
    {
        $db = TestDatabase::fresh();
        $notifications = new RedsysNotificationRepository();
        $builder = new RedsysInvoicePayloadBuilder($notifications);

        $notifications->recordReceived(
            $db,
            'ORDER300',
            300,
            '120.00',
            '0000',
            true,
            ['source' => 'test'],
            'VALIDATED'
        );

        $payload = $builder->buildFromValidatedNotification($db, 'ORDER300', Fixtures::invoicePayload([
            'relations' => [[
                'source_type' => 'INSCRIPCIO',
                'source_id' => 300,
                'factura_relacionada' => 700,
                'visible_alumne' => 1,
            ]],
        ]));

        Assert::same('REDSYS|CURS|IDPAG:300|ORDER:ORDER300', $payload['idempotency_key']);
        Assert::same('REDSYS', $payload['source_channel']);
        Assert::same('PAYMENT|REDSYS|ORDER:ORDER300', $payload['payment']['idempotency_key']);
        Assert::same('CHARGE', $payload['payment']['movement_type']);
        Assert::same('REDSYS', $payload['payment']['method']);
        Assert::same('120.00', $payload['payment']['amount']);
        Assert::same('ORDER300', $payload['payment']['provider_ref']);
        Assert::same('ORDER300', $payload['payment']['ds_order']);
        Assert::same(300, $payload['payment']['idpag']);
        Assert::same('ORDER300', $payload['relations'][0]['ds_order']);
        Assert::same(300, $payload['relations'][0]['idpag']);

        $result = IssueInvoiceTest::serviceFor($db)->issueInvoice($payload);

        Assert::same(true, $result['ok']);
        Assert::matchesRegularExpression('/^[0-9a-f-]{36}$/', $result['uuid_payment']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same('PAID', (string) $db->query('SELECT ESTAT_COBRAMENT FROM factura')->fetchColumn());
    }


    public function testResolvesCommercialOperationFromPersistedRedsysIntent(): void
    {
        $db = TestDatabase::fresh();
        $notifications = new RedsysNotificationRepository();
        $builder = new RedsysInvoicePayloadBuilder($notifications);
        $uuidIntent = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
        $uuidOperation = 'dddddddd-dddd-4ddd-8ddd-dddddddddddd';

        $db->prepare(
            'INSERT INTO redsys_payment_intent (
                UUID_INTENT, DS_ORDER, IDPAG, SOURCE_TYPE, SOURCE_ID,
                EXPECTED_AMOUNT, CURRENCY, TERMINAL, SNAPSHOT_JSON, STATUS, CREATED_BY
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $uuidIntent,
            'ORDER302',
            302,
            'CURS',
            '302',
            '120.00',
            'EUR',
            '1',
            '{}',
            'PENDING',
            'test-runner',
        ]);

        $db->prepare(
            'INSERT INTO commercial_operation (
                UUID_OPERATION, IDEMPOTENCY_KEY, OPERATION_TYPE, SOURCE_CHANNEL,
                SOURCE_TYPE, SOURCE_ID, PRODUCT_TYPE, PRODUCT_CODE, PRODUCT_EDITION,
                CLASSIFICATION, CLASSIFICATION_REASON, STATUS, CURRENCY,
                GROSS_AMOUNT, DISCOUNT_AMOUNT, NET_AMOUNT,
                PRICE_SNAPSHOT_JSON, CAPACITY_SNAPSHOT_JSON, TAX_SNAPSHOT_JSON,
                UUID_INTENT, CREATED_BY
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?)'
        )->execute([
            $uuidOperation,
            'COMMERCIAL|UC001|REDSYS-OP',
            'COURSE_ENROLLMENT',
            'WEB',
            'INSCRIPCIO',
            '302',
            'CURS',
            'TEST',
            '2026-10',
            'SALE',
            'COURSE_ENROLLMENT',
            'INTENT_CREATED',
            'EUR',
            '120.00',
            '0.00',
            '120.00',
            '{}',
            '{}',
            $uuidIntent,
            'test-runner',
        ]);

        $notifications->recordReceived(
            $db,
            'ORDER302',
            302,
            '120.00',
            '0000',
            true,
            ['source' => 'test'],
            'VALIDATED'
        );

        $payload = $builder->buildFromValidatedNotification(
            $db,
            'ORDER302',
            Fixtures::invoicePayload()
        );

        Assert::same($uuidOperation, $payload['uuid_operation']);

        $result = IssueInvoiceTest::serviceFor($db)->issueInvoice($payload);

        Assert::same(
            $result['uuid_factura'],
            (string) $db->query(
                'SELECT UUID_FACTURA FROM commercial_operation WHERE UUID_OPERATION = '
                . $db->quote($uuidOperation)
            )->fetchColumn()
        );
    }

    public function testRejectsNotificationThatIsNotValidated(): void
    {
        $db = TestDatabase::fresh();
        $notifications = new RedsysNotificationRepository();
        $builder = new RedsysInvoicePayloadBuilder($notifications);

        $notifications->recordReceived(
            $db,
            'ORDER301',
            301,
            '120.00',
            '0101',
            true,
            ['source' => 'test'],
            'ERROR'
        );

        Assert::throws(SifException::class, function () use ($db, $builder): void {
            $builder->buildFromValidatedNotification($db, 'ORDER301', Fixtures::invoicePayload());
        }, 409);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
    }
}
