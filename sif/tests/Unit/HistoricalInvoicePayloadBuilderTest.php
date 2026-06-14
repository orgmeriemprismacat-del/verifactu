<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\HistoricalInvoicePayloadBuilder;
use Prisma\Sif\Tests\Support\Assert;

final class HistoricalInvoicePayloadBuilderTest
{
    public function testBuildsHistoricalInvoicePayloadFromVisibleNumber(): void
    {
        $payload = (new HistoricalInvoicePayloadBuilder())->build($this->input([
            'num_visible' => 'A2024/000123',
            'legacy_id' => 9123,
            'factura_relacionada' => 700,
        ]));

        Assert::same('HISTORIC|FACT:A2024/000123', $payload['idempotency_key']);
        Assert::same('A', $payload['series']);
        Assert::same(2024, $payload['year']);
        Assert::same(123, $payload['num_seq']);
        Assert::same('A2024/000123', $payload['num_visible']);
        Assert::same('MIGRACIO', $payload['source_channel']);
        Assert::same('HISTORIC_WEB_FACTURES', $payload['source_type']);
        Assert::same('HISTORICAL', $payload['invoice_status']);
        Assert::same('NO_VERIFACTU', $payload['aeat_status']);
        Assert::same('PAID', $payload['payment_status']);
        Assert::same('HISTORIC_WEB_FACTURES', $payload['relations'][0]['source_type']);
        Assert::same(9123, $payload['relations'][0]['source_id']);
        Assert::same('HISTORIC_LINK', $payload['relations'][0]['relation_type']);
        Assert::same(700, $payload['relations'][0]['factura_relacionada']);
    }

    public function testPreservesExplicitIdempotencyAndDocumentMetadata(): void
    {
        $payload = (new HistoricalInvoicePayloadBuilder())->build($this->input([
            'num_visible' => 'R2023/000004',
            'idempotency_key' => 'HISTORIC|LEGACY-ID:4',
            'document' => [
                'type' => 'PDF',
                'path' => '/historic/factures/R2023-000004.pdf',
                'hash' => str_repeat('a', 64),
            ],
        ]));

        Assert::same('HISTORIC|LEGACY-ID:4', $payload['idempotency_key']);
        Assert::same('R', $payload['series']);
        Assert::same(2023, $payload['year']);
        Assert::same(4, $payload['num_seq']);
        Assert::same('PDF', $payload['document']['type']);
        Assert::same(str_repeat('a', 64), $payload['document']['hash']);
    }

    public function testRejectsHistoricalInvoiceWithoutVisibleNumber(): void
    {
        Assert::throws(SifException::class, function (): void {
            (new HistoricalInvoicePayloadBuilder())->build($this->input([
                'num_visible' => '',
            ]));
        }, 422);
    }

    private function input(array $overrides = []): array
    {
        return array_replace_recursive([
            'num_visible' => 'A2024/000123',
            'issue_date' => '2024-03-15 10:00:00',
            'payment_status' => 'PAID',
            'billing' => [
                'name' => 'Client Historic',
                'nif' => '12345678Z',
                'address' => 'Carrer Historic 1',
                'cp' => '08001',
                'city' => 'Barcelona',
                'province' => 'Barcelona',
                'country' => 'ES',
                'email' => 'historic@example.test',
            ],
            'totals' => [
                'import_base' => '100.00',
                'discount' => '0.00',
                'taxable_base' => '100.00',
                'iva_regim' => 'EXEMPT',
                'iva_pct' => '0.00',
                'iva_import' => '0.00',
                'total' => '100.00',
            ],
            'lines' => [[
                'concept' => 'Factura historica',
                'detail' => 'Servei migrat',
                'quantity' => '1.00',
                'unit_price' => '100.00',
                'base' => '100.00',
                'import_base' => '100.00',
                'discount_amount' => '0.00',
                'taxable_base' => '100.00',
                'iva_regim' => 'EXEMPT',
                'iva_pct' => '0.00',
                'iva_import' => '0.00',
                'total' => '100.00',
                'source_type' => 'HISTORIC_WEB_FACTURES',
                'source_id' => 9123,
            ]],
        ], $overrides);
    }
}
