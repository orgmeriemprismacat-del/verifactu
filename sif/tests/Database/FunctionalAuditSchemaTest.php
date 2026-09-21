<?php

namespace Prisma\Sif\Tests\Database;

use Prisma\Sif\Tests\Support\Assert;

final class FunctionalAuditSchemaTest
{
    private function migration(): string
    {
        return (string) file_get_contents(
            dirname(__DIR__, 2) . '/database/migrations/2026_09_15_000003_add_functional_audit_control.sql'
        );
    }

    public function testMigrationDefinesEveryAgreedPersistenceResponsibility(): void
    {
        $sql = $this->migration();
        $tables = [
            'sif_audit_event',
            'payment_action_event',
            'operational_event',
            'billing_profile_history',
            'course_change_event',
            'enrollment_cancellation_event',
            'factura_registre_control',
            'aeat_submission_attempt',
            'document_job',
            'notification_outbox',
            'notification_delivery_attempt',
            'fiscal_document_access',
            'sif_incident_action',
            'sif_version',
            'sif_declaration',
            'fiscal_export',
            'fiscal_export_access',
            'reconciliation_run',
            'reconciliation_item',
            'backup_restore_evidence',
            'fact_rels_context',
        ];

        foreach ($tables as $table) {
            Assert::matchesRegularExpression('/CREATE TABLE IF NOT EXISTS\s+' . preg_quote($table, '/') . '\s+\(/i', $sql);
        }
    }

    public function testPaymentLedgerCarriesUniversalTraceFields(): void
    {
        $sql = $this->migration();
        foreach ([
            'UUID_EVENT CHAR(36) NOT NULL UNIQUE',
            'UUID_PAYMENT CHAR(36) NULL',
            'PAYMENT_IDEMPOTENCY_KEY VARCHAR(120) NULL',
            'REQUEST_ID VARCHAR(120) NOT NULL',
            'CORRELATION_ID VARCHAR(120) NOT NULL',
            'CAUSATION_ID VARCHAR(120) NULL',
            'ACTION VARCHAR(80) NOT NULL',
            'RESULT VARCHAR(30) NOT NULL',
            'SOURCE_ENVIRONMENT VARCHAR(30) NOT NULL',
            'SOURCE_CHANNEL VARCHAR(30) NOT NULL',
            'BEFORE_HASH CHAR(64) NULL',
            'AFTER_HASH CHAR(64) NULL',
            'CHANGESET_JSON JSON NULL',
        ] as $field) {
            Assert::stringContainsString($field, $sql);
        }
    }

    public function testImmutableRoleTemplateDoesNotGrantUpdateOrDelete(): void
    {
        $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/database/permissions/functional-audit-roles.sql');
        Assert::same(0, preg_match('/GRANT[^;]*(UPDATE|DELETE)[^;]*ON\s+(payment_action_event|sif_audit_event|operational_event)/i', $sql));
        Assert::stringContainsString("GRANT SELECT, INSERT ON payment_action_event TO 'sif_app_writer'", $sql);
    }
}
