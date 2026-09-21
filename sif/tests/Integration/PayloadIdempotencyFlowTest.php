<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Contract\AeatTransport;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\FiscalQueueRepository;
use Prisma\Sif\Service\FiscalQueueProcessor;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class PayloadIdempotencyFlowTest
{
    public function testInvoiceRetryComparesFullOriginalInputAndPreservesFiscalSequence(): void
    {
        $db = TestDatabase::fresh();
        $service = IssueInvoiceTest::serviceFor($db);
        $payload = Fixtures::invoicePayload();
        $first = $service->issueInvoice($payload);
        $same = $service->issueInvoice($payload);
        Assert::same($first['uuid_factura'], $same['uuid_factura']);
        Assert::same(true, $same['idempotency_reused']);
        $payload['billing']['name'] = 'Una altra persona';
        Assert::throws(SifException::class, fn () => $service->issueInvoice($payload), 409);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT LAST_FISCAL_ORDER FROM fiscal_chain_state WHERE ID = 1')->fetchColumn());
    }

    public function testRetryCannotAddAnInitialPaymentToAnAlreadyIssuedInvoice(): void
    {
        $db = TestDatabase::fresh();
        $service = IssueInvoiceTest::serviceFor($db);
        $payload = Fixtures::invoicePayload();
        $service->issueInvoice($payload);
        $payload['payment'] = [
            'idempotency_key' => 'PAYMENT|RETRY_DIFFERENT_INPUT',
            'amount' => '120.00',
        ];
        Assert::throws(SifException::class, fn () => $service->issueInvoice($payload), 409);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
    }

    public function testOriginalInvoiceWithoutFingerprintFailsClosed(): void
    {
        $db = TestDatabase::fresh();
        $service = IssueInvoiceTest::serviceFor($db);
        $payload = Fixtures::invoicePayload();
        $service->issueInvoice($payload);
        $db->exec('UPDATE factura SET IDEMPOTENCY_PAYLOAD_HASH = NULL');
        Assert::throws(SifException::class, fn () => $service->issueInvoice($payload), 409);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
    }

    public function testPaymentRetryComparesAmountAndAllocations(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload());
        $service = RegisterPaymentTest::paymentServiceFor($db);
        $payload = [
            'idempotency_key' => 'TRANSFERENCIA|REF:IDEMPOTENCY_TEST',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '120.00',
            'movement_date' => '2026-06-02 10:00:00',
            'reference' => 'IDEMPOTENCY_TEST',
            'allocations' => [[
                'uuid_factura' => $invoice['uuid_factura'],
                'amount' => '120.00',
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ];
        $first = $service->registerPayment($payload);
        Assert::same(true, $service->registerPayment($payload)['idempotency_reused']);
        $payload['amount'] = '100.00';
        Assert::throws(SifException::class, fn () => $service->registerPayment($payload), 409);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT PAYLOAD_HASH_VERSION FROM payment_transaction')->fetchColumn());
        Assert::matchesRegularExpression('/^[a-f0-9]{64}$/D',
            (string) $db->query('SELECT IDEMPOTENCY_PAYLOAD_HASH FROM factura')->fetchColumn());
        Assert::same($first['uuid_payment'], $service->registerPayment([
            'reference' => 'IDEMPOTENCY_TEST',
            'movement_date' => '2026-06-02 10:00:00',
            'amount' => '120.00',
            'allocations' => $payload['allocations'],
            'source_channel' => 'INTRANET',
            'method' => 'TRANSFERENCIA',
            'movement_type' => 'CHARGE',
            'idempotency_key' => 'TRANSFERENCIA|REF:IDEMPOTENCY_TEST',
        ])['uuid_payment']);
    }

    public function testLegacyPaymentHashUsesLegacySerializationUntilMigrated(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload());
        $service = RegisterPaymentTest::paymentServiceFor($db);
        $payload = [
            'idempotency_key' => 'TRANSFERENCIA|REF:LEGACY_TEST',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '120.00',
            'movement_date' => '2026-06-02 10:00:00',
            'allocations' => [[
                'uuid_factura' => $invoice['uuid_factura'],
                'amount' => '120.00',
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ];
        $service->registerPayment($payload);
        $originalLegacyHash = hash('sha256', json_encode($payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
        $stmt = $db->prepare('UPDATE payment_transaction SET PAYLOAD_HASH = ?, PAYLOAD_HASH_VERSION = 1');
        $stmt->execute([$originalLegacyHash]);
        Assert::same(true, $service->registerPayment($payload)['idempotency_reused']);
        $payload['amount'] = '80.00';
        Assert::throws(SifException::class, fn () => $service->registerPayment($payload), 409);
    }

    public function testFiscalQueueTamperingIsQuarantinedWithoutSending(): void
    {
        $db = TestDatabase::fresh();
        IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload());
        $db->exec("UPDATE fiscal_queue SET PAYLOAD_JSON = JSON_SET(PAYLOAD_JSON, '$.totals.total', '999.00')");
        $transport = new class implements AeatTransport {
            public int $calls = 0;
            public function send(array $fiscalPayload): array
            {
                $this->calls++;
                return ['status' => 'ACCEPTED', 'response' => []];
            }
        };
        $processor = new FiscalQueueProcessor(new TransactionRunner($db), new FiscalQueueRepository(), $transport);
        $result = $processor->processNext();
        Assert::same(false, $result['ok']);
        Assert::same('DEAD_LETTER', $result['queue_status']);
        Assert::same(0, $transport->calls);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM errors_verifactu WHERE TIPUS_INCIDENCIA = "FISCAL_PAYLOAD_CONFLICT"')->fetchColumn());
        Assert::same('PENDING', (string) $db->query('SELECT ESTAT_AEAT FROM factura')->fetchColumn());
        Assert::same('PENDING', (string) $db->query('SELECT ESTAT_AEAT FROM factura_registres')->fetchColumn());
    }

    public function testFiscalRecordTamperingIsQuarantinedWithoutSending(): void
    {
        $db = TestDatabase::fresh();
        IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload());
        $db->exec("UPDATE factura_registres SET PAYLOAD_JSON = JSON_SET(PAYLOAD_JSON, '$.totals.total', '999.00')");
        $db->exec("UPDATE fiscal_queue SET PAYLOAD_JSON = JSON_SET(PAYLOAD_JSON, '$.totals.total', '999.00')");
        $transport = new class implements AeatTransport {
            public int $calls = 0;
            public function send(array $fiscalPayload): array
            {
                $this->calls++;
                return ['status' => 'ACCEPTED', 'response' => []];
            }
        };
        $result = (new FiscalQueueProcessor(
            new TransactionRunner($db), new FiscalQueueRepository(), $transport
        ))->processNext();
        Assert::same('DEAD_LETTER', $result['queue_status']);
        Assert::same(0, $transport->calls);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM errors_verifactu')->fetchColumn());
    }
}
