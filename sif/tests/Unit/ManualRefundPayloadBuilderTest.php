<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\ManualRefundPayloadBuilder;
use Prisma\Sif\Service\PaymentPayloadValidator;
use Prisma\Sif\Tests\Support\Assert;

final class ManualRefundPayloadBuilderTest
{
    public function testBuildsRefundPayloadForExistingInvoice(): void
    {
        $payload = (new ManualRefundPayloadBuilder())->forExistingInvoice(
            '11111111-1111-4111-8111-111111111111',
            [
                'amount' => '40.00',
                'movement_date' => '2026-06-12 12:00:00',
                'reference' => 'RET-001',
                'bank' => 'CAIXA',
                'notes' => 'Devolucio manual validada',
            ]
        );

        (new PaymentPayloadValidator())->validate($payload);

        Assert::same('REFUND|REF:RET-001', $payload['idempotency_key']);
        Assert::same('REFUND', $payload['movement_type']);
        Assert::same('TRANSFERENCIA', $payload['method']);
        Assert::same('INTRANET', $payload['source_channel']);
        Assert::same('40.00', $payload['amount']);
        Assert::same('2026-06-12 12:00:00', $payload['movement_date']);
        Assert::same('RET-001', $payload['reference']);
        Assert::same('CAIXA', $payload['bank']);
        Assert::same('Devolucio manual validada', $payload['notes']);
        Assert::same('11111111-1111-4111-8111-111111111111', $payload['allocations'][0]['uuid_factura']);
        Assert::same('40.00', $payload['allocations'][0]['amount']);
        Assert::same('INVOICE_REFUND', $payload['allocations'][0]['allocation_type']);
    }

    public function testBuildsFallbackRefundIdempotencyWithoutReference(): void
    {
        $payload = (new ManualRefundPayloadBuilder())->forExistingInvoice(
            '11111111-1111-4111-8111-111111111111',
            [
                'amount' => '25.50',
                'movement_date' => '2026-06-12',
                'num_visible' => 'A2026/12',
                'bank' => 'BANC TEST',
            ]
        );

        Assert::same('REFUND|FACT:A2026/12|DATA:2026-06-12|IMPORT:25.50|BANC:BANC_TEST', $payload['idempotency_key']);
    }

    public function testRejectsInvalidRefundAmount(): void
    {
        Assert::throws(SifException::class, function (): void {
            (new ManualRefundPayloadBuilder())->forExistingInvoice(
                '11111111-1111-4111-8111-111111111111',
                [
                    'amount' => '0.00',
                    'movement_date' => '2026-06-12',
                ]
            );
        }, 422);
    }
}
