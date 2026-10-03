<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\PaymentActionEventRepository;
use Prisma\Sif\Service\ManualPaymentPayloadBuilder;
use Prisma\Sif\Service\ManualPaymentService;
use Prisma\Sif\Service\ManualTransferCommandService;
use Prisma\Sif\Service\PaymentActionGateway;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ManualTransferAuditedFlowTest
{
    public function testManualTransferAndTerminalAuditCommitAtomically(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC022|AUDIT|INVOICE',
                'emesa_abans_cobrament' => 1,
            ])
        );

        $service = new ManualTransferCommandService(
            new ManualPaymentService(
                new ManualPaymentInvoiceRepository(),
                new ManualPaymentPayloadBuilder(),
                RegisterPaymentTest::paymentServiceFor($db)
            ),
            ['PAYMENT_WRITE'],
            new PaymentActionGateway(
                $db,
                new TransactionRunner($db),
                new PaymentActionEventRepository(new UuidGenerator())
            ),
            'test'
        );

        $result = $service->register($db, [
            'actor_id' => 'operator@example.test',
            'roles' => ['PAYMENT_WRITE'],
            'request_id' => '22222222-2222-4222-8222-222222222222',
        ], [
            'uuid_factura' => $invoice['uuid_factura'],
            'amount' => '120.00',
            'movement_date' => '2026-10-03 18:15:00',
            'external_bank_event_id' => 'BANK-EVENT-AUDIT-1',
            'reference' => 'TRANSFER TEST',
            'bank' => 'BANC TEST',
            'correlation_id' => 'uc022-audit-flow-1',
        ]);

        Assert::same('CREATED', $result['status']);
        Assert::same(
            'TRANSFERENCIA|BANK_EVENT:BANK-EVENT-AUDIT-1',
            $result['payment_idempotency_key']
        );

        $paymentCount = (int) $db->query(
            "SELECT COUNT(*) FROM payment_transaction
             WHERE IDEMPOTENCY_KEY = 'TRANSFERENCIA|BANK_EVENT:BANK-EVENT-AUDIT-1'"
        )->fetchColumn();
        Assert::same(1, $paymentCount);

        $requestedCount = (int) $db->query(
            "SELECT COUNT(*) FROM payment_action_event
             WHERE REQUEST_ID = '22222222-2222-4222-8222-222222222222'
               AND ACTION = 'CREATE'
               AND RESULT = 'REQUESTED'
               AND SOURCE_CHANNEL = 'INTRANET'
               AND SOURCE_ENVIRONMENT = 'TEST'"
        )->fetchColumn();
        Assert::same(1, $requestedCount);

        $terminal = $db->query(
            "SELECT RESULT, UUID_PAYMENT, PAYMENT_IDEMPOTENCY_KEY, CORRELATION_ID,
                    ACTOR_ID, ACTOR_ROLE, REASON_CODE
             FROM payment_action_event
             WHERE REQUEST_ID = '22222222-2222-4222-8222-222222222222'
               AND RESULT = 'SUCCEEDED'"
        )->fetch(\PDO::FETCH_ASSOC);

        Assert::same('SUCCEEDED', $terminal['RESULT']);
        Assert::same($result['uuid_payment'], $terminal['UUID_PAYMENT']);
        Assert::same($result['payment_idempotency_key'], $terminal['PAYMENT_IDEMPOTENCY_KEY']);
        Assert::same('uc022-audit-flow-1', $terminal['CORRELATION_ID']);
        Assert::same('operator@example.test', $terminal['ACTOR_ID']);
        Assert::same('PAYMENT_WRITE', $terminal['ACTOR_ROLE']);
        Assert::same('UC-022', $terminal['REASON_CODE']);
    }

    public function testIdempotentReuseIsAuditedAsReusedWithoutSecondPayment(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC022|AUDIT|REUSE|INVOICE',
                'emesa_abans_cobrament' => 1,
            ])
        );

        $service = new ManualTransferCommandService(
            new ManualPaymentService(
                new ManualPaymentInvoiceRepository(),
                new ManualPaymentPayloadBuilder(),
                RegisterPaymentTest::paymentServiceFor($db)
            ),
            ['PAYMENT_WRITE'],
            new PaymentActionGateway(
                $db,
                new TransactionRunner($db),
                new PaymentActionEventRepository(new UuidGenerator())
            ),
            'test'
        );

        $payload = [
            'uuid_factura' => $invoice['uuid_factura'],
            'amount' => '120.00',
            'movement_date' => '2026-10-03 18:30:00',
            'external_bank_event_id' => 'BANK-EVENT-AUDIT-REUSE',
            'reference' => 'TRANSFER REUSE',
            'bank' => 'BANC TEST',
        ];

        $first = $service->register($db, [
            'actor_id' => 'operator@example.test',
            'roles' => ['PAYMENT_WRITE'],
            'request_id' => '33333333-3333-4333-8333-333333333333',
        ], $payload);

        $second = $service->register($db, [
            'actor_id' => 'operator@example.test',
            'roles' => ['PAYMENT_WRITE'],
            'request_id' => '44444444-4444-4444-8444-444444444444',
        ], $payload);

        Assert::same('CREATED', $first['status']);
        Assert::same('REUSED', $second['status']);
        Assert::same($first['uuid_payment'], $second['uuid_payment']);

        Assert::same(
            1,
            (int) $db->query(
                "SELECT COUNT(*) FROM payment_transaction
                 WHERE IDEMPOTENCY_KEY = 'TRANSFERENCIA|BANK_EVENT:BANK-EVENT-AUDIT-REUSE'"
            )->fetchColumn()
        );

        Assert::same(
            1,
            (int) $db->query(
                "SELECT COUNT(*) FROM payment_action_event
                 WHERE REQUEST_ID = '44444444-4444-4444-8444-444444444444'
                   AND RESULT = 'REUSED'"
            )->fetchColumn()
        );
    }
}
