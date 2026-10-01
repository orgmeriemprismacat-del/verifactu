<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Repository\IncidentActionRepository;
use Prisma\Sif\Repository\IncidentRepository;
use Prisma\Sif\Service\IncidentLifecycleService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\ScriptRunner;
use Prisma\Sif\Tests\Support\TestDatabase;

final class IncidentPanelManagerEvidenceScriptTest
{
    public function testResolvedSyntheticManagerFlowIsAcceptedWithoutMutation(): void
    {
        $db = TestDatabase::fresh();
        $service = $this->service($db);
        $actor = $this->manager();

        $opened = $service->open($actor, [
            'source_type' => 'UC008_E2E',
            'source_id' => 'manager-browser-preproduction',
            'type' => 'UC008_E2E_MANAGER',
            'message' => 'Synthetic UC-008 manager E2E evidence incident',
            'severity' => 'LOW',
            'reason_code' => 'E2E_OPEN',
            'idempotency_key' => 'TEST|UC008|MANAGER|OPEN',
        ]);

        $service->assign($actor, $opened['incident_id'], [
            'assignee_id' => 'manager-e2e',
            'reason_code' => 'TRIAGE',
            'idempotency_key' => 'TEST|UC008|MANAGER|ASSIGN',
        ]);
        $service->addEvidence($actor, $opened['incident_id'], [
            'reason_code' => 'E2E_EVIDENCE',
            'idempotency_key' => 'TEST|UC008|MANAGER|EVIDENCE',
            'evidence' => ['reference' => 'UC008-E2E-EVIDENCE'],
        ]);
        $service->resolve($actor, $opened['incident_id'], [
            'reason_code' => 'E2E_VERIFIED',
            'idempotency_key' => 'TEST|UC008|MANAGER|RESOLVE',
            'closure_criteria' => 'Manager panel flow completed successfully.',
            'resolution_notes' => 'Synthetic preproduction-equivalent lifecycle verified.',
            'evidence' => ['reference' => 'UC008-E2E-RESOLVE'],
        ]);

        $incidentCountBefore = (int) $db->query('SELECT COUNT(*) FROM errors_verifactu')->fetchColumn();
        $actionCountBefore = (int) $db->query('SELECT COUNT(*) FROM sif_incident_action')->fetchColumn();

        $result = ScriptRunner::run(
            'scripts/verify-incident-manager-evidence.php',
            [
                'SIF_ENV' => 'test',
                'SIF_INCIDENT_READ_ROLES' => 'AUDITOR_FISCAL,SIF_ADMIN',
                'SIF_INCIDENT_MANAGE_ROLES' => 'SIF_ADMIN',
            ],
            [(string) $opened['incident_id']]
        );

        Assert::same(0, $result['exit_code']);
        Assert::same('', $result['stderr']);
        $json = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);

        Assert::same(true, $json['ok']);
        Assert::same(true, $json['read_only']);
        Assert::same('uc-008-manager-e2e-evidence', $json['scope']);
        Assert::same(true, $json['checks']['synthetic_manager_incident']);
        Assert::same(true, $json['checks']['assign_present']);
        Assert::same(true, $json['checks']['add_evidence_present']);
        Assert::same(true, $json['checks']['resolve_present']);
        Assert::same(true, $json['checks']['assign_manager_role']);
        Assert::same(true, $json['checks']['add_evidence_manager_role']);
        Assert::same(true, $json['checks']['resolve_manager_role']);
        Assert::same(true, $json['checks']['add_evidence_payload_present']);
        Assert::same(true, $json['checks']['resolve_evidence_payload_present']);

        Assert::same(
            $incidentCountBefore,
            (int) $db->query('SELECT COUNT(*) FROM errors_verifactu')->fetchColumn()
        );
        Assert::same(
            $actionCountBefore,
            (int) $db->query('SELECT COUNT(*) FROM sif_incident_action')->fetchColumn()
        );
    }

    public function testResolvedNonSyntheticIncidentCannotCloseManagerEvidenceGate(): void
    {
        $db = TestDatabase::fresh();
        $service = $this->service($db);
        $actor = $this->manager();

        $opened = $service->open($actor, [
            'source_type' => 'TEST',
            'source_id' => 'not-e2e',
            'type' => 'MANUAL_REVIEW',
            'message' => 'Not a dedicated UC-008 E2E incident',
            'severity' => 'LOW',
            'reason_code' => 'TEST_OPEN',
            'idempotency_key' => 'TEST|UC008|MANAGER|NON-SYNTHETIC|OPEN',
        ]);
        $service->assign($actor, $opened['incident_id'], [
            'assignee_id' => 'manager-e2e',
            'reason_code' => 'TRIAGE',
            'idempotency_key' => 'TEST|UC008|MANAGER|NON-SYNTHETIC|ASSIGN',
        ]);
        $service->addEvidence($actor, $opened['incident_id'], [
            'reason_code' => 'EVIDENCE',
            'idempotency_key' => 'TEST|UC008|MANAGER|NON-SYNTHETIC|EVIDENCE',
            'evidence' => ['reference' => 'NOT-E2E'],
        ]);
        $service->resolve($actor, $opened['incident_id'], [
            'reason_code' => 'VERIFIED',
            'idempotency_key' => 'TEST|UC008|MANAGER|NON-SYNTHETIC|RESOLVE',
            'closure_criteria' => 'Resolved.',
            'resolution_notes' => 'Resolved.',
            'evidence' => ['reference' => 'NOT-E2E'],
        ]);

        $result = ScriptRunner::run(
            'scripts/verify-incident-manager-evidence.php',
            [
                'SIF_ENV' => 'test',
                'SIF_INCIDENT_READ_ROLES' => 'AUDITOR_FISCAL,SIF_ADMIN',
                'SIF_INCIDENT_MANAGE_ROLES' => 'SIF_ADMIN',
            ],
            [(string) $opened['incident_id']]
        );

        Assert::same(1, $result['exit_code']);
        $json = json_decode($result['stdout'], true, 512, JSON_THROW_ON_ERROR);
        Assert::same(false, $json['ok']);
        Assert::same(false, $json['checks']['synthetic_manager_incident']);
    }

    public function testManagerEvidenceVerifierSourceRemainsReadOnly(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 2) . '/scripts/verify-incident-manager-evidence.php'
        );
        if ($source === false) {
            Assert::fail('Could not read UC-008 manager evidence verifier');
        }

        Assert::stringContainsString("'read_only' => true", $source);
        Assert::stringContainsString('UC008_E2E_MANAGER', $source);
        Assert::stringContainsString('UC008_E2E', $source);
        Assert::stringContainsString('ADD_EVIDENCE', $source);
        Assert::stringContainsString('RESOLVE', $source);

        foreach ([
            'INSERT INTO',
            'UPDATE errors_verifactu',
            'UPDATE sif_incident_action',
            'DELETE FROM',
            'issueInvoice(',
            'registerPayment(',
        ] as $forbidden) {
            if (stripos($source, $forbidden) !== false) {
                Assert::fail('Manager evidence verifier must remain read-only: ' . $forbidden);
            }
        }
    }

    private function service(\PDO $db): IncidentLifecycleService
    {
        return new IncidentLifecycleService(
            $db,
            new TransactionRunner($db),
            new IncidentRepository(),
            new IncidentActionRepository(),
            ['AUDITOR_FISCAL', 'SIF_ADMIN'],
            ['SIF_ADMIN']
        );
    }

    private function manager(): array
    {
        return [
            'actor_id' => 'manager-e2e',
            'roles' => ['SIF_ADMIN'],
            'request_id' => 'UC008-E2E-MANAGER:TEST',
        ];
    }
}
