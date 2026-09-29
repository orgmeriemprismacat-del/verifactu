<?php

namespace Prisma\Sif\Tests\Database;

use Prisma\Sif\Tests\Support\Assert;

final class IncidentLifecycleSchemaTest
{
    private function migration(): string
    {
        return (string) file_get_contents(
            dirname(__DIR__, 2) . '/database/migrations/2026_09_29_000010_add_incident_lifecycle.sql'
        );
    }

    public function testIncidentLifecycleMigrationExtendsIncidentIdentityAndTraceability(): void
    {
        $sql = $this->migration();

        foreach ([
            'UUID_INCIDENT CHAR(36) NULL',
            'UUID_PAYMENT CHAR(36) NULL',
            'RESOURCE_TYPE VARCHAR(40) NULL',
            'RESOURCE_ID VARCHAR(120) NULL',
            'SEVERITY VARCHAR(20) NOT NULL',
            'ASSIGNED_TO VARCHAR(120) NULL',
            'CORRELATION_ID VARCHAR(120) NULL',
            'IDEMPOTENCY_KEY VARCHAR(140) NULL',
            'RESOLVED_AT DATETIME(6) NULL',
            'RESOLUTION_NOTES TEXT NULL',
            'CLOSURE_CRITERIA TEXT NULL',
        ] as $field) {
            Assert::stringContainsString($field, $sql);
        }

        Assert::stringContainsString('uq_errors_verifactu_idempotency', $sql);
        Assert::stringContainsString('uq_sif_incident_action_idempotency', $sql);
    }
}
