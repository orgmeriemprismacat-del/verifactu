<?php

namespace Prisma\Sif\Tests\Database;

use Prisma\Sif\Tests\Support\Assert;

final class SifSchemaTest
{
    public function testCoreMigrationDefinesExpectedTablesAndColumns(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 2) . '/database/migrations/2026_06_02_000001_create_sif_core.sql');

        $tables = [
            'factura',
            'factura_linia',
            'factura_registres',
            'factura_rectificacio',
            'factura_documents',
            'fiscal_sequence',
            'fiscal_chain_state',
            'fiscal_queue',
            'payment_transaction',
            'payment_allocation',
            'fact_rels',
            'redsys_notifications',
            'credit_balance',
            'errors_verifactu',
        ];

        foreach ($tables as $table) {
            Assert::matchesRegularExpression('/CREATE TABLE IF NOT EXISTS\s+' . preg_quote($table, '/') . '\s+\(/i', $sql);
        }

        foreach ([
            'UUID_FACTURA CHAR(36) NOT NULL UNIQUE',
            'IDEMPOTENCY_KEY VARCHAR(100) NOT NULL UNIQUE',
            'NUM_VISIBLE VARCHAR(30) NOT NULL UNIQUE',
            'ESTAT_COBRAMENT VARCHAR(20) NOT NULL DEFAULT',
            'PAYLOAD_JSON JSON NOT NULL',
            'UUID_PAYMENT CHAR(36) NOT NULL UNIQUE',
            'IMPORT_ASSIGNAT DECIMAL(12,2) NOT NULL',
            'DS_ORDER VARCHAR(40) NULL',
        ] as $column) {
            Assert::stringContainsString($column, $sql);
        }
    }
}
