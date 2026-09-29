<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\IncidentActionRepository;
use Prisma\Sif\Repository\IncidentRepository;
use Prisma\Sif\Service\IncidentLifecycleService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class IncidentLifecycleTest
{
    public function testDetailedOpenReturnsStableIdentityAndReusesSameIdempotencyKey(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload());
        $repository = new IncidentRepository();

        $payload = [
            'uuid_factura' => $invoice['uuid_factura'],
            'resource_type' => 'INVOICE',
            'resource_id' => $invoice['uuid_factura'],
            'source_type' => 'TEST',
            'source_id' => 'incident-lifecycle-test',
            'type' => 'PDF_NOT_GENERATED',
            'message' => 'Document job failed',
            'severity' => 'HIGH',
            'correlation_id' => 'TEST:INCIDENT:1',
            'idempotency_key' => 'TEST|INCIDENT|PDF|1',
            'reason_code' => 'DOCUMENT_JOB_FAILED',
        ];

        $first = $repository->openDetailed($db, $payload);
        $second = $repository->openDetailed($db, $payload);

        Assert::same(false, $first['reused']);
        Assert::same(true, $second['reused']);
        Assert::same($first['incident_id'], $second['incident_id']);
        Assert::same($first['uuid_incident'], $second['uuid_incident']);
        Assert::same(
            1,
            (int) $db->query("SELECT COUNT(*) FROM errors_verifactu WHERE IDEMPOTENCY_KEY = 'TEST|INCIDENT|PDF|1'")
                ->fetchColumn()
        );
    }


    public function testDetailedOpenRejectsChangedPayloadForSameIdempotencyKey(): void
    {
        $db = TestDatabase::fresh();
        $repository = new IncidentRepository();

        $base = [
            'resource_type' => 'TEST_CASE',
            'resource_id' => 'UC08-IDEMPOTENCY',
            'source_type' => 'TEST',
            'source_id' => 'suite',
            'type' => 'MANUAL_REVIEW',
            'message' => 'Original incident payload',
            'severity' => 'HIGH',
            'idempotency_key' => 'TEST|INCIDENT|PAYLOAD|CONFLICT',
            'reason_code' => 'TEST_CONFLICT',
        ];

        $repository->openDetailed($db, $base);

        Assert::throws(
            SifException::class,
            fn () => $repository->openDetailed($db, array_merge($base, [
                'message' => 'Changed incident payload',
            ])),
            409
        );

        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM errors_verifactu')->fetchColumn());
    }

    public function testDetailedOpenRejectsUnknownInvoiceInsteadOfPersistingOrphanReference(): void
    {
        $db = TestDatabase::fresh();

        Assert::throws(
            SifException::class,
            static fn () => (new IncidentRepository())->openDetailed($db, [
                'uuid_factura' => '00000000-0000-0000-0000-000000000000',
                'resource_type' => 'INVOICE',
                'resource_id' => '00000000-0000-0000-0000-000000000000',
                'type' => 'UNKNOWN_INVOICE',
                'message' => 'Must fail closed',
            ]),
            404
        );

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM errors_verifactu')->fetchColumn());
    }

    public function testLifecycleAssignsAddsEvidenceAndResolvesWithImmutableActionHistory(): void
    {
        $db = TestDatabase::fresh();
        $service = $this->service($db);
        $actor = $this->manager();

        $opened = $service->open($actor, [
            'type' => 'MANUAL_REVIEW',
            'message' => 'Review required',
            'severity' => 'HIGH',
            'resource_type' => 'TEST_CASE',
            'resource_id' => 'UC08',
            'reason_code' => 'TEST_OPEN',
            'idempotency_key' => 'TEST|UC08|OPEN',
            'evidence' => ['source' => 'integration-test'],
        ]);

        $assigned = $service->assign($actor, $opened['incident_id'], [
            'assignee_id' => 'operator-2',
            'reason_code' => 'TRIAGE',
            'idempotency_key' => 'TEST|UC08|ASSIGN',
        ]);

        $evidence = $service->addEvidence($actor, $opened['incident_id'], [
            'reason_code' => 'VERIFICATION',
            'idempotency_key' => 'TEST|UC08|EVIDENCE',
            'evidence' => ['check' => 'PASS'],
        ]);

        $resolved = $service->resolve($actor, $opened['incident_id'], [
            'reason_code' => 'VERIFIED_FIXED',
            'idempotency_key' => 'TEST|UC08|RESOLVE',
            'closure_criteria' => 'La prova es repeteix i passa.',
            'resolution_notes' => 'Correccio verificada.',
            'evidence' => ['test' => 'PASS', 'reference' => 'UC08-TEST'],
        ]);

        Assert::same('IN_PROGRESS', $assigned['status']);
        Assert::same('IN_PROGRESS', $evidence['status']);
        Assert::same('RESOLVED', $resolved['status']);

        $row = $db->query('SELECT ESTAT, ASSIGNED_TO, RESOLVED_AT, CLOSURE_CRITERIA FROM errors_verifactu')
            ->fetch(\PDO::FETCH_ASSOC);
        Assert::same('RESOLVED', $row['ESTAT']);
        Assert::same('operator-2', $row['ASSIGNED_TO']);
        Assert::same(false, empty($row['RESOLVED_AT']));
        Assert::same('La prova es repeteix i passa.', $row['CLOSURE_CRITERIA']);
        Assert::same(4, (int) $db->query('SELECT COUNT(*) FROM sif_incident_action')->fetchColumn());
    }


    public function testLifecycleRetriesAreIdempotentAfterStateChanges(): void
    {
        $db = TestDatabase::fresh();
        $service = $this->service($db);
        $actor = $this->manager();

        $opened = $service->open($actor, [
            'type' => 'RETRY_TEST',
            'message' => 'Lifecycle retry test',
            'reason_code' => 'TEST_OPEN',
            'idempotency_key' => 'TEST|UC08|RETRY|OPEN',
        ]);

        $assignPayload = [
            'assignee_id' => 'operator-2',
            'reason_code' => 'TRIAGE',
            'idempotency_key' => 'TEST|UC08|RETRY|ASSIGN',
        ];
        $firstAssign = $service->assign($actor, $opened['incident_id'], $assignPayload);
        $secondAssign = $service->assign($actor, $opened['incident_id'], $assignPayload);

        Assert::same(false, $firstAssign['reused']);
        Assert::same(true, $secondAssign['reused']);

        $resolvePayload = [
            'reason_code' => 'VERIFIED_FIXED',
            'idempotency_key' => 'TEST|UC08|RETRY|RESOLVE',
            'closure_criteria' => 'Repeated verification passes.',
            'resolution_notes' => 'Resolved once.',
            'evidence' => ['test' => 'PASS'],
        ];
        $firstResolve = $service->resolve($actor, $opened['incident_id'], $resolvePayload);
        $secondResolve = $service->resolve($actor, $opened['incident_id'], $resolvePayload);

        Assert::same(false, $firstResolve['reused']);
        Assert::same(true, $secondResolve['reused']);
        Assert::same(
            3,
            (int) $db->query('SELECT COUNT(*) FROM sif_incident_action')->fetchColumn()
        );
    }

    public function testActionIdempotencyRejectsDifferentAssigneeForSameKey(): void
    {
        $db = TestDatabase::fresh();
        $service = $this->service($db);
        $actor = $this->manager();

        $opened = $service->open($actor, [
            'type' => 'ASSIGN_CONFLICT',
            'message' => 'Assignment conflict test',
            'reason_code' => 'TEST_OPEN',
            'idempotency_key' => 'TEST|UC08|ASSIGN-CONFLICT|OPEN',
        ]);

        $service->assign($actor, $opened['incident_id'], [
            'assignee_id' => 'operator-2',
            'reason_code' => 'TRIAGE',
            'idempotency_key' => 'TEST|UC08|ASSIGN-CONFLICT',
        ]);

        Assert::throws(
            SifException::class,
            fn () => $service->assign($actor, $opened['incident_id'], [
                'assignee_id' => 'operator-3',
                'reason_code' => 'TRIAGE',
                'idempotency_key' => 'TEST|UC08|ASSIGN-CONFLICT',
            ]),
            409
        );

        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM sif_incident_action')->fetchColumn());
    }

    public function testReadOnlyActorCanViewButCannotMutateIncident(): void
    {
        $db = TestDatabase::fresh();
        $service = $this->service($db);
        $opened = $service->open($this->manager(), [
            'type' => 'READ_ONLY_TEST',
            'message' => 'Read only policy',
            'reason_code' => 'TEST_OPEN',
            'idempotency_key' => 'TEST|UC08|READONLY',
        ]);

        $readOnly = [
            'actor_id' => 'auditor-test',
            'roles' => ['AUDITOR_FISCAL'],
            'request_id' => 'test-request-auditor',
        ];

        $view = $service->view($readOnly, $opened['incident_id']);
        Assert::same($opened['incident_id'], (int) $view['incident']['ID']);

        Assert::throws(
            SifException::class,
            fn () => $service->assign($readOnly, $opened['incident_id'], [
                'assignee_id' => 'auditor-test',
                'reason_code' => 'NOT_ALLOWED',
                'idempotency_key' => 'TEST|UC08|DENIED',
            ]),
            403
        );
    }

    public function testResolveRequiresClosureEvidence(): void
    {
        $db = TestDatabase::fresh();
        $service = $this->service($db);
        $opened = $service->open($this->manager(), [
            'type' => 'MISSING_EVIDENCE_TEST',
            'message' => 'Cannot close without evidence',
            'reason_code' => 'TEST_OPEN',
            'idempotency_key' => 'TEST|UC08|NO-EVIDENCE',
        ]);

        Assert::throws(
            SifException::class,
            fn () => $service->resolve($this->manager(), $opened['incident_id'], [
                'reason_code' => 'VERIFY',
                'idempotency_key' => 'TEST|UC08|NO-EVIDENCE|RESOLVE',
                'closure_criteria' => 'Must have proof.',
                'resolution_notes' => 'No proof supplied.',
            ]),
            422
        );
    }

    private function service(\PDO $db): IncidentLifecycleService
    {
        return new IncidentLifecycleService(
            $db,
            new TransactionRunner($db),
            new IncidentRepository(),
            new IncidentActionRepository(),
            ['AUDITOR_FISCAL'],
            ['SIF_ADMIN', 'RESPONSABLE_TECNICA']
        );
    }

    private function manager(): array
    {
        return [
            'actor_id' => 'operator-test',
            'roles' => ['SIF_ADMIN'],
            'request_id' => 'test-request-manager',
        ];
    }
}
