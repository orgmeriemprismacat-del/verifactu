<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\InvoicePayloadValidator;
use Prisma\Sif\Service\ManualRectificationPayloadBuilder;
use Prisma\Sif\Tests\Support\Assert;

final class ManualRectificationPayloadBuilderTest
{
    public function testBuildsRectificationInvoicePayloadFromOriginalInvoice(): void
    {
        $payload = (new ManualRectificationPayloadBuilder())->forOriginalInvoice($this->invoice(), [
            'amount' => '-40.00',
            'reason' => 'DEVOLUCIO_PARCIAL',
            'mode' => 'DIFERENCIES',
            'concept' => 'Rectificacio parcial curs',
            'detail' => 'Retorn parcial per baixa',
            'created_by' => 'adam',
        ]);

        (new InvoicePayloadValidator())->validate($payload);

        Assert::same('RECTIFICATIVA|FACT:A2026/000001|MODE:DIFERENCIES|MOTIU:DEVOLUCIO_PARCIAL|IMPORT:-40.00', $payload['idempotency_key']);
        Assert::same('R', $payload['series']);
        Assert::same('R1', $payload['type']);
        Assert::same('INTRANET', $payload['source_channel']);
        Assert::same('adam', $payload['created_by']);
        Assert::same('Client Exemple', $payload['billing']['name']);
        Assert::same('12345678Z', $payload['billing']['nif']);
        Assert::same('-40.00', $payload['totals']['import_base']);
        Assert::same('-40.00', $payload['totals']['taxable_base']);
        Assert::same('-40.00', $payload['totals']['total']);
        Assert::same('Rectificacio parcial curs', $payload['lines'][0]['concept']);
        Assert::same('Retorn parcial per baixa', $payload['lines'][0]['detail']);
        Assert::same('-40.00', $payload['lines'][0]['total']);
        Assert::same('RECTIFICATIVA', $payload['relations'][0]['source_type']);
        Assert::same(100, $payload['relations'][0]['source_id']);
        Assert::same('RECTIFIES', $payload['relations'][0]['relation_type']);
    }

    public function testRejectsZeroRectificationAmount(): void
    {
        Assert::throws(SifException::class, function (): void {
            (new ManualRectificationPayloadBuilder())->forOriginalInvoice($this->invoice(), [
                'amount' => '0',
                'reason' => 'ERROR_DADES',
                'mode' => 'DIFERENCIES',
            ]);
        }, 422);
    }

    private function invoice(): array
    {
        return [
            'ID' => 100,
            'UUID_FACTURA' => 'original-uuid',
            'NUM_VISIBLE' => 'A2026/000001',
            'BILLING_NOM_RAO' => 'Client Exemple',
            'BILLING_NIF_CIF' => '12345678Z',
            'BILLING_ADRECA' => 'Carrer Exemple 1',
            'BILLING_CP' => '08001',
            'BILLING_POBLACIO' => 'Barcelona',
            'BILLING_PROVINCIA' => 'Barcelona',
            'BILLING_PAIS' => 'ES',
            'BILLING_EMAIL' => 'client@example.test',
        ];
    }
}
