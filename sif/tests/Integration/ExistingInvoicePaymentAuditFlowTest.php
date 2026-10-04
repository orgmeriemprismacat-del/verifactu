<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\PaymentActionEventRepository;
use Prisma\Sif\Service\ExistingInvoiceLegacyProjectionService;
use Prisma\Sif\Service\ExistingInvoicePaymentCommandService;
use Prisma\Sif\Service\ManualPaymentPayloadBuilder;
use Prisma\Sif\Service\ManualPaymentService;
use Prisma\Sif\Service\PaymentActionGateway;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ExistingInvoicePaymentAuditFlowTest
{
    public function testCreateAndRetryWriteSucceededThenReusedAuditEvents(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC002|AUDIT|INVOICE',
                'emesa_abans_cobrament' => 1,
            ])
        );

        $paymentKey = 'INTRANET|UC002|REQ:11111111-1111-4111-8111-111111111111';
        $commandPayload = [
            'selector' => ['num_visible' => $invoice['num_visible']],
            'payment' => [
                'idempotency_key' => $paymentKey,
                'amount' => '120.00',
                'movement_date' => '2026-10-04 03:30:00',
                'bank' => 'CAIXA',
                'method' => 'TRANSFERENCIA',
            ],
        ];

        $command = new ExistingInvoicePaymentCommandService(
            new ManualPaymentService(
                new ManualPaymentInvoiceRepository(),
                new ManualPaymentPayloadBuilder(),
                RegisterPaymentTest::paymentServiceFor($db)
            ),
            new ExistingInvoiceLegacyProjectionService()
        );

        $gateway = new PaymentActionGateway(
            $db,
            new TransactionRunner($db),
            new PaymentActionEventRepository(new UuidGenerator())
        );

        $first = $gateway->run(
            $this->auditContext(
                'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
                $paymentKey
            ),
            static fn (\PDO $transactionDb): array => $command->register(
                $transactionDb,
                $commandPayload
            )
        );

        $second = $gateway->run(
            $this->auditContext(
                'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
                $paymentKey
            ),
            static fn (\PDO $transactionDb): array => $command->register(
                $transactionDb,
                $commandPayload
            )
        );

        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_payment'], $second['uuid_payment']);
        Assert::same(
            1,
            (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn()
        );

        $events = $db->query(
            "SELECT REQUEST_ID, CORRELATION_ID, PAYMENT_IDEMPOTENCY_KEY,
                    RESULT, IS_TERMINAL, UUID_PAYMENT
             FROM payment_action_event
             ORDER BY ID"
        )->fetchAll(\PDO::FETCH_ASSOC);

        Assert::same(4, count($events));
        Assert::same('REQUESTED', $events[0]['RESULT']);
        Assert::same('SUCCEEDED', $events[1]['RESULT']);
        Assert::same('REQUESTED', $events[2]['RESULT']);
        Assert::same('REUSED', $events[3]['RESULT']);

        Assert::same($paymentKey, $events[0]['CORRELATION_ID']);
        Assert::same($paymentKey, $events[3]['CORRELATION_ID']);
        Assert::same($paymentKey, $events[1]['PAYMENT_IDEMPOTENCY_KEY']);
        Assert::same($first['uuid_payment'], $events[1]['UUID_PAYMENT']);
        Assert::same($first['uuid_payment'], $events[3]['UUID_PAYMENT']);

        Assert::same(0, (int) $events[0]['IS_TERMINAL']);
        Assert::same(1, (int) $events[1]['IS_TERMINAL']);
        Assert::same(0, (int) $events[2]['IS_TERMINAL']);
        Assert::same(1, (int) $events[3]['IS_TERMINAL']);
    }

    private function auditContext(string $requestId, string $paymentKey): array
    {
        return [
            'request_id' => $requestId,
            'correlation_id' => $paymentKey,
            'action' => 'CREATE',
            'source_environment' => 'TEST',
            'source_channel' => 'INTRANET',
            'actor_type' => 'HUMAN',
            'actor_id' => 'uc002-test-user',
            'actor_role' => 'PABLO_GESTIO_SECRETARIA',
            'reason_code' => 'UC002_EXISTING_INVOICE_PAYMENT',
            'payment_idempotency_key' => $paymentKey,
            'occurred_at' => '2026-10-04 03:30:00.000000',
        ];
    }
}
