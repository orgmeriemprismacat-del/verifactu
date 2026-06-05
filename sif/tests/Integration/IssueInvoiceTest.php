<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\HashCalculator;
use Prisma\Sif\Domain\PaymentStatusCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\FiscalSequenceRepository;
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
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_linia')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM fiscal_queue')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM fact_rels')->fetchColumn());
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
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM fiscal_queue')->fetchColumn());
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

    public static function serviceFor(\PDO $db): InvoiceService
    {
        return new InvoiceService(
            new TransactionRunner($db),
            new InvoicePayloadValidator(),
            new FiscalSequenceRepository(),
            new InvoiceRepository(new UuidGenerator(), new HashCalculator()),
            new PaymentPayloadValidator(),
            new PaymentRepository(new UuidGenerator(), new PaymentStatusCalculator())
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
