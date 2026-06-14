<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\ManualInvoicePayloadBuilder;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;

final class ManualInvoicePayloadBuilderTest
{
    public function testBuildsManualInvoicePayloadFromReference(): void
    {
        $payload = (new ManualInvoicePayloadBuilder())->build(Fixtures::invoicePayload([
            'idempotency_key' => '',
            'source_channel' => 'REDSYS',
            'source_type' => 'CURS',
            'created_by' => '',
            'reference' => 'FM 2026/77',
            'user' => 'adam',
        ]));

        Assert::same('INTRANET|MANUAL|REF:FM_2026/77', $payload['idempotency_key']);
        Assert::same('A', $payload['series']);
        Assert::same('F1', $payload['type']);
        Assert::same('INTRANET', $payload['source_channel']);
        Assert::same('MANUAL', $payload['source_type']);
        Assert::same('adam', $payload['created_by']);
    }

    public function testBuildsManualInvoicePaymentBlockWhenInvoiceIsBornPaid(): void
    {
        $payload = (new ManualInvoicePayloadBuilder())->build(Fixtures::invoicePayload([
            'idempotency_key' => '',
            'reference' => 'FM PAID 1',
            'created_by' => 'pablo',
            'payment' => [
                'movement_date' => '2026-06-12 12:30:00',
                'reference' => 'EFECTIU-1',
            ],
        ]));

        Assert::same('INTRANET|MANUAL|REF:FM_PAID_1', $payload['idempotency_key']);
        Assert::same('PAYMENT|INTRANET|MANUAL|REF:FM_PAID_1', $payload['payment']['idempotency_key']);
        Assert::same('CHARGE', $payload['payment']['movement_type']);
        Assert::same('MANUAL', $payload['payment']['method']);
        Assert::same('INTRANET', $payload['payment']['source_channel']);
        Assert::same('120.00', $payload['payment']['amount']);
        Assert::same('2026-06-12 12:30:00', $payload['payment']['movement_date']);
        Assert::same('EFECTIU-1', $payload['payment']['provider_ref']);
        Assert::same('EFECTIU-1', $payload['payment']['reference']);
        Assert::same('INVOICE_PAYMENT', $payload['payment']['allocation_type']);
    }

    public function testFallbackIdempotencyUsesUserDateAndPayloadHash(): void
    {
        $payload = (new ManualInvoicePayloadBuilder())->build(Fixtures::invoicePayload([
            'idempotency_key' => '',
            'reference' => '',
            'created_by' => 'meriem',
            'issue_date' => '2026-06-12',
        ]));

        Assert::matchesRegularExpression(
            '/^INTRANET\|MANUAL\|USUARI:meriem\|DATA:2026-06-12\|HASH:[0-9a-f]{16}$/',
            $payload['idempotency_key']
        );
    }

    public function testRejectsMissingManualIssuer(): void
    {
        Assert::throws(SifException::class, function (): void {
            (new ManualInvoicePayloadBuilder())->build(Fixtures::invoicePayload([
                'created_by' => '',
                'user' => '',
                'usuari' => '',
            ]));
        }, 422);
    }
}
