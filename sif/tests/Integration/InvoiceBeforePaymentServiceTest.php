<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\InvoiceBeforePaymentPayloadBuilder;
use Prisma\Sif\Service\InvoiceBeforePaymentService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class InvoiceBeforePaymentServiceTest
{
    public function testIssuesInvoiceBeforePaymentWithoutCreatingPayment(): void
    {
        $db = TestDatabase::fresh();
        $service = new InvoiceBeforePaymentService(
            new InvoiceBeforePaymentPayloadBuilder(),
            IssueInvoiceTest::serviceFor($db)
        );
        $input = Fixtures::invoicePayload([
            'idempotency_key' => 'INTRANET|FACTURA_ABANS_COBRAR|REF:PRE900',
            'source_channel' => 'INTRANET',
            'created_by' => 'gestio-factura-abans-cobrar',
        ]);

        $first = $service->issueBeforePayment($input);
        $second = $service->issueBeforePayment($input);

        Assert::same(true, $first['ok']);
        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_factura'], $second['uuid_factura']);
        Assert::same(1, (int) $db->query('SELECT EMESA_ABANS_COBRAMENT FROM factura')->fetchColumn());
        Assert::same('PENDING', (string) $db->query('SELECT ESTAT_COBRAMENT FROM factura')->fetchColumn());
        Assert::same('INTRANET', (string) $db->query('SELECT SOURCE_CHANNEL FROM factura')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM fiscal_queue')->fetchColumn());
    }

    public function testBuilderDerivesIdempotencyAndForcesInvoiceBeforePaymentFlags(): void
    {
        $payload = (new InvoiceBeforePaymentPayloadBuilder())->build(Fixtures::invoicePayload([
            'idempotency_key' => '',
            'source_channel' => 'REDSYS',
            'created_by' => '',
            'reference' => 'PRE 900',
        ]));

        Assert::same('INTRANET|FACTURA_ABANS_COBRAR|REF:PRE_900', $payload['idempotency_key']);
        Assert::same('INTRANET', $payload['source_channel']);
        Assert::same('intranet-factura-abans-cobrar', $payload['created_by']);
        Assert::same(1, $payload['emesa_abans_cobrament']);
    }

    public function testRejectsPaymentBlockBeforeIssuingInvoice(): void
    {
        $db = TestDatabase::fresh();
        $service = new InvoiceBeforePaymentService(
            new InvoiceBeforePaymentPayloadBuilder(),
            IssueInvoiceTest::serviceFor($db)
        );

        Assert::throws(SifException::class, function () use ($service): void {
            $service->issueBeforePayment(Fixtures::invoicePayload([
                'payment' => [
                    'amount' => '120.00',
                    'movement_date' => '2026-06-10',
                ],
            ]));
        }, 422);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
    }
}
