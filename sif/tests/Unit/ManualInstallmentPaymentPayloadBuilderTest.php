<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\ManualInstallmentPaymentPayloadBuilder;
use Prisma\Sif\Service\PaymentPayloadValidator;
use Prisma\Sif\Tests\Support\Assert;

final class ManualInstallmentPaymentPayloadBuilderTest
{
    public function testBuildsManualInstallmentPayloadForExistingInvoice(): void
    {
        $payload = (new ManualInstallmentPaymentPayloadBuilder())->forExistingInvoice(
            '11111111-1111-4111-8111-111111111111',
            [
                'amount' => '40',
                'movement_date' => '2026-06-12 10:30:00',
                'id_insc' => 77,
                'user' => 'adam',
                'notes' => 'Primera fraccio',
            ]
        );

        Assert::same('MANUAL|FRACCIO|ID_INSC:77|DATA:2026-06-12|IMPORT:40.00|USUARI:adam', $payload['idempotency_key']);
        Assert::same('CHARGE', $payload['movement_type']);
        Assert::same('MANUAL', $payload['method']);
        Assert::same('INTRANET', $payload['source_channel']);
        Assert::same('40.00', $payload['amount']);
        Assert::same('2026-06-12 10:30:00', $payload['movement_date']);
        Assert::same('FRACCIO|ID_INSC:77|USUARI:adam', $payload['provider_ref']);
        Assert::same('Primera fraccio', $payload['notes']);
        Assert::same('11111111-1111-4111-8111-111111111111', $payload['allocations'][0]['uuid_factura']);
        Assert::same('40.00', $payload['allocations'][0]['amount']);
        Assert::same('INSTALLMENT_PAYMENT', $payload['allocations'][0]['allocation_type']);

        (new PaymentPayloadValidator())->validate($payload);
    }

    public function testRejectsMissingInstallmentInscription(): void
    {
        Assert::throws(SifException::class, static function (): void {
            (new ManualInstallmentPaymentPayloadBuilder())->forExistingInvoice(
                '11111111-1111-4111-8111-111111111111',
                [
                    'amount' => '40',
                    'movement_date' => '2026-06-12',
                    'user' => 'adam',
                ]
            );
        }, 422);
    }
}
