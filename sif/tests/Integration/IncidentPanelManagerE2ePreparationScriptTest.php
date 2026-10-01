<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\ScriptRunner;
use Prisma\Sif\Tests\Support\TestDatabase;

final class IncidentPanelManagerE2ePreparationScriptTest
{
    public function testPrepareCreatesOneSyntheticIncidentAndReusesSameRunId(): void
    {
        $db = TestDatabase::fresh();
        $invoiceBefore = (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn();
        $paymentBefore = (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn();

        $env = [
            'SIF_ENV' => 'test',
            'SIF_INCIDENT_READ_ROLES' => 'AUDITOR_FISCAL,SIF_ADMIN',
            'SIF_INCIDENT_MANAGE_ROLES' => 'SIF_ADMIN',
            'SIF_E2E_INCIDENT_MANAGER_ROLE' => 'SIF_ADMIN',
            'SIF_UC008_MANAGER_E2E_PREPARE' => 'YES',
        ];

        $first = ScriptRunner::run(
            'scripts/prepare-incident-manager-e2e.php',
            $env,
            ['CI-UC008-MANAGER-001']
        );
        Assert::same(0, $first['exit_code']);
        Assert::same('', $first['stderr']);
        $firstJson = json_decode($first['stdout'], true, 512, JSON_THROW_ON_ERROR);
        Assert::same(true, $firstJson['ok']);
        Assert::same(true, $firstJson['checks']['manager_role_can_read']);
        Assert::same(false, $firstJson['incident']['reused']);
        Assert::same('UC008_E2E_MANAGER', $firstJson['incident']['type']);
        Assert::same('UC008_E2E', $firstJson['incident']['source_type']);
        Assert::same('OPEN', $firstJson['incident']['status']);

        $second = ScriptRunner::run(
            'scripts/prepare-incident-manager-e2e.php',
            $env,
            ['CI-UC008-MANAGER-001']
        );
        Assert::same(0, $second['exit_code']);
        $secondJson = json_decode($second['stdout'], true, 512, JSON_THROW_ON_ERROR);
        Assert::same(true, $secondJson['ok']);
        Assert::same(true, $secondJson['incident']['reused']);
        Assert::same($firstJson['incident']['incident_id'], $secondJson['incident']['incident_id']);

        Assert::same(
            1,
            (int) $db->query(
                "SELECT COUNT(*) FROM errors_verifactu
                 WHERE TIPUS_INCIDENCIA = 'UC008_E2E_MANAGER'
                   AND SOURCE_TYPE = 'UC008_E2E'"
            )->fetchColumn()
        );
        Assert::same(
            1,
            (int) $db->query(
                "SELECT COUNT(*) FROM sif_incident_action WHERE ACTION_TYPE = 'OPEN'"
            )->fetchColumn()
        );
        Assert::same($invoiceBefore, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same($paymentBefore, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
    }

    public function testPrepareRequiresExplicitAckBeforeMutation(): void
    {
        $db = TestDatabase::fresh();

        $result = ScriptRunner::run(
            'scripts/prepare-incident-manager-e2e.php',
            [
                'SIF_ENV' => 'test',
                'SIF_INCIDENT_READ_ROLES' => 'AUDITOR_FISCAL,SIF_ADMIN',
                'SIF_INCIDENT_MANAGE_ROLES' => 'SIF_ADMIN',
                'SIF_E2E_INCIDENT_MANAGER_ROLE' => 'SIF_ADMIN',
                'SIF_UC008_MANAGER_E2E_PREPARE' => 'NO',
            ],
            ['CI-UC008-MANAGER-NOACK']
        );

        Assert::same(1, $result['exit_code']);
        $json = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);
        Assert::same(false, $json['ok']);
        Assert::same(false, $json['checks']['explicit_mutation_ack']);
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM errors_verifactu')->fetchColumn());
    }

    public function testPrepareRejectsManagerRoleWithoutReadPermission(): void
    {
        $db = TestDatabase::fresh();

        $result = ScriptRunner::run(
            'scripts/prepare-incident-manager-e2e.php',
            [
                'SIF_ENV' => 'test',
                'SIF_INCIDENT_READ_ROLES' => 'AUDITOR_FISCAL',
                'SIF_INCIDENT_MANAGE_ROLES' => 'SIF_ADMIN',
                'SIF_E2E_INCIDENT_MANAGER_ROLE' => 'SIF_ADMIN',
                'SIF_UC008_MANAGER_E2E_PREPARE' => 'YES',
            ],
            ['CI-UC008-MANAGER-NOREAD']
        );

        Assert::same(1, $result['exit_code']);
        $json = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);
        Assert::same(false, $json['ok']);
        Assert::same(false, $json['checks']['manager_role_can_read']);
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM errors_verifactu')->fetchColumn());
    }

    public function testPrepareRefusesProductionBeforeMutation(): void
    {
        $db = TestDatabase::fresh();

        $result = ScriptRunner::run(
            'scripts/prepare-incident-manager-e2e.php',
            [
                'SIF_ENV' => 'production',
                'SIF_INCIDENT_READ_ROLES' => 'AUDITOR_FISCAL,SIF_ADMIN',
                'SIF_INCIDENT_MANAGE_ROLES' => 'SIF_ADMIN',
                'SIF_E2E_INCIDENT_MANAGER_ROLE' => 'SIF_ADMIN',
                'SIF_UC008_MANAGER_E2E_PREPARE' => 'YES',
            ],
            ['CI-UC008-MANAGER-PRODUCTION']
        );

        Assert::same(1, $result['exit_code']);
        $json = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);
        Assert::same(false, $json['ok']);
        Assert::same(false, $json['checks']['environment_not_production']);
        Assert::same(false, $json['production_authorized']);
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM errors_verifactu')->fetchColumn());
    }

    public function testPrepareSourceDoesNotTouchFiscalOrPaymentServices(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/scripts/prepare-incident-manager-e2e.php'
        );
        if ($source === false) {
            Assert::fail('Could not read UC-008 manager E2E preparation script');
        }

        Assert::stringContainsString("'mutation_scope' => 'synthetic_incident_only'", $source);
        Assert::stringContainsString("'fiscal_or_payment_mutation_allowed' => false", $source);
        Assert::stringContainsString('UC008_E2E_MANAGER', $source);
        Assert::stringContainsString('UC008_E2E', $source);

        foreach ([
            'issueInvoice(',
            'registerPayment(',
            'FiscalQueueProcessor',
            'RedsysCallbackWorker',
            'UPDATE factura',
            'UPDATE payment_transaction',
        ] as $forbidden) {
            if (stripos($source, $forbidden) !== false) {
                Assert::fail('Manager E2E preparation must not touch fiscal/payment flows: ' . $forbidden);
            }
        }
    }
}
