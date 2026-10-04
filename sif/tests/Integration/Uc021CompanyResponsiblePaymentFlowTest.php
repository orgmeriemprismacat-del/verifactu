<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Service\InvoiceBeforePaymentPayloadBuilder;
use Prisma\Sif\Service\InvoiceBeforePaymentService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class Uc021CompanyResponsiblePaymentFlowTest
{
    public function testJointInvoiceCanMoveFromPendingToPartialToPaidWithoutSecondFiscalRecord(): void
    {
        $db = TestDatabase::fresh();

        $invoiceService = new InvoiceBeforePaymentService(
            new InvoiceBeforePaymentPayloadBuilder(),
            IssueInvoiceTest::serviceFor($db)
        );

        $invoice = $invoiceService->issueBeforePayment($this->jointInvoicePayload());

        Assert::same(true, $invoice['ok']);
        Assert::same(false, $invoice['idempotency_reused']);
        Assert::same('PENDING', (string) $db->query(
            'SELECT ESTAT_COBRAMENT FROM factura WHERE UUID_FACTURA = "' . $invoice['uuid_factura'] . '"'
        )->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM factura_linia')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM fact_rels')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM invoice_before_payment_coverage')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM fiscal_queue')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());

        $payments = RegisterPaymentTest::paymentServiceFor($db);

        $firstPayment = $payments->registerPayment([
            'idempotency_key' => 'TRANSFERENCIA|UC021|REF:PARTIAL-80',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '80.00',
            'movement_date' => '2026-10-03 10:00:00',
            'reference' => 'UC021-PARTIAL-80',
            'allocations' => [[
                'uuid_factura' => $invoice['uuid_factura'],
                'amount' => '80.00',
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ]);

        Assert::same(false, $firstPayment['idempotency_reused']);
        Assert::same('PARTIAL', (string) $db->query(
            'SELECT ESTAT_COBRAMENT FROM factura WHERE UUID_FACTURA = "' . $invoice['uuid_factura'] . '"'
        )->fetchColumn());

        $secondPayment = $payments->registerPayment([
            'idempotency_key' => 'TRANSFERENCIA|UC021|REF:FINAL-120',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '120.00',
            'movement_date' => '2026-10-03 11:00:00',
            'reference' => 'UC021-FINAL-120',
            'allocations' => [[
                'uuid_factura' => $invoice['uuid_factura'],
                'amount' => '120.00',
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ]);

        Assert::same(false, $secondPayment['idempotency_reused']);
        Assert::same('PAID', (string) $db->query(
            'SELECT ESTAT_COBRAMENT FROM factura WHERE UUID_FACTURA = "' . $invoice['uuid_factura'] . '"'
        )->fetchColumn());

        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM fiscal_queue')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same('200.00', number_format((float) $db->query(
            'SELECT SUM(IMPORT_ASSIGNAT) FROM payment_allocation WHERE UUID_FACTURA = "' . $invoice['uuid_factura'] . '"'
        )->fetchColumn(), 2, '.', ''));
    }

    public function testActiveRedsysCourseIntentBlocksJointInvoiceBeforeFiscalNumber(): void
    {
        $db = TestDatabase::fresh();
        $this->createCourseIntent($db, '210000000011', 11, '80.00');

        $invoiceService = new InvoiceBeforePaymentService(
            new InvoiceBeforePaymentPayloadBuilder(),
            IssueInvoiceTest::serviceFor($db)
        );

        Assert::throws(SifException::class, function () use ($invoiceService): void {
            $invoiceService->issueBeforePayment($this->jointInvoicePayload());
        }, 409);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM fiscal_queue')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM invoice_before_payment_coverage')->fetchColumn());
        Assert::same(0, (int) $db->query(
            'SELECT COUNT(*) FROM fiscal_sequence WHERE TIPUS_SERIE = "A" AND ANY_FACT = 2026'
        )->fetchColumn());
    }

    public function testRejectedRedsysCourseIntentDoesNotBlockJointInvoice(): void
    {
        $db = TestDatabase::fresh();
        $this->createCourseIntent($db, '210000000012', 11, '80.00');

        (new \Prisma\Sif\Repository\RedsysNotificationRepository())->recordReceived(
            $db,
            '210000000012',
            11,
            '80.00',
            '0190',
            true,
            [
                'currency_code' => '978',
                'terminal' => '1',
                'signature_version' => 'HMAC_SHA256_V1',
                'payload_hash' => hash('sha256', '210000000012'),
            ],
            'ERROR'
        );

        $invoiceService = new InvoiceBeforePaymentService(
            new InvoiceBeforePaymentPayloadBuilder(),
            IssueInvoiceTest::serviceFor($db)
        );
        $result = $invoiceService->issueBeforePayment($this->jointInvoicePayload());

        Assert::same(true, $result['ok']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM invoice_before_payment_coverage')->fetchColumn());
    }

    public function testExistingRedsysCourseInvoiceBlocksUc021BeforeSecondFiscalNumber(): void
    {
        $db = TestDatabase::fresh();
        $service = IssueInvoiceTest::serviceFor($db);

        $service->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'REDSYS|CURS|IDPAG:11|ORDER:210000000111',
            'source_channel' => 'REDSYS',
            'totals' => [
                'import_base' => '80.00',
                'taxable_base' => '80.00',
                'total' => '80.00',
            ],
            'lines' => [[
                'concept' => 'Curs individual',
                'detail' => 'Participant 11',
                'quantity' => '1.00',
                'unit_price' => '80.00',
                'base' => '80.00',
                'import_base' => '80.00',
                'discount_amount' => '0.00',
                'taxable_base' => '80.00',
                'iva_regim' => 'EXEMPT',
                'iva_pct' => '0.00',
                'iva_import' => '0.00',
                'total' => '80.00',
                'source_type' => 'INSCRIPCIO',
                'source_id' => 11,
            ]],
            'relations' => [[
                'source_type' => 'INSCRIPCIO',
                'source_id' => 11,
                'relation_type' => 'ORIGIN',
                'idpag' => 11,
                'ds_order' => '210000000111',
                'visible_alumne' => 1,
            ]],
            'payment' => [
                'idempotency_key' => 'PAYMENT|REDSYS|ORDER:210000000111',
                'movement_type' => 'CHARGE',
                'method' => 'REDSYS',
                'source_channel' => 'REDSYS',
                'amount' => '80.00',
                'movement_date' => '2026-10-04 03:00:00',
                'ds_order' => '210000000111',
                'idpag' => 11,
            ],
        ]));

        $beforePayment = new InvoiceBeforePaymentService(
            new InvoiceBeforePaymentPayloadBuilder(),
            $service
        );

        Assert::throws(SifException::class, function () use ($beforePayment): void {
            $beforePayment->issueBeforePayment($this->jointInvoicePayload());
        }, 409);

        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM invoice_before_payment_coverage')->fetchColumn());
    }

    public function testUc021CoverageBlocksRedsysCourseInvoiceInsideInvoiceTransaction(): void
    {
        $db = TestDatabase::fresh();
        $service = IssueInvoiceTest::serviceFor($db);

        $beforePayment = new InvoiceBeforePaymentService(
            new InvoiceBeforePaymentPayloadBuilder(),
            $service
        );
        $beforePayment->issueBeforePayment($this->jointInvoicePayload());

        Assert::throws(SifException::class, function () use ($service): void {
            $service->issueInvoice(Fixtures::invoicePayload([
                'idempotency_key' => 'REDSYS|CURS|IDPAG:11|ORDER:210000000112',
                'source_channel' => 'REDSYS',
                'totals' => [
                    'import_base' => '80.00',
                    'taxable_base' => '80.00',
                    'total' => '80.00',
                ],
                'lines' => [[
                    'concept' => 'Curs individual',
                    'detail' => 'Participant 11',
                    'quantity' => '1.00',
                    'unit_price' => '80.00',
                    'base' => '80.00',
                    'import_base' => '80.00',
                    'discount_amount' => '0.00',
                    'taxable_base' => '80.00',
                    'iva_regim' => 'EXEMPT',
                    'iva_pct' => '0.00',
                    'iva_import' => '0.00',
                    'total' => '80.00',
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => 11,
                ]],
                'relations' => [[
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => 11,
                    'relation_type' => 'ORIGIN',
                    'idpag' => 11,
                    'ds_order' => '210000000112',
                    'visible_alumne' => 1,
                ]],
                'payment' => [
                    'idempotency_key' => 'PAYMENT|REDSYS|ORDER:210000000112',
                    'movement_type' => 'CHARGE',
                    'method' => 'REDSYS',
                    'source_channel' => 'REDSYS',
                    'amount' => '80.00',
                    'movement_date' => '2026-10-04 03:05:00',
                    'ds_order' => '210000000112',
                    'idpag' => 11,
                ],
            ]));
        }, 409);

        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM invoice_before_payment_coverage')->fetchColumn());
    }

    public function testSameIdempotencyKeyWithChangedBillingPartyIsRejected(): void
    {
        $db = TestDatabase::fresh();

        $invoiceService = new InvoiceBeforePaymentService(
            new InvoiceBeforePaymentPayloadBuilder(),
            IssueInvoiceTest::serviceFor($db)
        );

        $payload = $this->jointInvoicePayload();
        $invoiceService->issueBeforePayment($payload);

        $changed = $payload;
        $changed['billing']['name'] = 'Una altra empresa';
        $changed['billing']['nif'] = 'B99999999';

        Assert::throws(SifException::class, function () use ($invoiceService, $changed): void {
            $invoiceService->issueBeforePayment($changed);
        }, 409);

        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM fiscal_queue')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM invoice_before_payment_coverage')->fetchColumn());
    }

    private function createCourseIntent(\PDO $db, string $dsOrder, int $sourceId, string $amount): void
    {
        (new \Prisma\Sif\Service\RedsysPaymentIntentService(
            new \Prisma\Sif\Repository\RedsysPaymentIntentRepository(),
            new \Prisma\Sif\Domain\UuidGenerator()
        ))->create($db, [
            'ds_order' => $dsOrder,
            'idpag' => $sourceId,
            'source_type' => 'CURS',
            'source_id' => (string) $sourceId,
            'expected_amount' => $amount,
            'currency' => 'EUR',
            'terminal' => '1',
            'snapshot' => [
                'inscription' => [
                    'ID' => $sourceId,
                    'IDPAG' => $sourceId,
                    'ANY' => 2026,
                    'MES' => '10',
                    'CURS' => 'UC21',
                    'NOM' => 'Participant',
                    'DNI' => '00000000T',
                    'A_PAGAR' => $amount,
                ],
                'course' => ['NOM_CURS' => 'Curs compartit'],
                'payment' => [
                    'idpag' => $sourceId,
                    'amount' => $amount,
                ],
            ],
        ]);
    }

    private function jointInvoicePayload(): array
    {
        return Fixtures::invoicePayload([
            'idempotency_key' => 'INTRANET|FACTURA_ABANS_COBRAR|UC021:ENTITY7:I11-I12',
            'source_channel' => 'INTRANET',
            'created_by' => 'uc021-test',
            'billing' => [
                'name' => 'Escola Exemple SL',
                'nif' => 'B12345678',
                'email' => 'responsable@example.test',
            ],
            'totals' => [
                'import_base' => '200.00',
                'taxable_base' => '200.00',
                'total' => '200.00',
            ],
            'lines' => [
                [
                    'concept' => 'Curs compartit',
                    'detail' => 'Participant 11',
                    'quantity' => '1.00',
                    'unit_price' => '80.00',
                    'base' => '80.00',
                    'import_base' => '80.00',
                    'discount_amount' => '0.00',
                    'taxable_base' => '80.00',
                    'iva_regim' => 'EXEMPT',
                    'iva_pct' => '0.00',
                    'iva_import' => '0.00',
                    'total' => '80.00',
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => 11,
                ],
                [
                    'concept' => 'Curs compartit',
                    'detail' => 'Participant 12',
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
                    'source_id' => 12,
                ],
            ],
            'relations' => [
                [
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => 11,
                    'relation_type' => 'ORIGIN',
                    'visible_alumne' => 0,
                ],
                [
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => 12,
                    'relation_type' => 'ORIGIN',
                    'visible_alumne' => 0,
                ],
            ],
        ]);
    }
}
