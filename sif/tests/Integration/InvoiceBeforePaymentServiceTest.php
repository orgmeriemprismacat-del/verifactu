<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Contract\InvoiceBeforePaymentDocumentQueueInterface;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\DocumentJobRepository;
use Prisma\Sif\Service\InvoiceBeforePaymentDocumentQueueService;
use Prisma\Sif\Service\InvoiceBeforePaymentPayloadBuilder;
use Prisma\Sif\Service\InvoiceBeforePaymentService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class InvoiceBeforePaymentServiceTest
{
    public function testIssuesInvoiceBeforePaymentWithoutCreatingPayment(): void
    {
        $db = TestDatabase::fresh();
        $service = $this->serviceWithDocuments($db);
        $input = Fixtures::invoicePayload([
            'idempotency_key' => 'INTRANET|FACTURA_ABANS_COBRAR|REF:PRE900',
            'source_channel' => 'INTRANET',
            'created_by' => 'gestio-factura-abans-cobrar',
        ]);

        $first = $service->issueBeforePayment($input);
        $second = $service->issueBeforePayment($input);

        Assert::same(true, $first['ok']);
        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_factura'], $second['uuid_factura']);
        Assert::same('PENDING', $first['document_status']);
        Assert::same('PENDING', $second['document_status']);
        Assert::same(false, $first['document_job']['reused']);
        Assert::same(true, $second['document_job']['reused']);
        Assert::same($first['document_job']['uuid_job'], $second['document_job']['uuid_job']);
        Assert::same(1, (int) $db->query('SELECT EMESA_ABANS_COBRAMENT FROM factura')->fetchColumn());
        Assert::same('PENDING', (string) $db->query('SELECT ESTAT_COBRAMENT FROM factura')->fetchColumn());
        Assert::same('INTRANET', (string) $db->query('SELECT SOURCE_CHANNEL FROM factura')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM fiscal_queue')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM fact_rels')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM invoice_before_payment_coverage')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM operational_event')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM document_job')->fetchColumn());

        $event = $db->query(
            'SELECT OPERATION_TYPE, UUID_FACTURA, FISCAL_IMPACT, ECONOMIC_IMPACT,
                    STATUS, REASON_CODE, ACTOR_ID, SOURCE_CHANNEL, CORRELATION_ID
             FROM operational_event'
        )->fetch(\PDO::FETCH_ASSOC);

        Assert::same('ISSUE_INVOICE_BEFORE_PAYMENT', $event['OPERATION_TYPE']);
        Assert::same($first['uuid_factura'], $event['UUID_FACTURA']);
        Assert::same('INVOICE_ISSUED', $event['FISCAL_IMPACT']);
        Assert::same('PENDING_PAYMENT', $event['ECONOMIC_IMPACT']);
        Assert::same('COMPLETED', $event['STATUS']);
        Assert::same('UC004_CONFIRMED', $event['REASON_CODE']);
        Assert::same('gestio-factura-abans-cobrar', $event['ACTOR_ID']);
        Assert::same('INTRANET', $event['SOURCE_CHANNEL']);
        Assert::same(
            'UC004:' . hash('sha256', 'INTRANET|FACTURA_ABANS_COBRAR|REF:PRE900'),
            $event['CORRELATION_ID']
        );
    }

    public function testBuilderDerivesIdempotencyAndForcesInvoiceBeforePaymentFlags(): void
    {
        $payload = (new InvoiceBeforePaymentPayloadBuilder())->build(Fixtures::invoicePayload([
            'idempotency_key' => '',
            'source_channel' => 'REDSYS',
            'created_by' => '',
            'reference' => 'PRE 900',
            'relations' => [[
                'source_type' => 'inscripcio',
                'source_id' => '900',
                'relation_type' => 'ANY_CLIENT_VALUE',
                'factura_relacionada' => 900,
            ]],
        ]));

        Assert::same('INTRANET|FACTURA_ABANS_COBRAR|REF:PRE_900', $payload['idempotency_key']);
        Assert::same('INTRANET', $payload['source_channel']);
        Assert::same('intranet-factura-abans-cobrar', $payload['created_by']);
        Assert::same(1, $payload['emesa_abans_cobrament']);
        Assert::same('INSCRIPCIO', $payload['relations'][0]['source_type']);
        Assert::same(900, $payload['relations'][0]['source_id']);
        Assert::same('ORIGIN', $payload['relations'][0]['relation_type']);
        Assert::same('E1', $payload['totals']['exemption_reason']);
        Assert::same('E1', $payload['lines'][0]['exemption_reason']);
    }

    public function testRejectsDifferentExemptionReasonForCurrentTrainingFlow(): void
    {
        $builder = new InvoiceBeforePaymentPayloadBuilder();

        Assert::throws(SifException::class, function () use ($builder): void {
            $builder->build(Fixtures::invoicePayload([
                'totals' => [
                    'exemption_reason' => 'E6',
                ],
            ]));
        }, 422);
    }

    public function testRejectsPaymentBlockBeforeIssuingInvoice(): void
    {
        $db = TestDatabase::fresh();
        $service = new InvoiceBeforePaymentService(
            new InvoiceBeforePaymentPayloadBuilder(),
            IssueInvoiceTest::serviceFor($db)
        );

        Assert::throws(SifException::class, function () use ($service): void {
            $service->issueBeforePayment(Fixtures::invoicePayload([
                'payment' => [
                    'amount' => '120.00',
                    'movement_date' => '2026-06-10',
                ],
            ]));
        }, 422);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
    }

    public function testRejectsMissingInscriptionOrigins(): void
    {
        $builder = new InvoiceBeforePaymentPayloadBuilder();
        $input = Fixtures::invoicePayload();
        unset($input['relations']);

        Assert::throws(SifException::class, function () use ($builder, $input): void {
            $builder->build($input);
        }, 422);
    }

    public function testRejectsDuplicateInscriptionOriginsInsideSameRequest(): void
    {
        $builder = new InvoiceBeforePaymentPayloadBuilder();

        Assert::throws(SifException::class, function () use ($builder): void {
            $builder->build(Fixtures::invoicePayload([
                'relations' => [
                    [
                        'source_type' => 'INSCRIPCIO',
                        'source_id' => 900,
                        'factura_relacionada' => 900,
                    ],
                    [
                        'source_type' => 'INSCRIPCIO',
                        'source_id' => '900',
                        'factura_relacionada' => 900,
                    ],
                ],
            ]));
        }, 422);
    }

    public function testRejectsNonInscriptionOrigin(): void
    {
        $builder = new InvoiceBeforePaymentPayloadBuilder();

        Assert::throws(SifException::class, function () use ($builder): void {
            $builder->build(Fixtures::invoicePayload([
                'relations' => [[
                    'source_type' => 'PACK',
                    'source_id' => 900,
                ]],
            ]));
        }, 422);
    }

    public function testFailsClosedWhenInvoiceServiceHasNoBeforePaymentCoverageRepository(): void
    {
        $db = TestDatabase::fresh();
        $invoiceService = new \Prisma\Sif\Service\InvoiceService(
            new \Prisma\Sif\Database\TransactionRunner($db),
            new \Prisma\Sif\Service\InvoicePayloadValidator(),
            new \Prisma\Sif\Repository\FiscalSequenceRepository(),
            new \Prisma\Sif\Repository\InvoiceRepository(
                new \Prisma\Sif\Domain\UuidGenerator(),
                new \Prisma\Sif\Domain\HashCalculator()
            )
        );
        $service = new InvoiceBeforePaymentService(
            new InvoiceBeforePaymentPayloadBuilder(),
            $invoiceService
        );

        Assert::throws(\RuntimeException::class, function () use ($service): void {
            $service->issueBeforePayment(Fixtures::invoicePayload([
                'idempotency_key' => 'INTRANET|FACTURA_ABANS_COBRAR|REF:NO-COVERAGE-REPO',
            ]));
        });

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM invoice_before_payment_coverage')->fetchColumn());
    }

    public function testFailsClosedWhenInvoiceServiceHasNoOperationalAuditRepository(): void
    {
        $db = TestDatabase::fresh();
        $invoiceService = new \Prisma\Sif\Service\InvoiceService(
            new \Prisma\Sif\Database\TransactionRunner($db),
            new \Prisma\Sif\Service\InvoicePayloadValidator(),
            new \Prisma\Sif\Repository\FiscalSequenceRepository(),
            new \Prisma\Sif\Repository\InvoiceRepository(
                new \Prisma\Sif\Domain\UuidGenerator(),
                new \Prisma\Sif\Domain\HashCalculator()
            ),
            null,
            null,
            null,
            new \Prisma\Sif\Repository\InvoiceBeforePaymentCoverageRepository()
        );
        $service = new InvoiceBeforePaymentService(
            new InvoiceBeforePaymentPayloadBuilder(),
            $invoiceService
        );

        Assert::throws(\RuntimeException::class, function () use ($service): void {
            $service->issueBeforePayment(Fixtures::invoicePayload([
                'idempotency_key' => 'INTRANET|FACTURA_ABANS_COBRAR|REF:NO-AUDIT-REPO',
            ]));
        });

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM operational_event')->fetchColumn());
    }

    public function testDocumentQueueFailureKeepsInvoiceCommittedAndRetryReusesIt(): void
    {
        $db = TestDatabase::fresh();
        $failingQueue = new class implements InvoiceBeforePaymentDocumentQueueInterface {
            public function ensurePdf(string $uuidFactura, string $invoiceIdempotencyKey): array
            {
                throw new \RuntimeException('forced document queue failure');
            }
        };

        $service = new InvoiceBeforePaymentService(
            new InvoiceBeforePaymentPayloadBuilder(),
            IssueInvoiceTest::serviceFor($db),
            $failingQueue
        );
        $input = Fixtures::invoicePayload([
            'idempotency_key' => 'INTRANET|FACTURA_ABANS_COBRAR|REF:DOC-RECOVERY',
            'source_channel' => 'INTRANET',
            'created_by' => 'gestio-doc-recovery',
            'relations' => [[
                'source_type' => 'INSCRIPCIO',
                'source_id' => 902,
                'factura_relacionada' => 902,
            ]],
            'lines' => [[
                'concept' => 'Curs recuperacio document',
                'detail' => 'Factura emesa amb cua documental temporalment fallida',
                'quantity' => '1.00',
                'unit_price' => '120.00',
                'base' => '120.00',
                'import_base' => '120.00',
                'discount_amount' => '0.00',
                'taxable_base' => '120.00',
                'iva_regim' => 'EXEMPT',
                'iva_pct' => '0.00',
                'iva_import' => '0.00',
                'total' => '120.00',
                'source_type' => 'INSCRIPCIO',
                'source_id' => 902,
            ]],
        ]);

        $first = $service->issueBeforePayment($input);

        Assert::same(true, $first['ok']);
        Assert::same(false, $first['idempotency_reused']);
        Assert::same('ERROR', $first['document_status']);
        Assert::same('DOCUMENT_QUEUE_FAILED', $first['document_error_code']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM document_job')->fetchColumn());

        $retryService = $this->serviceWithDocuments($db);
        $retry = $retryService->issueBeforePayment($input);

        Assert::same(true, $retry['ok']);
        Assert::same(true, $retry['idempotency_reused']);
        Assert::same($first['uuid_factura'], $retry['uuid_factura']);
        Assert::same('PENDING', $retry['document_status']);
        Assert::same(false, $retry['document_job']['reused']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM operational_event')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM document_job')->fetchColumn());
    }

    public function testDifferentIdempotencyKeyCannotCoverSameInscriptionTwice(): void
    {
        $db = TestDatabase::fresh();
        $service = $this->serviceWithDocuments($db);

        $first = $service->issueBeforePayment(Fixtures::invoicePayload([
            'idempotency_key' => 'INTRANET|FACTURA_ABANS_COBRAR|REF:COVERAGE-1',
            'relations' => [[
                'source_type' => 'INSCRIPCIO',
                'source_id' => 901,
                'factura_relacionada' => 901,
            ]],
            'lines' => [[
                'concept' => 'Curs cobertura',
                'detail' => 'Primera factura',
                'quantity' => '1.00',
                'unit_price' => '120.00',
                'base' => '120.00',
                'import_base' => '120.00',
                'discount_amount' => '0.00',
                'taxable_base' => '120.00',
                'iva_regim' => 'EXEMPT',
                'iva_pct' => '0.00',
                'iva_import' => '0.00',
                'total' => '120.00',
                'source_type' => 'INSCRIPCIO',
                'source_id' => 901,
            ]],
        ]));

        Assert::same(false, $first['idempotency_reused']);

        $exception = Assert::throws(SifException::class, function () use ($service): void {
            $service->issueBeforePayment(Fixtures::invoicePayload([
                'idempotency_key' => 'INTRANET|FACTURA_ABANS_COBRAR|REF:COVERAGE-2',
                'relations' => [[
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => 901,
                    'factura_relacionada' => 901,
                ]],
                'lines' => [[
                    'concept' => 'Curs cobertura',
                    'detail' => 'Segona factura incompatible',
                    'quantity' => '1.00',
                    'unit_price' => '120.00',
                    'base' => '120.00',
                    'import_base' => '120.00',
                    'discount_amount' => '0.00',
                    'taxable_base' => '120.00',
                    'iva_regim' => 'EXEMPT',
                    'iva_pct' => '0.00',
                    'iva_import' => '0.00',
                    'total' => '120.00',
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => 901,
                ]],
            ]));
        }, 409);

        Assert::stringContainsString('already claimed', $exception->getMessage());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM fiscal_queue')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM fact_rels')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM invoice_before_payment_coverage')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM operational_event')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM document_job')->fetchColumn());
        Assert::same(1, (int) $db->query(
            'SELECT LAST_NUM FROM fiscal_sequence WHERE TIPUS_SERIE = "A" AND ANY_FACT = 2026'
        )->fetchColumn());
    }
    private function serviceWithDocuments(\PDO $db): InvoiceBeforePaymentService
    {
        return new InvoiceBeforePaymentService(
            new InvoiceBeforePaymentPayloadBuilder(),
            IssueInvoiceTest::serviceFor($db),
            new InvoiceBeforePaymentDocumentQueueService(
                new TransactionRunner($db),
                new DocumentJobRepository(),
                'uc004-fiscal-pdf-v1'
            )
        );
    }
}
