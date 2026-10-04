<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Repository\AeatOperationsReadRepository;
use Prisma\Sif\Tests\Support\{Assert, Fixtures, TestDatabase};

final class AeatOperationsReadRepositoryTest
{
    public function testReturnsSummaryQueueListAndDetailWithoutProtectedXml(): void
    {
        $db = TestDatabase::fresh();
        IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'AEAT|OPS|READ',
        ]));
        $repository = new AeatOperationsReadRepository();

        $summary = $repository->summary($db);
        Assert::same(1, $summary['queue']['counts']['PENDING']);
        Assert::same(0, $summary['queue']['counts']['REVIEW']);

        $list = $repository->listQueue($db, 'PENDING', 20);
        Assert::same(1, count($list));
        Assert::same('PENDING', $list[0]['STATUS']);
        Assert::same(null, $list[0]['LAST_ERROR']);

        $detail = $repository->detail($db, (int) $list[0]['ID']);
        Assert::same($list[0]['UUID_FACTURA'], $detail['queue']['UUID_FACTURA']);
        Assert::same([], $detail['attempts']);
        Assert::same([], $detail['incidents']);
        if (array_key_exists('XML_PAYLOAD', $detail['record'] ?? [])) {
            Assert::fail('AEAT operations projection must not expose protected XML payloads.');
        }
        if (array_key_exists('PAYLOAD_JSON', $detail['queue'])) {
            Assert::fail('AEAT operations projection must not expose fiscal queue payloads.');
        }
    }


    public function testDetailExposesOnlyEvidenceReconciliationCapabilityNotDatabaseAnchor(): void
    {
        $db = TestDatabase::fresh();
        IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'AEAT|OPS|EVIDENCE-CAPABILITY',
        ]));

        $queue = $db->query('SELECT * FROM fiscal_queue LIMIT 1')->fetch(\PDO::FETCH_ASSOC);
        $recordId = (int) $db->query('SELECT ID FROM factura_registres LIMIT 1')->fetchColumn();
        $db->prepare(
            "INSERT INTO aeat_submission_attempt
             (UUID_ATTEMPT, FACTURA_REGISTRE_ID, FISCAL_QUEUE_ID, ATTEMPT_NO,
              ENVIRONMENT, ENDPOINT_CODE, REQUEST_HASH, EVIDENCE_ID,
              EVIDENCE_RESPONSE_SHA256, EVIDENCE_HTTP_STATUS, STATUS, STARTED_AT, FINISHED_AT)
             VALUES (?, ?, ?, 1, 'preproduction', 'AEAT_WORKER', ?, ?, ?, 200,
                     'UNCERTAIN', NOW(6), NOW(6))"
        )->execute([
            '55555555-eeee-4fff-8000-000000000009',
            $recordId,
            (int) $queue['ID'],
            str_repeat('b', 64),
            '20261004T010000Z-0123456789abcdef01234567',
            str_repeat('c', 64),
        ]);

        $detail = (new AeatOperationsReadRepository())->detail($db, (int) $queue['ID']);
        Assert::same(1, count($detail['attempts']));
        Assert::same(1, (int) $detail['attempts'][0]['EVIDENCE_RECONCILABLE']);
        if (array_key_exists('EVIDENCE_RESPONSE_SHA256', $detail['attempts'][0])
            || array_key_exists('EVIDENCE_HTTP_STATUS', $detail['attempts'][0])
        ) {
            Assert::fail('AEAT panel must not expose the private response anchor details.');
        }
    }

    public function testRejectsUnknownStatusAndMissingQueueItem(): void
    {
        $db = TestDatabase::fresh();
        $repository = new AeatOperationsReadRepository();

        Assert::throws(
            \Prisma\Sif\Exception\SifException::class,
            fn () => $repository->listQueue($db, 'UNKNOWN', 20),
            422
        );
        Assert::throws(
            \Prisma\Sif\Exception\SifException::class,
            fn () => $repository->detail($db, 999999),
            404
        );
    }
}
