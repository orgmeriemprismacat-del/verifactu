<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\HashCalculator;
use Prisma\Sif\Domain\PaymentStatusCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\FiscalSequenceRepository;
use Prisma\Sif\Repository\InvoiceBeforePaymentCoverageRepository;
use Prisma\Sif\Repository\InvoiceRepository;
use Prisma\Sif\Repository\PaymentRepository;
use Prisma\Sif\Service\InvoicePayloadValidator;
use Prisma\Sif\Service\InvoiceService;
use Prisma\Sif\Service\PaymentPayloadValidator;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class IssueInvoiceTest
{
    public function testIssueInvoiceCreatesFiscalRecordAndQueue(): void
    {
        $db = TestDatabase::fresh();
        $service = $this->makeService($db);

        $result = $service->issueInvoice(Fixtures::invoicePayload());

        Assert::same(true, $result['ok']);
        Assert::same(false, $result['idempotency_reused']);
        Assert::same('A2026/000001', $result['num_visible']);
        Assert::matchesRegularExpression('/^[0-9a-f-]{36}$/', $result['uuid_factura']);
        Assert::same('ISSUED', $result['status']['invoice']);
        Assert::same('PENDING', $result['status']['payment']);
        Assert::same('PENDING', $result['status']['aeat']);
        Assert::same('PENDING', $result['status']['fiscal_queue']);
        Assert::same(null, $result['status']['document']);
        Assert::same(null, $result['status']['document_type']);
        Assert::same(1, $result['fiscal_order']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_linia')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registre_control')->fetchColumn());
        Assert::same('ALTA', (string) $db->query('SELECT RECORD_ACTION FROM factura_registre_control LIMIT 1')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM fiscal_queue')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM fact_rels')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM operational_event')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM sif_audit_event')->fetchColumn());
        Assert::same($result['correlation_id'], (string) $db->query(
            'SELECT CORRELATION_ID FROM operational_event LIMIT 1'
        )->fetchColumn());
        Assert::same('SUCCEEDED', (string) $db->query(
            'SELECT RESULT FROM sif_audit_event LIMIT 1'
        )->fetchColumn());
        Assert::same(
            (int) $db->query('SELECT ID FROM factura_linia LIMIT 1')->fetchColumn(),
            (int) $db->query('SELECT ID_FACTURA_LINIA FROM fact_rels LIMIT 1')->fetchColumn()
        );
        Assert::same(1, (int) $db->query('SELECT LAST_FISCAL_ORDER FROM fiscal_chain_state WHERE ID = 1')->fetchColumn());

        $recordPayload = (string) $db->query('SELECT PAYLOAD_JSON FROM factura_registres LIMIT 1')->fetchColumn();
        $queue = $db->query('SELECT IDEMPOTENCY_KEY, PAYLOAD_JSON, STATUS FROM fiscal_queue LIMIT 1')
            ->fetch(\PDO::FETCH_ASSOC);

        Assert::same(JSON_ERROR_NONE, $this->jsonError($recordPayload));
        Assert::same(JSON_ERROR_NONE, $this->jsonError((string) $queue['PAYLOAD_JSON']));
        Assert::same($recordPayload, (string) $queue['PAYLOAD_JSON']);
        Assert::same('AEAT|REDSYS|CURS|IDPAG:123|ORDER:999999', (string) $queue['IDEMPOTENCY_KEY']);
        Assert::same('PENDING', (string) $queue['STATUS']);
    }

    public function testIssueInvoiceReusesExistingInvoiceForSameIdempotencyKey(): void
    {
        $db = TestDatabase::fresh();
        $service = $this->makeService($db);

        $first = $service->issueInvoice(Fixtures::invoicePayload());
        $second = $service->issueInvoice(Fixtures::invoicePayload());

        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_factura'], $second['uuid_factura']);
        Assert::same($first['num_visible'], $second['num_visible']);
        Assert::same($first['status'], $second['status']);
        Assert::same(1, $second['fiscal_order']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM fiscal_queue')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM operational_event')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM sif_audit_event')->fetchColumn());
        Assert::same('REUSED', (string) $db->query(
            'SELECT RESULT FROM sif_audit_event ORDER BY ID DESC LIMIT 1'
        )->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT LAST_NUM FROM fiscal_sequence WHERE TIPUS_SERIE = "A" AND ANY_FACT = 2026')->fetchColumn());
    }

    public function testIssueInvoiceWithPaymentCreatesPaymentTransactionAndAllocation(): void
    {
        $db = TestDatabase::fresh();
        $service = $this->makeService($db);
        $payload = Fixtures::invoicePayload([
            'idempotency_key' => 'REDSYS|CURS|IDPAG:222|ORDER:ORDER222',
            'relations' => [[
                'source_type' => 'INSCRIPCIO',
                'source_id' => 222,
                'factura_relacionada' => 522,
                'idpag' => 222,
                'ds_order' => 'ORDER222',
                'visible_alumne' => 1,
            ]],
            'payment' => [
                'idempotency_key' => 'PAYMENT|REDSYS|ORDER:ORDER222',
                'movement_type' => 'CHARGE',
                'method' => 'REDSYS',
                'source_channel' => 'REDSYS',
                'amount' => '120.00',
                'movement_date' => '2026-06-02 10:00:00',
                'ds_order' => 'ORDER222',
                'idpag' => 222,
            ],
        ]);

        $result = $service->issueInvoice($payload);
        $second = $service->issueInvoice($payload);

        Assert::same(true, $result['ok']);
        Assert::same(false, $result['idempotency_reused']);
        Assert::matchesRegularExpression('/^[0-9a-f-]{36}$/', $result['uuid_payment']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($result['uuid_payment'], $second['uuid_payment']);
        Assert::same('PAID', $result['status']['payment']);
        Assert::same('PAID', $second['status']['payment']);
        Assert::same('PENDING', $result['status']['aeat']);
        Assert::same('PENDING', $result['status']['fiscal_queue']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same('PAID', (string) $db->query('SELECT ESTAT_COBRAMENT FROM factura')->fetchColumn());

        $payment = $db->query('SELECT METODE, DS_ORDER, IDPAG FROM payment_transaction')
            ->fetch(\PDO::FETCH_ASSOC);
        $allocation = $db->query('SELECT UUID_FACTURA, IMPORT_ASSIGNAT, TIPUS_ASSIGNACIO FROM payment_allocation')
            ->fetch(\PDO::FETCH_ASSOC);

        Assert::same('REDSYS', $payment['METODE']);
        Assert::same('ORDER222', $payment['DS_ORDER']);
        Assert::same(222, (int) $payment['IDPAG']);
        Assert::same($result['uuid_factura'], $allocation['UUID_FACTURA']);
        Assert::same('120.00', $allocation['IMPORT_ASSIGNAT']);
        Assert::same('INVOICE_PAYMENT', $allocation['TIPUS_ASSIGNACIO']);
    }

    public function testIssueInvoicePersistsLineExemptionReason(): void
    {
        $db = TestDatabase::fresh();
        $service = $this->makeService($db);
        $payload = Fixtures::invoicePayload([
            'idempotency_key' => 'INTRANET|EXEMPTION|UC001',
            'source_channel' => 'INTRANET',
            'totals' => [
                'exemption_reason' => 'E1',
            ],
            'lines' => [[
                'exemption_reason' => 'E1',
            ]],
        ]);

        $service->issueInvoice($payload);

        Assert::same(
            'E1',
            (string) $db->query('SELECT CAUSA_EXEMPCIO_NO_SUBJECTA FROM factura_linia LIMIT 1')->fetchColumn()
        );
    }


    public function testAmbiguousOriginDoesNotGuessInvoiceLineRelation(): void
    {
        $db = TestDatabase::fresh();
        $service = $this->makeService($db);
        $payload = Fixtures::invoicePayload([
            'idempotency_key' => 'INTRANET|AMBIGUOUS-LINE|UC001',
            'source_channel' => 'INTRANET',
            'totals' => [
                'import_base' => '240.00',
                'taxable_base' => '240.00',
                'total' => '240.00',
            ],
            'lines' => [
                [
                    'concept' => 'Part A',
                    'detail' => 'Mateix origen comercial',
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
                    'source_id' => 10,
                ],
                [
                    'concept' => 'Part B',
                    'detail' => 'Mateix origen comercial',
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
                    'source_id' => 10,
                ],
            ],
        ]);

        $service->issueInvoice($payload);

        $lineId = $db->query('SELECT ID_FACTURA_LINIA FROM fact_rels LIMIT 1')->fetchColumn();
        Assert::same(null, $lineId === false ? null : $lineId);
    }

    public function testFiscalRegisterControlLinksPreviousGlobalRecord(): void
    {
        $db = TestDatabase::fresh();
        $service = $this->makeService($db);

        $first = $service->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'INTRANET|CONTROL|FIRST',
            'source_channel' => 'INTRANET',
            'correlation_id' => 'UC001-CONTROL-FIRST',
        ]));
        $second = $service->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'INTRANET|CONTROL|SECOND',
            'source_channel' => 'INTRANET',
            'correlation_id' => 'UC001-CONTROL-SECOND',
        ]));

        Assert::notSame($first['uuid_factura'], $second['uuid_factura']);
        $rows = $db->query(
            'SELECT frc.FACTURA_REGISTRE_ID, frc.PREVIOUS_REGISTRE_ID,
                    frc.RECORD_ACTION, frc.GENERATOR_VERSION, frc.CORRELATION_ID
             FROM factura_registre_control frc
             ORDER BY frc.ID'
        )->fetchAll(\PDO::FETCH_ASSOC);

        Assert::same(2, count($rows));
        Assert::same(null, $rows[0]['PREVIOUS_REGISTRE_ID']);
        Assert::same((int) $rows[0]['FACTURA_REGISTRE_ID'], (int) $rows[1]['PREVIOUS_REGISTRE_ID']);
        Assert::same('ALTA', $rows[1]['RECORD_ACTION']);
        Assert::same('invoice-repository-v1', $rows[1]['GENERATOR_VERSION']);
        Assert::same('UC001-CONTROL-SECOND', $rows[1]['CORRELATION_ID']);
    }

    public function testMaterialisesCommercialOperationLineToInvoiceLineWhenProvided(): void
    {
        $db = TestDatabase::fresh();
        $uuidOperation = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
        $uuidLine = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';

        $db->prepare(
            'INSERT INTO commercial_operation (
                UUID_OPERATION, IDEMPOTENCY_KEY, OPERATION_TYPE, SOURCE_CHANNEL,
                SOURCE_TYPE, SOURCE_ID, PRODUCT_TYPE, PRODUCT_CODE, PRODUCT_EDITION,
                CLASSIFICATION, CLASSIFICATION_REASON, STATUS, CURRENCY,
                GROSS_AMOUNT, DISCOUNT_AMOUNT, NET_AMOUNT,
                PRICE_SNAPSHOT_JSON, CAPACITY_SNAPSHOT_JSON, TAX_SNAPSHOT_JSON,
                EXPIRES_AT, CREATED_BY
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, NULL, ?)'
        )->execute([
            $uuidOperation,
            'COMMERCIAL|UC001|LINK',
            'SALE',
            'INTRANET',
            'INSCRIPCIO',
            '10',
            'COURSE',
            'TEST',
            '2026',
            'INVOICE',
            'UC001_TEST',
            'CONFIRMED',
            'EUR',
            '120.00',
            '0.00',
            '120.00',
            '{}',
            '{}',
            'test-runner',
        ]);

        $db->prepare(
            'INSERT INTO commercial_operation_line (
                UUID_LINE, UUID_OPERATION, PARENT_UUID_LINE, LINE_TYPE, ORDRE,
                PRODUCT_TYPE, PRODUCT_CODE, PRODUCT_EDITION, PARTICIPANT_PARTY_KEY,
                DESCRIPTION, QUANTITY, UNIT_PRICE, GROSS_AMOUNT, DISCOUNT_AMOUNT,
                NET_AMOUNT, TAX_REGIME, TAX_RATE, TAX_AMOUNT,
                EXEMPTION_OR_NON_SUBJECT_REASON, PRICE_RULE_VERSION, SNAPSHOT_JSON, STATUS
            ) VALUES (?, ?, NULL, ?, 1, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $uuidLine,
            $uuidOperation,
            'PRODUCT',
            'COURSE',
            'TEST',
            '2026',
            'Curs de prova',
            '1.00',
            '120.00',
            '120.00',
            '0.00',
            '120.00',
            'EXEMPT',
            '0.00',
            '0.00',
            'E1',
            'test-v1',
            '{}',
            'CONFIRMED',
        ]);

        $result = $this->makeService($db)->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'INTRANET|COMMERCIAL-LINE|UC001',
            'source_channel' => 'INTRANET',
            'lines' => [[
                'uuid_operation_line' => $uuidLine,
            ]],
        ]));

        $link = $db->query(
            'SELECT UUID_LINE, FACTURA_LINE_ID, LINK_TYPE, LINKED_AMOUNT
             FROM operation_line_invoice_link LIMIT 1'
        )->fetch(\PDO::FETCH_ASSOC);

        Assert::same($uuidLine, $link['UUID_LINE']);
        Assert::same(
            (int) $db->query('SELECT ID FROM factura_linia WHERE UUID_FACTURA = '
                . $db->quote($result['uuid_factura']) . ' LIMIT 1')->fetchColumn(),
            (int) $link['FACTURA_LINE_ID']
        );
        Assert::same('MATERIALISED_AS', $link['LINK_TYPE']);
        Assert::same('120.00', $link['LINKED_AMOUNT']);
    }

    public static function serviceFor(\PDO $db): InvoiceService
    {
        return new InvoiceService(
            new TransactionRunner($db),
            new InvoicePayloadValidator(),
            new FiscalSequenceRepository(),
            new InvoiceRepository(new UuidGenerator(), new HashCalculator()),
            new PaymentPayloadValidator(),
            new PaymentRepository(new UuidGenerator(), new PaymentStatusCalculator()),
            null,
            new InvoiceBeforePaymentCoverageRepository()
        );
    }

    private function makeService(\PDO $db): InvoiceService
    {
        return self::serviceFor($db);
    }

    private function jsonError(string $json): int
    {
        json_decode($json, true);

        return json_last_error();
    }
}
