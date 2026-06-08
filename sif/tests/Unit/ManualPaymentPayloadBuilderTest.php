<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\ManualPaymentPayloadBuilder;
use Prisma\Sif\Service\PaymentPayloadValidator;
use Prisma\Sif\Tests\Support\Assert;

final class ManualPaymentPayloadBuilderTest
{
    public function testBuildsTransferPayloadForExistingInvoiceWithReference(): void
    {
        $payload = (new ManualPaymentPayloadBuilder())->forExistingInvoice(
            '11111111-1111-4111-8111-111111111111',
            [
                'amount' => '120',
                'movement_date' => '2026-06-06 10:30:00',
                'reference' => 'TRF900',
                'bank' => 'CAIXA',
            ]
        );

        Assert::same('TRANSFERENCIA|REF:TRF900', $payload['idempotency_key']);
        Assert::same('CHARGE', $payload['movement_type']);
        Assert::same('TRANSFERENCIA', $payload['method']);
        Assert::same('INTRANET', $payload['source_channel']);
        Assert::same('120.00', $payload['amount']);
        Assert::same('2026-06-06 10:30:00', $payload['movement_date']);
        Assert::same('TRF900', $payload['reference']);
        Assert::same('CAIXA', $payload['bank']);
        Assert::same('11111111-1111-4111-8111-111111111111', $payload['allocations'][0]['uuid_factura']);
        Assert::same('120.00', $payload['allocations'][0]['amount']);
        Assert::same('INVOICE_PAYMENT', $payload['allocations'][0]['allocation_type']);

        (new PaymentPayloadValidator())->validate($payload);
    }

    public function testBuildsFallbackIdempotencyWhenReferenceIsMissing(): void
    {
        $payload = (new ManualPaymentPayloadBuilder())->forExistingInvoice(
            '11111111-1111-4111-8111-111111111111',
            [
                'num_visible' => 'A2026/000123',
                'amount' => '75',
                'movement_date' => '2026-06-06',
                'reference' => '   ',
                'bank' => 'BANC TEST',
            ]
        );

        Assert::same(
            'TRANSFERENCIA|FACT:A2026/000123|DATA:2026-06-06|IMPORT:75.00|BANC:BANC_TEST',
            $payload['idempotency_key']
        );
        Assert::same('75.00', $payload['amount']);
        Assert::same('75.00', $payload['allocations'][0]['amount']);

        (new PaymentPayloadValidator())->validate($payload);
    }

    public function testRejectsMissingTransferAmount(): void
    {
        Assert::throws(
            SifException::class,
            static fn (): array => (new ManualPaymentPayloadBuilder())->forExistingInvoice(
                '11111111-1111-4111-8111-111111111111',
                ['movement_date' => '2026-06-06']
            ),
            422
        );
    }
}
