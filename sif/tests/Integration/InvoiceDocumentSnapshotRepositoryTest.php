<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\InvoiceDocumentSnapshotRepository;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class InvoiceDocumentSnapshotRepositoryTest
{
    public function testLoadsFrozenInvoiceFromVerifiedFiscalRecord(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'TEST|UC004|DOCUMENT-SNAPSHOT|VALID',
            ])
        );

        $snapshot = (new InvoiceDocumentSnapshotRepository())->loadFrozen(
            $db,
            $invoice['uuid_factura']
        );

        Assert::same($invoice['uuid_factura'], $snapshot['invoice']['uuid_factura']);
        Assert::same($invoice['num_visible'], $snapshot['invoice']['num_visible']);
        Assert::same('ALTA', $snapshot['record']['record_type']);
        Assert::same(
            $invoice['uuid_factura'],
            $snapshot['payload']['uuid_factura']
        );
        Assert::same(
            $invoice['num_visible'],
            $snapshot['payload']['num_visible']
        );
        Assert::matchesRegularExpression('/^[a-f0-9]{64}$/', $snapshot['record']['hash']);
    }

    public function testRejectsTamperedFrozenFiscalPayload(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'TEST|UC004|DOCUMENT-SNAPSHOT|TAMPER',
            ])
        );

        $db->prepare(
            "UPDATE factura_registres
             SET PAYLOAD_JSON = ?
             WHERE UUID_FACTURA = ? AND TIPUS_REGISTRE = 'ALTA'"
        )->execute([
            json_encode([
                'uuid_factura' => $invoice['uuid_factura'],
                'num_visible' => $invoice['num_visible'],
                'tampered' => true,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $invoice['uuid_factura'],
        ]);

        $exception = Assert::throws(
            SifException::class,
            function () use ($db, $invoice): void {
                (new InvoiceDocumentSnapshotRepository())->loadFrozen(
                    $db,
                    $invoice['uuid_factura']
                );
            },
            409
        );

        Assert::stringContainsString(
            'Immutable fiscal payload hash mismatch',
            $exception->getMessage()
        );
    }
}
