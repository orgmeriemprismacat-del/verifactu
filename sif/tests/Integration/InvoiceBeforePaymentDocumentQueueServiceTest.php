<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Repository\DocumentJobRepository;
use Prisma\Sif\Service\InvoiceBeforePaymentDocumentQueueService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class InvoiceBeforePaymentDocumentQueueServiceTest
{
    public function testEnsurePdfIsIdempotentForSameInvoiceAndGeneratorVersion(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'TEST|DOC-JOB|INVOICE-1',
            ])
        );

        $service = new InvoiceBeforePaymentDocumentQueueService(
            new TransactionRunner($db),
            new DocumentJobRepository(),
            'uc004-fiscal-pdf-v1'
        );

        $first = $service->ensurePdf(
            $invoice['uuid_factura'],
            'INTRANET|FACTURA_ABANS_COBRAR|REF:DOC-1'
        );
        $second = $service->ensurePdf(
            $invoice['uuid_factura'],
            'INTRANET|FACTURA_ABANS_COBRAR|REF:DOC-1'
        );

        Assert::same(false, $first['reused']);
        Assert::same(true, $second['reused']);
        Assert::same($first['uuid_job'], $second['uuid_job']);
        Assert::same('PDF', $first['document_type']);
        Assert::same('PENDING', $first['status']);
        Assert::same('uc004-fiscal-pdf-v1', $first['generator_version']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM document_job')->fetchColumn());
    }

    public function testNewGeneratorVersionCreatesNewImmutableJobVersion(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'TEST|DOC-JOB|INVOICE-2',
            ])
        );

        $v1 = new InvoiceBeforePaymentDocumentQueueService(
            new TransactionRunner($db),
            new DocumentJobRepository(),
            'uc004-fiscal-pdf-v1'
        );
        $v2 = new InvoiceBeforePaymentDocumentQueueService(
            new TransactionRunner($db),
            new DocumentJobRepository(),
            'uc004-fiscal-pdf-v2'
        );

        $first = $v1->ensurePdf(
            $invoice['uuid_factura'],
            'INTRANET|FACTURA_ABANS_COBRAR|REF:DOC-2'
        );
        $second = $v2->ensurePdf(
            $invoice['uuid_factura'],
            'INTRANET|FACTURA_ABANS_COBRAR|REF:DOC-2'
        );

        Assert::notSame($first['uuid_job'], $second['uuid_job']);
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM document_job')->fetchColumn());
        Assert::same(
            2,
            (int) $db->query(
                "SELECT COUNT(DISTINCT GENERATOR_VERSION) FROM document_job"
            )->fetchColumn()
        );
    }
}
