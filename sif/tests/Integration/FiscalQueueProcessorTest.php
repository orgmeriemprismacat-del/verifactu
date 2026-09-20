<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Contract\AeatTransport;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Repository\FiscalQueueRepository;
use Prisma\Sif\Service\FiscalQueueProcessor;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class FiscalQueueProcessorTest
{
    public function testPersistsAcceptedResponseAndRequestXml(): void
    {
        $db = TestDatabase::fresh();
        IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload());
        $transport = new class implements AeatTransport {
            public function send(array $fiscalPayload): array
            {
                return [
                    'status' => 'ACCEPTED',
                    'request_xml' => '<test-request fiscal-order="' . $fiscalPayload['fiscal_order'] . '"/>',
                    'response' => ['csv' => 'TEST-CSV-001', 'status' => 'Accepted'],
                ];
            }
        };

        $result = $this->processor($db, $transport)->processNext();

        Assert::same(true, $result['ok']);
        Assert::same(true, $result['processed']);
        Assert::same('ACCEPTED', $result['aeat_status']);
        Assert::same('SENT', (string) $db->query('SELECT STATUS FROM fiscal_queue')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT ATTEMPTS FROM fiscal_queue')->fetchColumn());
        Assert::same('ACCEPTED', (string) $db->query('SELECT ESTAT_AEAT FROM factura')->fetchColumn());

        $record = $db->query('SELECT ESTAT_AEAT, XML_PAYLOAD, AEAT_RESPONSE_JSON FROM factura_registres')
            ->fetch(\PDO::FETCH_ASSOC);
        Assert::same('ACCEPTED', $record['ESTAT_AEAT']);
        Assert::stringContainsString('<test-request', $record['XML_PAYLOAD']);
        Assert::same('TEST-CSV-001', json_decode($record['AEAT_RESPONSE_JSON'], true)['csv']);

        $empty = $this->processor($db, $transport)->processNext();
        Assert::same(false, $empty['processed']);
    }

    public function testRetriesAndMovesPermanentTransportFailureToDeadLetter(): void
    {
        $db = TestDatabase::fresh();
        IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'TRANSPORT|FAILURE',
        ]));
        $transport = new class implements AeatTransport {
            public function send(array $fiscalPayload): array
            {
                throw new \RuntimeException('AEAT test transport unavailable');
            }
        };
        $processor = $this->processor($db, $transport);

        $first = $processor->processNext();
        $this->makeRetryDue($db);
        $second = $processor->processNext();
        $this->makeRetryDue($db);
        $third = $processor->processNext();

        Assert::same('RETRY', $first['queue_status']);
        Assert::same('RETRY', $second['queue_status']);
        Assert::same('DEAD_LETTER', $third['queue_status']);
        Assert::notSame($first['next_retry_at'], $second['next_retry_at']);
        Assert::same(null, $third['next_retry_at']);
        Assert::same(3, $third['attempts']);
        Assert::same('DEAD_LETTER', (string) $db->query('SELECT STATUS FROM fiscal_queue')->fetchColumn());
        Assert::same('ERROR', (string) $db->query('SELECT ESTAT_AEAT FROM factura')->fetchColumn());
        Assert::same('ERROR', (string) $db->query('SELECT ESTAT_AEAT FROM factura_registres')->fetchColumn());
        Assert::stringContainsString(
            'AEAT test transport unavailable',
            (string) $db->query('SELECT LAST_ERROR FROM fiscal_queue')->fetchColumn()
        );
    }

    public function testProcessesBoundedBatchAndPersistsAcceptedWithErrors(): void
    {
        $db = TestDatabase::fresh();
        IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'BATCH|ONE',
        ]));
        IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'BATCH|TWO',
        ]));
        $transport = new class implements AeatTransport {
            public function send(array $fiscalPayload): array
            {
                return [
                    'status' => 'ACCEPTED_WITH_ERRORS',
                    'response' => ['warning' => 'TEST_WARNING'],
                ];
            }
        };
        $processor = $this->processor($db, $transport);

        $firstBatch = $processor->processBatch(1);
        $secondBatch = $processor->processBatch(100);

        Assert::same(1, $firstBatch['processed']);
        Assert::same(1, $secondBatch['processed']);
        Assert::same(2, (int) $db->query("SELECT COUNT(*) FROM fiscal_queue WHERE STATUS = 'SENT'")->fetchColumn());
        Assert::same(
            2,
            (int) $db->query("SELECT COUNT(*) FROM factura_registres WHERE ESTAT_AEAT = 'ACCEPTED_WITH_ERRORS'")
                ->fetchColumn()
        );
    }

    public function testRecoversOnlyExpiredProcessingLocks(): void
    {
        $db = TestDatabase::fresh();
        IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'STALE|LOCK',
        ]));
        $db->exec("UPDATE fiscal_queue SET STATUS = 'PROCESSING', LOCKED_AT = '2026-09-16 10:00:00'");
        $transport = new class implements AeatTransport {
            public function send(array $fiscalPayload): array
            {
                return ['status' => 'ACCEPTED', 'response' => []];
            }
        };
        $processor = $this->processor($db, $transport);

        $notRecovered = $processor->recoverStaleLocks(
            3600,
            new \DateTimeImmutable('2026-09-16 10:30:00')
        );
        $recovered = $processor->recoverStaleLocks(
            3600,
            new \DateTimeImmutable('2026-09-16 12:00:01')
        );

        Assert::same(0, $notRecovered);
        Assert::same(1, $recovered);
        Assert::same('RETRY', (string) $db->query('SELECT STATUS FROM fiscal_queue')->fetchColumn());
        Assert::same(null, $db->query('SELECT LOCKED_AT FROM fiscal_queue')->fetchColumn());
        Assert::same('Recovered stale worker lock', (string) $db->query('SELECT LAST_ERROR FROM fiscal_queue')->fetchColumn());
    }

    public function testBatchStopsAfterFirstTransportFailure(): void
    {
        $db = TestDatabase::fresh();
        IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'BATCH|FAIL|ONCE',
        ]));
        $transport = new class implements AeatTransport {
            public function send(array $fiscalPayload): array
            {
                throw new \RuntimeException('Temporary AEAT failure');
            }
        };

        $result = $this->processor($db, $transport)->processBatch(100);

        Assert::same(false, $result['ok']);
        Assert::same(1, $result['processed']);
        Assert::same(1, (int) $db->query('SELECT ATTEMPTS FROM fiscal_queue')->fetchColumn());
        Assert::same('RETRY', (string) $db->query('SELECT STATUS FROM fiscal_queue')->fetchColumn());
        Assert::same($result['results'][0]['next_retry_at'], (string) $db->query('SELECT NEXT_RETRY_AT FROM fiscal_queue')->fetchColumn());
        Assert::same(false, $this->processor($db, $transport)->processNext()['processed']);
    }

    private function processor(\PDO $db, AeatTransport $transport): FiscalQueueProcessor
    {
        return new FiscalQueueProcessor(
            new TransactionRunner($db),
            new FiscalQueueRepository(),
            $transport,
            3
        );
    }

    private function makeRetryDue(\PDO $db): void
    {
        $db->exec("UPDATE fiscal_queue SET NEXT_RETRY_AT = '2000-01-01 00:00:00' WHERE STATUS = 'RETRY'");
    }
}
