<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\ClaimPaymentPayloadBuilder;
use Prisma\Sif\Service\PaymentPayloadValidator;
use Prisma\Sif\Tests\Support\Assert;

final class ClaimPaymentPayloadBuilderTest
{
    public function testBuildsClaimPaymentPayloadWithClaimReference(): void
    {
        $payload = (new ClaimPaymentPayloadBuilder())->forExistingInvoice(
            '11111111-1111-4111-8111-111111111111',
            [
                'amount' => '80',
                'movement_date' => '2026-06-13 12:00:00',
                'claim_reference' => 'REC-2026-001',
                'bank' => 'CAIXA',
                'created_by' => 'admin-cobraments',
                'notes' => 'Pagament rebut despres de reclamacio',
            ]
        );

        Assert::same('CLAIM|REF:REC-2026-001', $payload['idempotency_key']);
        Assert::same('CHARGE', $payload['movement_type']);
        Assert::same('TRANSFERENCIA', $payload['method']);
        Assert::same('INTRANET', $payload['source_channel']);
        Assert::same('80.00', $payload['amount']);
        Assert::same('2026-06-13 12:00:00', $payload['movement_date']);
        Assert::same('REC-2026-001', $payload['reference']);
        Assert::same('CAIXA', $payload['bank']);
        Assert::same('admin-cobraments', $payload['created_by']);
        Assert::same('11111111-1111-4111-8111-111111111111', $payload['allocations'][0]['uuid_factura']);
        Assert::same('80.00', $payload['allocations'][0]['amount']);
        Assert::same('CLAIM_PAYMENT', $payload['allocations'][0]['allocation_type']);

        (new PaymentPayloadValidator())->validate($payload);
    }

    public function testBuildsFallbackIdempotencyWithRequiredUserWhenReferenceIsMissing(): void
    {
        $payload = (new ClaimPaymentPayloadBuilder())->forExistingInvoice(
            '11111111-1111-4111-8111-111111111111',
            [
                'num_visible' => 'A2026/000321',
                'amount' => '40',
                'movement_date' => '2026-06-13',
                'created_by' => 'admin-cobraments',
            ]
        );

        Assert::same(
            'CLAIM|FACT:A2026/000321|DATA:2026-06-13|IMPORT:40.00|USUARI:admin-cobraments',
            $payload['idempotency_key']
        );
        Assert::same('CLAIM_PAYMENT', $payload['allocations'][0]['allocation_type']);

        (new PaymentPayloadValidator())->validate($payload);
    }

    public function testRejectsFallbackIdempotencyWithoutUser(): void
    {
        Assert::throws(
            SifException::class,
            static fn (): array => (new ClaimPaymentPayloadBuilder())->forExistingInvoice(
                '11111111-1111-4111-8111-111111111111',
                [
                    'num_visible' => 'A2026/000321',
                    'amount' => '40',
                    'movement_date' => '2026-06-13',
                ]
            ),
            422
        );
    }
}
