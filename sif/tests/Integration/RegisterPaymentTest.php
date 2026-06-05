<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\PaymentStatusCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\PaymentRepository;
use Prisma\Sif\Service\PaymentPayloadValidator;
use Prisma\Sif\Service\PaymentService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class RegisterPaymentTest
{
    public function testRegisterPaymentCreatesTransactionAndAllocationOnly(): void
    {
        $db = TestDatabase::fresh();
        $invoiceService = IssueInvoiceTest::serviceFor($db);
        $invoice = $invoiceService->issueInvoice(Fixtures::invoicePayload(['emesa_abans_cobrament' => 1]));

        $paymentService = self::paymentServiceFor($db);
        $result = $paymentService->registerPayment($this->paymentPayload($invoice['uuid_factura']));

        Assert::same(true, $result['ok']);
        Assert::same(false, $result['idempotency_reused']);
        Assert::matchesRegularExpression('/^[0-9a-f-]{36}$/', $result['uuid_payment']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same('PAID', (string) $db->query('SELECT ESTAT_COBRAMENT FROM factura')->fetchColumn());
    }

    public function testRegisterPaymentReusesSamePaymentForSameIdempotencyKey(): void
    {
        $db = TestDatabase::fresh();
        $invoiceService = IssueInvoiceTest::serviceFor($db);
        $invoice = $invoiceService->issueInvoice(Fixtures::invoicePayload(['emesa_abans_cobrament' => 1]));

        $paymentService = self::paymentServiceFor($db);
        $first = $paymentService->registerPayment($this->paymentPayload($invoice['uuid_factura']));
        $second = $paymentService->registerPayment($this->paymentPayload($invoice['uuid_factura']));

        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_payment'], $second['uuid_payment']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same('PAID', (string) $db->query('SELECT ESTAT_COBRAMENT FROM factura')->fetchColumn());
    }

    public static function paymentServiceFor(\PDO $db): PaymentService
    {
        return new PaymentService(
            new TransactionRunner($db),
            new PaymentPayloadValidator(),
            new PaymentRepository(new UuidGenerator(), new PaymentStatusCalculator())
        );
    }

    private function paymentPayload(string $uuidFactura, array $overrides = []): array
    {
        return array_replace_recursive([
            'idempotency_key' => 'TRANSFERENCIA|REF:ABC123',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '120.00',
            'movement_date' => '2026-06-02 10:00:00',
            'reference' => 'ABC123',
            'allocations' => [[
                'uuid_factura' => $uuidFactura,
                'amount' => '120.00',
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ], $overrides);
    }
}
