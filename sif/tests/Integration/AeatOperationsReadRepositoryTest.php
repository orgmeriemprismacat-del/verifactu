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
