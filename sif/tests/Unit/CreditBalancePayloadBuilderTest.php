<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\CreditBalancePayloadBuilder;
use Prisma\Sif\Service\PaymentPayloadValidator;
use Prisma\Sif\Tests\Support\Assert;

final class CreditBalancePayloadBuilderTest
{
    public function testBuildsCreditBalancePayloadForEconomicHolder(): void
    {
        $payload = (new CreditBalancePayloadBuilder())->forCreditBalance([
            'holder_type' => 'student',
            'holder_id' => 10,
            'holder_nif_cif' => '12345678Z',
            'holder_name' => 'Client Exemple',
            'amount' => '80',
            'source_type' => 'BAIXA',
            'source_id' => 44,
            'uuid_factura_origen' => 'origin-uuid',
            'uuid_factura_rectificativa' => 'rect-uuid',
            'review_after' => '2031-06-12',
        ]);

        Assert::same('STUDENT', $payload['holder_type']);
        Assert::same(10, $payload['holder_id']);
        Assert::same('12345678Z', $payload['holder_nif_cif']);
        Assert::same('Client Exemple', $payload['holder_name']);
        Assert::same('80.00', $payload['amount']);
        Assert::same('BAIXA', $payload['source_type']);
        Assert::same(44, $payload['source_id']);
        Assert::same('origin-uuid', $payload['uuid_factura_origen']);
        Assert::same('rect-uuid', $payload['uuid_factura_rectificativa']);
        Assert::same('2031-06-12', $payload['review_after']);
    }

    public function testBuildsCompensationPaymentPayloadForFutureInvoice(): void
    {
        $payload = (new CreditBalancePayloadBuilder())->forCompensation(
            'credit-uuid',
            'invoice-uuid',
            ['amount' => '60', 'movement_date' => '2026-06-12', 'notes' => 'Us de saldo'],
            ['NUM_VISIBLE' => 'A2026/000001']
        );

        (new PaymentPayloadValidator())->validate($payload);

        Assert::same('COMPENSACIO|UUID_CREDIT:credit-uuid|FACT:A2026/000001|IMPORT:60.00', $payload['idempotency_key']);
        Assert::same('COMPENSATION', $payload['movement_type']);
        Assert::same('COMPENSACIO', $payload['method']);
        Assert::same('INTRANET', $payload['source_channel']);
        Assert::same('60.00', $payload['amount']);
        Assert::same('2026-06-12', $payload['movement_date']);
        Assert::same('credit-uuid', $payload['provider_ref']);
        Assert::same('Us de saldo', $payload['notes']);
        Assert::same('invoice-uuid', $payload['allocations'][0]['uuid_factura']);
        Assert::same('60.00', $payload['allocations'][0]['amount']);
        Assert::same('CREDIT_COMPENSATION', $payload['allocations'][0]['allocation_type']);
    }

    public function testRejectsInvalidCreditAmount(): void
    {
        Assert::throws(SifException::class, static function (): void {
            (new CreditBalancePayloadBuilder())->forCreditBalance([
                'holder_type' => 'STUDENT',
                'holder_name' => 'Client Exemple',
                'amount' => '0',
                'source_type' => 'BAIXA',
            ]);
        }, 422);
    }
}
