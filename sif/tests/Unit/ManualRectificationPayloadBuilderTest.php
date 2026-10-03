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

    public function testPreservesOriginalExemptionReasonForAmountOnlyRectification(): void
    {
        $payload = (new ManualRectificationPayloadBuilder())->forOriginalInvoice($this->invoice([
            'IVA_REGIM' => 'EXEMPT',
            'IVA_PCT' => '0.00',
            'IVA_IMPORT' => '0.00',
            'CAUSA_EXEMPCIO_NO_SUBJECTA' => 'E1',
        ]), [
            'amount' => '-40.00',
            'reason' => 'DEVOLUCIO_PARCIAL',
            'mode' => 'DIFERENCIES',
        ]);

        Assert::same('EXEMPT', $payload['totals']['iva_regim']);
        Assert::same('0.00', $payload['totals']['iva_pct']);
        Assert::same('0.00', $payload['totals']['iva_import']);
        Assert::same('E1', $payload['totals']['exemption_reason']);
        Assert::same('E1', $payload['lines'][0]['exemption_reason']);
    }

    public function testRejectsTaxedOriginalWhenOnlyAmbiguousAmountIsProvided(): void
    {
        Assert::throws(SifException::class, function (): void {
            (new ManualRectificationPayloadBuilder())->forOriginalInvoice($this->invoice([
                'IVA_REGIM' => 'GENERAL',
                'IVA_PCT' => '21.00',
                'IVA_IMPORT' => '21.00',
            ]), [
                'amount' => '-121.00',
                'reason' => 'AJUST_IMPORT',
                'mode' => 'DIFERENCIES',
            ]);
        }, 422);
    }

    public function testAcceptsExplicitFiscalBlockForTaxedRectification(): void
    {
        $payload = (new ManualRectificationPayloadBuilder())->forOriginalInvoice($this->invoice([
            'IVA_REGIM' => 'GENERAL',
            'IVA_PCT' => '21.00',
            'IVA_IMPORT' => '21.00',
        ]), [
            'amount' => '-121.00',
            'reason' => 'AJUST_IMPORT',
            'mode' => 'DIFERENCIES',
            'fiscal' => [
                'import_base' => '-100.00',
                'taxable_base' => '-100.00',
                'iva_regim' => 'GENERAL',
                'iva_pct' => '21.00',
                'iva_import' => '-21.00',
                'total' => '-121.00',
            ],
        ]);

        (new InvoicePayloadValidator())->validate($payload);

        Assert::same('-100.00', $payload['totals']['import_base']);
        Assert::same('-100.00', $payload['totals']['taxable_base']);
        Assert::same('GENERAL', $payload['totals']['iva_regim']);
        Assert::same('21.00', $payload['totals']['iva_pct']);
        Assert::same('-21.00', $payload['totals']['iva_import']);
        Assert::same('-121.00', $payload['totals']['total']);
        Assert::same('-21.00', $payload['lines'][0]['iva_import']);
    }

    public function testRejectsInconsistentExplicitFiscalBlock(): void
    {
        Assert::throws(SifException::class, function (): void {
            (new ManualRectificationPayloadBuilder())->forOriginalInvoice($this->invoice(), [
                'amount' => '-120.00',
                'reason' => 'AJUST_IMPORT',
                'mode' => 'DIFERENCIES',
                'fiscal' => [
                    'import_base' => '-100.00',
                    'taxable_base' => '-100.00',
                    'iva_regim' => 'GENERAL',
                    'iva_pct' => '21.00',
                    'iva_import' => '-21.00',
                    'total' => '-120.00',
                ],
            ]);
        }, 422);
    }

    public function testSubstitutionCanCarryCorrectedBillingSnapshot(): void
    {
        $payload = (new ManualRectificationPayloadBuilder())->forOriginalInvoice($this->invoice(), [
            'amount' => '-120.00',
            'reason' => 'CANVI_RECEPTOR',
            'mode' => 'SUBSTITUCIO',
            'billing' => [
                'nom_rao' => 'Empresa Correcta SL',
                'nif_cif' => 'B12345678',
                'adreca' => 'Carrer Fiscal 2',
                'poblacio' => 'Girona',
            ],
        ]);

        Assert::same('Empresa Correcta SL', $payload['billing']['name']);
        Assert::same('B12345678', $payload['billing']['nif']);
        Assert::same('Carrer Fiscal 2', $payload['billing']['address']);
        Assert::same('Girona', $payload['billing']['city']);
        Assert::same('08001', $payload['billing']['cp']);
    }

    public function testDifferencesModeRejectsBillingMutation(): void
    {
        Assert::throws(SifException::class, function (): void {
            (new ManualRectificationPayloadBuilder())->forOriginalInvoice($this->invoice(), [
                'amount' => '-40.00',
                'reason' => 'AJUST_IMPORT',
                'mode' => 'DIFERENCIES',
                'billing' => [
                    'name' => 'Un altre receptor',
                ],
            ]);
        }, 422);
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

    private function invoice(array $overrides = []): array
    {
        return array_replace([
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
            'IVA_REGIM' => 'EXEMPT',
            'IVA_PCT' => '0.00',
            'IVA_IMPORT' => '0.00',
            'CAUSA_EXEMPCIO_NO_SUBJECTA' => null,
        ], $overrides);
    }
}
