<?php

namespace Prisma\Sif\Tests\Support;

final class Fixtures
{
    public static function invoicePayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'idempotency_key' => 'REDSYS|CURS|IDPAG:123|ORDER:999999',
            'series' => 'A',
            'year' => 2026,
            'type' => 'F1',
            'source_channel' => 'REDSYS',
            'created_by' => 'test-runner',
            'billing' => [
                'name' => 'Client Exemple',
                'nif' => '12345678Z',
                'address' => 'Carrer Exemple 1',
                'cp' => '08001',
                'city' => 'Barcelona',
                'province' => 'Barcelona',
                'country' => 'ES',
                'email' => 'client@example.test',
            ],
            'totals' => [
                'import_base' => '120.00',
                'discount' => '0.00',
                'taxable_base' => '120.00',
                'iva_regim' => 'EXEMPT',
                'iva_pct' => '0.00',
                'iva_import' => '0.00',
                'total' => '120.00',
            ],
            'lines' => [[
                'concept' => 'Curs individual',
                'detail' => 'Curs de prova',
                'quantity' => '1.00',
                'unit_price' => '120.00',
                'base' => '120.00',
                'import_base' => '120.00',
                'discount_amount' => '0.00',
                'taxable_base' => '120.00',
                'iva_regim' => 'EXEMPT',
                'iva_pct' => '0.00',
                'iva_import' => '0.00',
                'total' => '120.00',
                'source_type' => 'INSCRIPCIO',
                'source_id' => 10,
            ]],
            'relations' => [[
                'source_type' => 'INSCRIPCIO',
                'source_id' => 10,
                'factura_relacionada' => 500,
                'idpag' => 123,
                'ds_order' => '999999',
                'visible_alumne' => 1,
            ]],
        ], $overrides);
    }
}
