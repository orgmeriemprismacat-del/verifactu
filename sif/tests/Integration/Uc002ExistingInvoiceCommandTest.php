<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\PaymentStatusCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\PaymentActionEventRepository;
use Prisma\Sif\Repository\PaymentRepository;
use Prisma\Sif\Service\ExistingInvoiceLegacyProjectionService;
use Prisma\Sif\Service\ExistingInvoicePaymentCommandService;
use Prisma\Sif\Service\ManualPaymentPayloadBuilder;
use Prisma\Sif\Service\ManualPaymentService;
use Prisma\Sif\Service\PaymentActionGateway;
use Prisma\Sif\Service\PaymentPayloadValidator;
use Prisma\Sif\Service\PaymentService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class Uc002ExistingInvoiceCommandTest
{
    public function testGatewayOwnsSingleTransactionForExistingInvoicePayment(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload(['emesa_abans_cobrament' => 1])
        );

        $paymentService = new PaymentService(
            new TransactionRunner($db),
            new PaymentPayloadValidator(),
            new PaymentRepository(new UuidGenerator(), new PaymentStatusCalculator())
        );
        $command = new ExistingInvoicePaymentCommandService(
            new ManualPaymentService(
                new ManualPaymentInvoiceRepository(),
                new ManualPaymentPayloadBuilder(),
                $paymentService
            ),
            new ExistingInvoiceLegacyProjectionService()
        );
        $gateway = new PaymentActionGateway(
            $db,
            new TransactionRunner($db),
            new PaymentActionEventRepository(new UuidGenerator())
        );

        $result = $gateway->run(
            [
                'request_id' => '11111111-1111-4111-8111-111111111111',
                'correlation_id' => 'TRANSFERENCIA|REF:UC002-TX',
                'action' => 'CREATE',
                'source_environment' => 'TEST',
                'source_channel' => 'INTRANET',
                'actor_type' => 'HUMAN',
                'actor_id' => 'tester',
                'actor_role' => 'GESTIO',
                'reason_code' => 'UC002_EXISTING_INVOICE_PAYMENT',
                'payment_idempotency_key' => 'TRANSFERENCIA|REF:UC002-TX',
                'occurred_at' => '2026-10-04 04:10:00.000000',
            ],
            static fn (\PDO $transactionDb): array => $command->register(
                $transactionDb,
                [
                    'selector' => ['uuid_factura' => $invoice['uuid_factura']],
                    'payment' => [
                        'idempotency_key' => 'TRANSFERENCIA|REF:UC002-TX',
                        'amount' => '120.00',
                        'movement_date' => '2026-10-04 04:10:00',
                        'method' => 'TRANSFERENCIA',
                        'reference' => 'UC002-TX',
                        'bank' => 'TEST',
                    ],
                ]
            )
        );

        Assert::same(true, $result['ok']);
        Assert::same(false, $result['idempotency_reused']);
        Assert::same('READY', $result['legacy_projection_status']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM payment_action_event')->fetchColumn());
        Assert::same('PAID', (string) $db->query('SELECT ESTAT_COBRAMENT FROM factura')->fetchColumn());
        Assert::same('SUCCEEDED', (string) $db->query(
            'SELECT RESULT FROM payment_action_event WHERE IS_TERMINAL = 1 LIMIT 1'
        )->fetchColumn());
    }

    public function testExistingInvoicePaymentRetryIsReusedInsideAuditGateway(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload(['emesa_abans_cobrament' => 1])
        );

        $command = $this->command($db);
        $payload = [
            'selector' => ['uuid_factura' => $invoice['uuid_factura']],
            'payment' => [
                'idempotency_key' => 'TRANSFERENCIA|REF:UC002-RETRY',
                'amount' => '120.00',
                'movement_date' => '2026-10-04 04:11:00',
                'method' => 'TRANSFERENCIA',
                'reference' => 'UC002-RETRY',
                'bank' => 'TEST',
            ],
        ];

        $first = $this->gateway($db)->run(
            $this->audit('22222222-2222-4222-8222-222222222222', 'TRANSFERENCIA|REF:UC002-RETRY'),
            static fn (\PDO $tx): array => $command->register($tx, $payload)
        );
        $second = $this->gateway($db)->run(
            $this->audit('33333333-3333-4333-8333-333333333333', 'TRANSFERENCIA|REF:UC002-RETRY'),
            static fn (\PDO $tx): array => $command->register($tx, $payload)
        );

        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_payment'], $second['uuid_payment']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(4, (int) $db->query('SELECT COUNT(*) FROM payment_action_event')->fetchColumn());
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM payment_action_event WHERE RESULT = 'REUSED'"
        )->fetchColumn());
    }

    private function command(\PDO $db): ExistingInvoicePaymentCommandService
    {
        $payments = new PaymentService(
            new TransactionRunner($db),
            new PaymentPayloadValidator(),
            new PaymentRepository(new UuidGenerator(), new PaymentStatusCalculator())
        );

        return new ExistingInvoicePaymentCommandService(
            new ManualPaymentService(
                new ManualPaymentInvoiceRepository(),
                new ManualPaymentPayloadBuilder(),
                $payments
            ),
            new ExistingInvoiceLegacyProjectionService()
        );
    }

    private function gateway(\PDO $db): PaymentActionGateway
    {
        return new PaymentActionGateway(
            $db,
            new TransactionRunner($db),
            new PaymentActionEventRepository(new UuidGenerator())
        );
    }

    private function audit(string $requestId, string $key): array
    {
        return [
            'request_id' => $requestId,
            'correlation_id' => $key,
            'action' => 'CREATE',
            'source_environment' => 'TEST',
            'source_channel' => 'INTRANET',
            'actor_type' => 'HUMAN',
            'actor_id' => 'tester',
            'actor_role' => 'GESTIO',
            'reason_code' => 'UC002_EXISTING_INVOICE_PAYMENT',
            'payment_idempotency_key' => $key,
            'occurred_at' => '2026-10-04 04:11:00.000000',
        ];
    }
}
