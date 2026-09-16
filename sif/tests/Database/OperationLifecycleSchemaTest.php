<?php

namespace Prisma\Sif\Tests\Database;

use Prisma\Sif\Tests\Support\Assert;

final class OperationLifecycleSchemaTest
{
    private function migration(): string
    {
        return (string) file_get_contents(
            dirname(__DIR__, 2) . '/database/migrations/2026_09_16_000005_add_operation_lifecycle_tables.sql'
        );
    }

    public function testMigrationDefinesEveryAuditedLifecycleResponsibility(): void
    {
        $sql = $this->migration();
        foreach ([
            'commercial_operation_line',
            'operation_line_invoice_link',
            'capacity_reservation',
            'discount_evidence',
            'commercial_entitlement',
            'commercial_entitlement_event',
            'enrollment_import_run',
            'enrollment_import_item',
            'master_data_change_request',
            'personal_data_change_request',
            'electronic_invoice_delivery',
            'academic_economic_state_event',
        ] as $table) {
            Assert::matchesRegularExpression(
                '/CREATE TABLE IF NOT EXISTS\s+' . preg_quote($table, '/') . '\s+\(/i',
                $sql
            );
        }
    }

    public function testLinesCapacityEvidenceAndEntitlementsKeepRequiredSnapshots(): void
    {
        $sql = $this->migration();
        foreach ([
            'PARENT_UUID_LINE CHAR(36) NULL',
            'SNAPSHOT_JSON JSON NOT NULL',
            'PRICE_RULE_VERSION VARCHAR(40) NOT NULL',
            'LOCK_VERSION BIGINT NOT NULL DEFAULT 1',
            'CONTENT_HASH CHAR(64) NOT NULL',
            'RETENTION_POLICY_CODE VARCHAR(60) NOT NULL',
            'CODE_HASH CHAR(64) NULL UNIQUE',
            'RULE_SNAPSHOT_JSON JSON NOT NULL',
            'CONSUMED_UUID_OPERATION CHAR(36) NULL',
        ] as $field) {
            Assert::stringContainsString($field, $sql);
        }
    }

    public function testImportsChangesDeliveryAndAcademicEventsAreTraceable(): void
    {
        $sql = $this->migration();
        foreach ([
            'SOURCE_HASH CHAR(64) NOT NULL',
            'ROW_HASH CHAR(64) NOT NULL',
            'AFFECTED_OPEN_OPERATIONS_JSON JSON NOT NULL',
            'PROPAGATION_RESULT_JSON JSON NULL',
            'DELIVERY_PROOF_HASH CHAR(64) NULL',
            'ECONOMIC_SNAPSHOT_HASH CHAR(64) NOT NULL',
            'CORRELATION_ID VARCHAR(100) NOT NULL',
        ] as $field) {
            Assert::stringContainsString($field, $sql);
        }
    }
}
