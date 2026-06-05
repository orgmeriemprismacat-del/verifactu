<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Repository\DocumentRepository;
use Prisma\Sif\Repository\IncidentRepository;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class DocumentsAndIncidentsTest
{
    public function testRegisterDocumentStoresImmutableHashOnly(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload());
        $contents = '%PDF-1.4 test invoice';

        $result = (new DocumentRepository())->registerDocument(
            $db,
            $invoice['uuid_factura'],
            'PDF',
            'factures/2026/A2026-000001.pdf',
            $contents
        );

        $row = $db->query('SELECT TIPUS, PATH_FITXER, HASH_FITXER, ESTAT FROM factura_documents')
            ->fetch(\PDO::FETCH_ASSOC);

        Assert::same(hash('sha256', $contents), $result['hash']);
        Assert::same('PDF', $row['TIPUS']);
        Assert::same('factures/2026/A2026-000001.pdf', $row['PATH_FITXER']);
        Assert::same(hash('sha256', $contents), $row['HASH_FITXER']);
        Assert::same('CREATED', $row['ESTAT']);
    }

    public function testOpenIncidentStoresOpenFiscalIssue(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload());

        $result = (new IncidentRepository())->open(
            $db,
            $invoice['uuid_factura'],
            'PDF_NOT_GENERATED',
            'No s ha pogut generar el PDF immutable.'
        );

        $row = $db->query('SELECT UUID_FACTURA, TIPUS_INCIDENCIA, ESTAT, DETAILS FROM errors_verifactu')
            ->fetch(\PDO::FETCH_ASSOC);

        Assert::same(true, $result['ok']);
        Assert::same($invoice['uuid_factura'], $row['UUID_FACTURA']);
        Assert::same('PDF_NOT_GENERATED', $row['TIPUS_INCIDENCIA']);
        Assert::same('OPEN', $row['ESTAT']);
        Assert::same('No s ha pogut generar el PDF immutable.', $row['DETAILS']);
    }
}
