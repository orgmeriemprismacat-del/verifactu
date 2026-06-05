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
