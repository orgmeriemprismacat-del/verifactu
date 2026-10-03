<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Aeat\XmlCodec;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\{FiscalQueueRepository, IncidentRepository};
use Prisma\Sif\Service\AeatReviewReconciliationService;
use Prisma\Sif\Tests\Support\{AeatFixtures, Assert, Fixtures, TestDatabase};

final class AeatReviewReconciliationServiceTest
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

    public function testReconcilesPersistedRemoteResultWithoutSecondTransportCall(): void
    {
        $db = TestDatabase::fresh();
        $invoice = $this->issue($db, 'AEAT|RECONCILE|OK');

        $queue = $db->query('SELECT * FROM fiscal_queue LIMIT 1')->fetch(\PDO::FETCH_ASSOC);
        $record = $db->query('SELECT ID FROM factura_registres LIMIT 1')->fetchColumn();
        $payload = json_decode((string) $queue['PAYLOAD_JSON'], true);
        $requestHash = hash('sha256', (new XmlCodec())->request($payload['aeat']));
        $db->exec("UPDATE fiscal_queue SET STATUS = 'REVIEW', LAST_ERROR = 'remote result pending local commit'");
        $db->prepare(
            "INSERT INTO aeat_submission_attempt
             (UUID_ATTEMPT, FACTURA_REGISTRE_ID, FISCAL_QUEUE_ID, ATTEMPT_NO, ENVIRONMENT,
              ENDPOINT_CODE, REQUEST_HASH, RESPONSE_CSV, RESPONSE_JSON, STATUS, STARTED_AT, FINISHED_AT)
             VALUES (?, ?, ?, 1, 'preproduction', 'AEAT_WORKER', ?, ?, ?, 'ACCEPTED', NOW(6), NOW(6))"
        )->execute([
            '11111111-2222-4333-8444-555555555555',
            (int) $record,
            (int) $queue['ID'],
            $requestHash,
            'CSV-RECONCILED',
            json_encode(['csv' => 'CSV-RECONCILED', 'flow_wait_seconds' => 60]),
        ]);
        (new IncidentRepository())->open(
            $db,
            $invoice['uuid_factura'],
            'AEAT_REMOTE_RESULT_PENDING_LOCAL_COMMIT',
            'Queue ID ' . $queue['ID'] . ': synthetic reconciliation test'
        );

        $result = (new AeatReviewReconciliationService(
            new TransactionRunner($db),
            new FiscalQueueRepository(),
            new IncidentRepository()
        ))->reconcile(
            (int) $queue['ID'],
            '11111111-2222-4333-8444-555555555555',
            'tester'
        );

        Assert::same(true, $result['ok']);
        Assert::same(true, $result['reconciled_without_resend']);
        Assert::same('SENT', $db->query('SELECT STATUS FROM fiscal_queue')->fetchColumn());
        Assert::same('ACCEPTED', $db->query('SELECT ESTAT_AEAT FROM factura_registres')->fetchColumn());
        Assert::same('ACCEPTED', $db->query('SELECT ESTAT_AEAT FROM factura')->fetchColumn());
        Assert::same('CSV-RECONCILED', $db->query('SELECT AEAT_CSV FROM fiscal_queue')->fetchColumn());
        Assert::same(
            'RESOLVED',
            $db->query(
                "SELECT ESTAT FROM errors_verifactu
                 WHERE TIPUS_INCIDENCIA = 'AEAT_REMOTE_RESULT_PENDING_LOCAL_COMMIT'"
            )->fetchColumn()
        );
        Assert::same(
            0,
            (int) $db->query(
                "SELECT COUNT(*) FROM errors_verifactu WHERE TIPUS_INCIDENCIA = 'AEAT_RECONCILED'"
            )->fetchColumn()
        );
        Assert::same(
            1,
            (int) $db->query(
                "SELECT COUNT(*) FROM operational_event
                 WHERE OPERATION_TYPE = 'AEAT_RECONCILE'
                   AND REASON_CODE = 'AEAT_RECONCILED'
                   AND STATUS = 'COMPLETED'"
            )->fetchColumn()
        );
    }

    public function testDoesNotReconcileUncertainAttempt(): void
    {
        $db = TestDatabase::fresh();
        $this->issue($db, 'AEAT|RECONCILE|UNCERTAIN');

        $queue = $db->query('SELECT * FROM fiscal_queue LIMIT 1')->fetch(\PDO::FETCH_ASSOC);
        $record = $db->query('SELECT ID FROM factura_registres LIMIT 1')->fetchColumn();
        $db->exec("UPDATE fiscal_queue SET STATUS = 'REVIEW'");
        $db->prepare(
            "INSERT INTO aeat_submission_attempt
             (UUID_ATTEMPT, FACTURA_REGISTRE_ID, FISCAL_QUEUE_ID, ATTEMPT_NO, ENVIRONMENT,
              ENDPOINT_CODE, REQUEST_HASH, STATUS, STARTED_AT, FINISHED_AT)
             VALUES (?, ?, ?, 1, 'preproduction', 'AEAT_WORKER', ?, 'UNCERTAIN', NOW(6), NOW(6))"
        )->execute([
            'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
            (int) $record,
            (int) $queue['ID'],
            str_repeat('b', 64),
        ]);

        Assert::throws(
            SifException::class,
            fn () => (new AeatReviewReconciliationService(
                new TransactionRunner($db),
                new FiscalQueueRepository(),
                new IncidentRepository()
            ))->reconcile(
                (int) $queue['ID'],
                'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
                'tester'
            ),
            409
        );

        Assert::same('REVIEW', $db->query('SELECT STATUS FROM fiscal_queue')->fetchColumn());
        Assert::same('PENDING', $db->query('SELECT ESTAT_AEAT FROM factura_registres')->fetchColumn());
    }

    public function testRejectsTerminalAttemptWhoseRequestHashDoesNotMatchSnapshot(): void
    {
        $db = TestDatabase::fresh();
        $this->issue($db, 'AEAT|RECONCILE|HASH-MISMATCH');

        $queue = $db->query('SELECT * FROM fiscal_queue LIMIT 1')->fetch(\PDO::FETCH_ASSOC);
        $record = $db->query('SELECT ID FROM factura_registres LIMIT 1')->fetchColumn();
        $db->exec("UPDATE fiscal_queue SET STATUS = 'REVIEW'");
        $db->prepare(
            "INSERT INTO aeat_submission_attempt
             (UUID_ATTEMPT, FACTURA_REGISTRE_ID, FISCAL_QUEUE_ID, ATTEMPT_NO, ENVIRONMENT,
              ENDPOINT_CODE, REQUEST_HASH, RESPONSE_JSON, STATUS, STARTED_AT, FINISHED_AT)
             VALUES (?, ?, ?, 1, 'preproduction', 'AEAT_WORKER', ?, ?, 'ACCEPTED', NOW(6), NOW(6))"
        )->execute([
            'bbbbbbbb-cccc-4ddd-8eee-ffffffffffff',
            (int) $record,
            (int) $queue['ID'],
            str_repeat('f', 64),
            json_encode(['csv' => 'WRONG-HASH']),
        ]);

        Assert::throws(
            SifException::class,
            fn () => (new AeatReviewReconciliationService(
                new TransactionRunner($db),
                new FiscalQueueRepository(),
                new IncidentRepository()
            ))->reconcile(
                (int) $queue['ID'],
                'bbbbbbbb-cccc-4ddd-8eee-ffffffffffff',
                'tester'
            ),
            409
        );

        Assert::same('REVIEW', $db->query('SELECT STATUS FROM fiscal_queue')->fetchColumn());
    }


    public function testRejectsMalformedAttemptUuidBeforeDatabaseReconciliation(): void
    {
        $db = TestDatabase::fresh();

        Assert::throws(
            SifException::class,
            fn () => (new AeatReviewReconciliationService(
                new TransactionRunner($db),
                new FiscalQueueRepository(),
                new IncidentRepository()
            ))->reconcile(
                1,
                '------------------------------------',
                'tester'
            ),
            422
        );
    }

}
