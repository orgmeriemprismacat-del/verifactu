<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Aeat\{EvidenceStore, XmlCodec};
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\{
    AeatSubmissionAttemptRepository,
    FiscalQueueRepository,
    IncidentRepository
};
use Prisma\Sif\Service\AeatEvidenceReconciliationService;
use Prisma\Sif\Tests\Support\{AeatFixtures, Assert, Fixtures, TestDatabase};

final class AeatEvidenceReconciliationServiceTest
{
    private function issue(\PDO $db, string $key): array
    {
        $snapshot = AeatFixtures::snapshot();
        $fields = $snapshot['record'];
        $fields['Desglose'] = ['DetalleDesglose' => [[
            'Impuesto' => '01',
            'ClaveRegimen' => '01',
            'OperacionExenta' => 'E1',
            'BaseImponibleOimporteNoSujeto' => '120.00',
        ]]];

        return IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => $key,
            'aeat_fields' => $fields,
            'aeat_header' => $snapshot['header'],
        ]));
    }

    public function testReconcilesUncertainAttemptFromVerifiedPrivateEvidenceWithoutResend(): void
    {
        $db = TestDatabase::fresh();
        $invoice = $this->issue($db, 'AEAT|EVIDENCE|RECONCILE|OK');

        $queue = $db->query('SELECT * FROM fiscal_queue LIMIT 1')->fetch(\PDO::FETCH_ASSOC);
        $recordId = (int) $db->query('SELECT ID FROM factura_registres LIMIT 1')->fetchColumn();
        $payload = json_decode((string) $queue['PAYLOAD_JSON'], true);
        $requestXml = (new XmlCodec())->request($payload['aeat']);
        $responseXml = AeatFixtures::response($payload['aeat'], 'Correcto');

        [$dir, $evidenceId] = $this->createEvidence($requestXml, $responseXml);
        try {
            $attemptUuid = 'cccccccc-dddd-4eee-8fff-000000000001';
            $db->exec("UPDATE fiscal_queue SET STATUS = 'REVIEW', LAST_ERROR = 'uncertain delivery'");
            $db->prepare(
                "INSERT INTO aeat_submission_attempt
                 (UUID_ATTEMPT, FACTURA_REGISTRE_ID, FISCAL_QUEUE_ID, ATTEMPT_NO,
                  ENVIRONMENT, ENDPOINT_CODE, REQUEST_HASH, EVIDENCE_ID, STATUS,
                  ERROR_DETAIL, STARTED_AT, FINISHED_AT)
                 VALUES (?, ?, ?, 1, 'preproduction', 'AEAT_WORKER', ?, ?, 'UNCERTAIN',
                         'synthetic uncertain evidence', NOW(6), NOW(6))"
            )->execute([
                $attemptUuid,
                $recordId,
                (int) $queue['ID'],
                hash('sha256', $requestXml),
                $evidenceId,
            ]);

            (new IncidentRepository())->openDetailed($db, [
                'uuid_factura' => $invoice['uuid_factura'],
                'resource_type' => 'FISCAL_QUEUE',
                'resource_id' => (string) $queue['ID'],
                'source_type' => 'AEAT_WORKER',
                'source_id' => (string) $queue['ID'],
                'type' => 'AEAT_DELIVERY_UNCERTAIN',
                'message' => 'Queue ID ' . $queue['ID'] . ': synthetic uncertain evidence',
                'severity' => 'HIGH',
                'correlation_id' => 'FISCAL_QUEUE:' . $queue['ID'],
                'idempotency_key' => 'AEAT_DELIVERY_UNCERTAIN|QUEUE:' . $queue['ID'],
                'reason_code' => 'AEAT_DELIVERY_UNCERTAIN',
            ]);

            $result = (new AeatEvidenceReconciliationService(
                new TransactionRunner($db),
                new FiscalQueueRepository(),
                new IncidentRepository(),
                new AeatSubmissionAttemptRepository(),
                $dir
            ))->reconcile(
                (int) $queue['ID'],
                $attemptUuid,
                'tester'
            );

            Assert::same(true, $result['ok']);
            Assert::same(true, $result['reconciled_from_verified_evidence']);
            Assert::same(true, $result['reconciled_without_resend']);
            Assert::same('ACCEPTED', $result['aeat_status']);
            Assert::same('SENT', $db->query('SELECT STATUS FROM fiscal_queue')->fetchColumn());
            Assert::same('ACCEPTED', $db->query('SELECT ESTAT_AEAT FROM factura_registres')->fetchColumn());
            Assert::same('ACCEPTED', $db->query('SELECT STATUS FROM aeat_submission_attempt')->fetchColumn());
            Assert::same($evidenceId, $db->query('SELECT EVIDENCE_ID FROM aeat_submission_attempt')->fetchColumn());
            Assert::same(
                'RESOLVED',
                $db->query(
                    "SELECT ESTAT FROM errors_verifactu
                     WHERE TIPUS_INCIDENCIA = 'AEAT_DELIVERY_UNCERTAIN'"
                )->fetchColumn()
            );
            Assert::same(
                1,
                (int) $db->query(
                    "SELECT COUNT(*) FROM operational_event
                     WHERE OPERATION_TYPE = 'AEAT_RECONCILE'
                       AND REASON_CODE = 'AEAT_EVIDENCE_RECONCILED'
                       AND STATUS = 'COMPLETED'"
                )->fetchColumn()
            );
            $stored = json_decode(
                (string) $db->query('SELECT RESPONSE_JSON FROM aeat_submission_attempt')->fetchColumn(),
                true
            );
            Assert::same($evidenceId, $stored['evidence_id'] ?? null);
            Assert::matchesRegularExpression(
                '/^[a-f0-9]{64}$/',
                (string) ($stored['evidence_response_sha256'] ?? '')
            );
        } finally {
            $this->removeEvidence($dir, $evidenceId);
        }
    }

    public function testRejectsEvidenceWhoseRequestDoesNotMatchImmutableQueueSnapshot(): void
    {
        $db = TestDatabase::fresh();
        $this->issue($db, 'AEAT|EVIDENCE|RECONCILE|MISMATCH');

        $queue = $db->query('SELECT * FROM fiscal_queue LIMIT 1')->fetch(\PDO::FETCH_ASSOC);
        $recordId = (int) $db->query('SELECT ID FROM factura_registres LIMIT 1')->fetchColumn();
        $payload = json_decode((string) $queue['PAYLOAD_JSON'], true);
        $requestXml = (new XmlCodec())->request($payload['aeat']);
        $responseXml = AeatFixtures::response($payload['aeat'], 'Correcto');

        [$dir, $evidenceId] = $this->createEvidence('<different-request/>', $responseXml);
        try {
            $attemptUuid = 'dddddddd-eeee-4fff-8000-000000000002';
            $db->exec("UPDATE fiscal_queue SET STATUS = 'REVIEW'");
            $db->prepare(
                "INSERT INTO aeat_submission_attempt
                 (UUID_ATTEMPT, FACTURA_REGISTRE_ID, FISCAL_QUEUE_ID, ATTEMPT_NO,
                  ENVIRONMENT, ENDPOINT_CODE, REQUEST_HASH, EVIDENCE_ID, STATUS,
                  STARTED_AT, FINISHED_AT)
                 VALUES (?, ?, ?, 1, 'preproduction', 'AEAT_WORKER', ?, ?, 'UNCERTAIN',
                         NOW(6), NOW(6))"
            )->execute([
                $attemptUuid,
                $recordId,
                (int) $queue['ID'],
                hash('sha256', $requestXml),
                $evidenceId,
            ]);

            Assert::throws(
                SifException::class,
                fn () => (new AeatEvidenceReconciliationService(
                    new TransactionRunner($db),
                    new FiscalQueueRepository(),
                    new IncidentRepository(),
                    new AeatSubmissionAttemptRepository(),
                    $dir
                ))->reconcile(
                    (int) $queue['ID'],
                    $attemptUuid,
                    'tester'
                ),
                409
            );

            Assert::same('REVIEW', $db->query('SELECT STATUS FROM fiscal_queue')->fetchColumn());
            Assert::same('UNCERTAIN', $db->query('SELECT STATUS FROM aeat_submission_attempt')->fetchColumn());
            Assert::same('PENDING', $db->query('SELECT ESTAT_AEAT FROM factura_registres')->fetchColumn());
        } finally {
            $this->removeEvidence($dir, $evidenceId);
        }
    }


    public function testDoesNotGuessOutcomeForStartedAttemptEvenWithValidEvidence(): void
    {
        $db = TestDatabase::fresh();
        $this->issue($db, 'AEAT|EVIDENCE|STARTED|FAIL-CLOSED');

        $queue = $db->query('SELECT * FROM fiscal_queue LIMIT 1')->fetch(\PDO::FETCH_ASSOC);
        $recordId = (int) $db->query('SELECT ID FROM factura_registres LIMIT 1')->fetchColumn();
        $payload = json_decode((string) $queue['PAYLOAD_JSON'], true);
        $requestXml = (new XmlCodec())->request($payload['aeat']);
        $responseXml = AeatFixtures::response($payload['aeat'], 'Correcto');

        [$dir, $evidenceId] = $this->createEvidence($requestXml, $responseXml);
        try {
            $attemptUuid = 'eeeeeeee-ffff-4000-8111-000000000003';
            $db->exec("UPDATE fiscal_queue SET STATUS = 'REVIEW'");
            $db->prepare(
                "INSERT INTO aeat_submission_attempt
                 (UUID_ATTEMPT, FACTURA_REGISTRE_ID, FISCAL_QUEUE_ID, ATTEMPT_NO,
                  ENVIRONMENT, ENDPOINT_CODE, REQUEST_HASH, EVIDENCE_ID, STATUS, STARTED_AT)
                 VALUES (?, ?, ?, 1, 'preproduction', 'AEAT_WORKER', ?, ?, 'STARTED', NOW(6))"
            )->execute([
                $attemptUuid,
                $recordId,
                (int) $queue['ID'],
                hash('sha256', $requestXml),
                $evidenceId,
            ]);

            Assert::throws(
                SifException::class,
                fn () => (new AeatEvidenceReconciliationService(
                    new TransactionRunner($db),
                    new FiscalQueueRepository(),
                    new IncidentRepository(),
                    new AeatSubmissionAttemptRepository(),
                    $dir
                ))->reconcile(
                    (int) $queue['ID'],
                    $attemptUuid,
                    'tester'
                ),
                409
            );

            Assert::same('REVIEW', $db->query('SELECT STATUS FROM fiscal_queue')->fetchColumn());
            Assert::same('STARTED', $db->query('SELECT STATUS FROM aeat_submission_attempt')->fetchColumn());
            Assert::same('PENDING', $db->query('SELECT ESTAT_AEAT FROM factura_registres')->fetchColumn());
        } finally {
            $this->removeEvidence($dir, $evidenceId);
        }
    }

    private function createEvidence(string $requestXml, string $responseXml): array
    {
        $dir = sys_get_temp_dir() . '/aeat-evidence-reconcile-' . bin2hex(random_bytes(12));
        mkdir($dir, 0700);
        $store = new EvidenceStore($dir);
        $id = $store->begin($requestXml, ['environment' => 'offline-test']);
        $store->response($id, $responseXml, 200);

        return [$dir, $id];
    }

    private function removeEvidence(string $dir, string $id): void
    {
        foreach (['request.xml', 'request.json', 'response.xml', 'response.json', 'failure.json'] as $name) {
            if (is_file($dir . '/' . $id . '/' . $name)) {
                unlink($dir . '/' . $id . '/' . $name);
            }
        }
        if (is_dir($dir . '/' . $id)) {
            rmdir($dir . '/' . $id);
        }
        if (is_dir($dir)) {
            rmdir($dir);
        }
    }
}
