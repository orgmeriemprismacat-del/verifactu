<?php

namespace Prisma\Sif\Tests\Database;

use Prisma\Sif\Tests\Support\Assert;

final class CrossSystemControlSchemaTest
{
    private function migration(): string
    {
        return (string) file_get_contents(
            dirname(__DIR__, 2) . '/database/migrations/2026_09_16_000006_add_cross_system_control_tables.sql'
        );
    }

    public function testMigrationDefinesAllThirdAuditResponsibilities(): void
    {
        $sql = $this->migration();
        foreach ([
            'communication_consent',
            'communication_consent_event',
            'external_identity_link',
            'identity_conflict_case',
            'edition_lifecycle_event',
            'edition_operation_impact',
            'address_validation_case',
            'academic_reconciliation_item',
        ] as $table) {
            Assert::matchesRegularExpression(
                '/CREATE TABLE IF NOT EXISTS\s+' . preg_quote($table, '/') . '\s+\(/i',
                $sql
            );
        }
    }

    public function testConsentIdentityAndAddressEvidenceIsVersioned(): void
    {
        $sql = $this->migration();
        foreach ([
            'NOTICE_VERSION VARCHAR(40) NOT NULL',
            'EVIDENCE_HASH CHAR(64) NOT NULL',
            'SNAPSHOT_HASH CHAR(64) NOT NULL',
            'CANDIDATE_IDENTITIES_JSON JSON NOT NULL',
            'ORIGINAL_ADDRESS_HASH CHAR(64) NOT NULL',
            'NORMALIZATION_RULE_VERSION VARCHAR(40) NULL',
        ] as $field) {
            Assert::stringContainsString($field, $sql);
        }
    }

    public function testEditionAndAcademicReconciliationKeepImpactAndResolution(): void
    {
        $sql = $this->migration();
        foreach ([
            'AFFECTED_OPERATION_COUNT INT NOT NULL DEFAULT 0',
            'REQUIRED_ACTION VARCHAR(40) NOT NULL',
            'ECONOMIC_DECISION VARCHAR(40) NULL',
            'FISCAL_DECISION VARCHAR(40) NULL',
            'SOURCE_SNAPSHOT_HASH CHAR(64) NOT NULL',
            'TARGET_SNAPSHOT_HASH CHAR(64) NOT NULL',
            'RESOLUTION_STATUS VARCHAR(30) NOT NULL',
        ] as $field) {
            Assert::stringContainsString($field, $sql);
        }
    }
}
