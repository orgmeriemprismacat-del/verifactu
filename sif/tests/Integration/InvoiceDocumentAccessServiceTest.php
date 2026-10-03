<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\DocumentAccessRepository;
use Prisma\Sif\Repository\DocumentRepository;
use Prisma\Sif\Repository\FiscalDocumentAccessRepository;
use Prisma\Sif\Repository\InvoiceReadRepository;
use Prisma\Sif\Service\InvoiceDocumentAccessService;
use Prisma\Sif\Service\PrivateDocumentStore;
use Prisma\Sif\Service\ResolvedDocumentAuthorizationPolicy;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class InvoiceDocumentAccessServiceTest
{
    public function testVerifiedDocumentIsReturnedAndAllowedAccessIsAudited(): void
    {
        [$db, $invoice, $id, $root, $bytes] = $this->fixture();
        try {
            $result = $this->service($db, $root)->download($this->actor($invoice['uuid_factura'], 'FULL', 'uc007-ok'), $id);
            Assert::same($bytes, $result['bytes']);
            Assert::same($id, $result['document']['id']);
            $this->assertLastAudit($db, 'ALLOWED', 'OK');
        } finally {
            $this->cleanup($root);
        }
    }

    public function testMinimalScopeIsDeniedAndAudited(): void
    {
        [$db, $invoice, $id, $root] = $this->fixture();
        try {
            Assert::throws(
                SifException::class,
                fn () => $this->service($db, $root)->download($this->actor($invoice['uuid_factura'], 'MINIMAL', 'uc007-denied'), $id),
                403
            );
            $this->assertLastAudit($db, 'DENIED', 'INVOICE_SCOPE');
        } finally {
            $this->cleanup($root);
        }
    }

    public function testHashMismatchFailsClosedAndIsAudited(): void
    {
        [$db, $invoice, $id, $root] = $this->fixture();
        try {
            file_put_contents($root . '/invoice.pdf', '%PDF tampered');
            Assert::throws(
                SifException::class,
                fn () => $this->service($db, $root)->download($this->actor($invoice['uuid_factura'], 'FULL', 'uc007-hash'), $id),
                409
            );
            $this->assertLastAudit($db, 'FAILED', 'HASH_MISMATCH');
        } finally {
            $this->cleanup($root);
        }
    }

    public function testMissingBytesFailClosedAndAreAudited(): void
    {
        [$db, $invoice, $id, $root] = $this->fixture();
        try {
            unlink($root . '/invoice.pdf');
            Assert::throws(
                SifException::class,
                fn () => $this->service($db, $root)->download($this->actor($invoice['uuid_factura'], 'FULL', 'uc007-missing'), $id),
                503
            );
            $this->assertLastAudit($db, 'FAILED', 'STORAGE_UNAVAILABLE');
        } finally {
            $this->cleanup($root);
        }
    }

    private function fixture(): array
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'UC007|DOC|' . bin2hex(random_bytes(4)),
        ]));
        $root = sys_get_temp_dir() . '/uc007-' . bin2hex(random_bytes(8));
        mkdir($root, 0700, true);
        $bytes = '%PDF-1.4 immutable UC007';
        file_put_contents($root . '/invoice.pdf', $bytes);
        (new DocumentRepository())->registerDocument($db, $invoice['uuid_factura'], 'PDF', 'invoice.pdf', $bytes);
        $id = (int) $db->query('SELECT ID FROM factura_documents ORDER BY ID DESC LIMIT 1')->fetchColumn();
        return [$db, $invoice, $id, $root, $bytes];
    }

    private function service(\PDO $db, string $root): InvoiceDocumentAccessService
    {
        return new InvoiceDocumentAccessService(
            $db,
            new DocumentAccessRepository(),
            new InvoiceReadRepository(),
            new ResolvedDocumentAuthorizationPolicy(),
            new PrivateDocumentStore($root),
            new FiscalDocumentAccessRepository(new UuidGenerator())
        );
    }

    private function actor(string $uuid, string $projection, string $requestId): array
    {
        return [
            'actor_id' => 'operator-uc007',
            'roles' => ['FACTURACIO'],
            'source_channel' => 'INTRANET',
            'request_id' => $requestId,
            'invoice_scope' => ['invoices' => [$uuid => $projection]],
        ];
    }

    private function assertLastAudit(\PDO $db, string $result, string $reason): void
    {
        $row = $db->query('SELECT RESULT, REASON_CODE FROM fiscal_document_access ORDER BY ID DESC LIMIT 1')->fetch(\PDO::FETCH_ASSOC);
        Assert::same($result, $row['RESULT']);
        Assert::same($reason, $row['REASON_CODE']);
    }

    private function cleanup(string $root): void
    {
        foreach (glob($root . '/*') ?: [] as $file) {
            if (is_file($file)) unlink($file);
        }
        if (is_dir($root)) rmdir($root);
    }
}
