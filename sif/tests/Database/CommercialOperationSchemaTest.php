<?php

namespace Prisma\Sif\Tests\Database;

use Prisma\Sif\Tests\Support\Assert;

final class CommercialOperationSchemaTest
{
    private function migration(): string
    {
        return (string) file_get_contents(
            dirname(__DIR__, 2) . '/database/migrations/2026_09_16_000004_add_commercial_operation_and_fiscal_fields.sql'
        );
    }

    public function testMigrationDefinesTheMissingCommercialResponsibilities(): void
    {
        $sql = $this->migration();
        foreach ([
            'commercial_operation',
            'commercial_operation_party',
            'discount_validation',
            'payment_link',
        ] as $table) {
            Assert::matchesRegularExpression(
                '/CREATE TABLE IF NOT EXISTS\s+' . preg_quote($table, '/') . '\s+\(/i',
                $sql
            );
        }
    }

    public function testCommercialOperationSeparatesClassificationSnapshotsAndLinks(): void
    {
        $sql = $this->migration();
        foreach ([
            'CLASSIFICATION VARCHAR(40) NOT NULL',
            'CLASSIFICATION_REASON VARCHAR(80) NOT NULL',
            'PRICE_SNAPSHOT_JSON JSON NOT NULL',
            'TAX_SNAPSHOT_JSON JSON NOT NULL',
            'UUID_INTENT CHAR(36) NULL',
            'UUID_FACTURA CHAR(36) NULL',
            'UUID_PAYMENT CHAR(36) NULL',
            'TOKEN_HASH CHAR(64) NOT NULL UNIQUE',
            'EVIDENCE_HASH CHAR(64) NULL',
        ] as $field) {
            Assert::stringContainsString($field, $sql);
        }
    }

    public function testMigrationAddsDocumentedFiscalData(): void
    {
        $sql = $this->migration();
        foreach ([
            'EMISSOR_NIF VARCHAR(20) NULL',
            'CAUSA_EXEMPCIO_NO_SUBJECTA VARCHAR(80) NULL',
            'INVERSIO_SUBJECTE_PASSIU TINYINT(1)',
            'RECARREC_EQUIVALENCIA_IMPORT DECIMAL(12,2) NULL',
            'GENERATED_TIMEZONE VARCHAR(64) NULL',
            'SIF_CODE VARCHAR(80) NULL',
            'SIF_VERSION VARCHAR(40) NULL',
            'PRODUCTOR_NIF VARCHAR(20) NULL',
            'QR_URL VARCHAR(500) NULL',
            'QR_DATA_HASH CHAR(64) NULL',
            'VERIFACTU_TEXT VARCHAR(255) NULL',
            'NEXT_RETRY_AT DATETIME NULL',
        ] as $field) {
            Assert::stringContainsString($field, $sql);
        }
    }
}
