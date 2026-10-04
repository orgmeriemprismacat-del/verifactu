<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\PaymentStatusCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\PaymentActionEventRepository;
use Prisma\Sif\Repository\PaymentRepository;
use Prisma\Sif\Service\ClaimPaymentPayloadBuilder;
use Prisma\Sif\Service\ClaimPaymentService;
use Prisma\Sif\Service\PaymentActionGateway;
use Prisma\Sif\Service\PaymentPayloadValidator;
use Prisma\Sif\Service\PaymentService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ClaimPaymentAuditFlowTest
{
    public function testClaimPaymentAndTerminalAuditCommitTogether(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC024|AUDIT|INVOICE',
                'emesa_abans_cobrament' => 1,
            ])
        );

        $claim = $this->claimService($db);
        $gateway = $this->gateway($db);
        $input = [
            'amount' => '30.00',
            'movement_date' => '2026-10-04 02:00:00',
            'claim_reference' => 'CLAIM-AUDIT-1',
            'created_by' => 'gestio-test',
        ];

        $result = $gateway->run(
            $this->audit('req-uc024-1', 'corr-uc024-1'),
            fn (\PDO $tx): array => $claim->registerByUuidInTransaction(
                $tx,
                $invoice['uuid_factura'],
                $input
            )
        );

        Assert::same(true, $result['ok']);
        Assert::same(false, $result['idempotency_reused']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM payment_action_event')->fetchColumn());

        $events = $db->query(
            'SELECT ACTION, RESULT, IS_TERMINAL, UUID_PAYMENT, PAYMENT_IDEMPOTENCY_KEY,
                    REQUEST_ID, CORRELATION_ID, ACTOR_ID, ACTOR_ROLE
             FROM payment_action_event ORDER BY ID'
        )->fetchAll(\PDO::FETCH_ASSOC);

        Assert::same('LINK_CLAIM_PAYMENT', $events[0]['ACTION']);
        Assert::same('REQUESTED', $events[0]['RESULT']);
        Assert::same(0, (int) $events[0]['IS_TERMINAL']);
        Assert::same(null, $events[0]['UUID_PAYMENT']);
        Assert::same('gestio-test', $events[0]['ACTOR_ID']);
        Assert::same('FACTURACIO', $events[0]['ACTOR_ROLE']);

        Assert::same('SUCCEEDED', $events[1]['RESULT']);
        Assert::same(1, (int) $events[1]['IS_TERMINAL']);
        Assert::same($result['uuid_payment'], $events[1]['UUID_PAYMENT']);
        Assert::same('CLAIM|REF:CLAIM-AUDIT-1', $events[1]['PAYMENT_IDEMPOTENCY_KEY']);
        Assert::same('req-uc024-1', $events[1]['REQUEST_ID']);
        Assert::same('corr-uc024-1', $events[1]['CORRELATION_ID']);
    }

    public function testIdempotentClaimPaymentWritesReusedTerminalAuditWithoutSecondCharge(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC024|AUDIT|REUSE|INVOICE',
                'emesa_abans_cobrament' => 1,
            ])
        );

        $claim = $this->claimService($db);
        $gateway = $this->gateway($db);
        $input = [
            'amount' => '30.00',
            'movement_date' => '2026-10-04 02:05:00',
            'claim_reference' => 'CLAIM-AUDIT-REUSE',
            'created_by' => 'gestio-test',
        ];

        $first = $gateway->run(
            $this->audit('req-uc024-2a', 'corr-uc024-2'),
            fn (\PDO $tx): array => $claim->registerByUuidInTransaction(
                $tx,
                $invoice['uuid_factura'],
                $input
            )
        );
        $second = $gateway->run(
            $this->audit('req-uc024-2b', 'corr-uc024-2'),
            fn (\PDO $tx): array => $claim->registerByUuidInTransaction(
                $tx,
                $invoice['uuid_factura'],
                $input
            )
        );

        Assert::same($first['uuid_payment'], $second['uuid_payment']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same(4, (int) $db->query('SELECT COUNT(*) FROM payment_action_event')->fetchColumn());
        Assert::same('REUSED', (string) $db->query(
            'SELECT RESULT FROM payment_action_event ORDER BY ID DESC LIMIT 1'
        )->fetchColumn());
    }

    private function claimService(\PDO $db): ClaimPaymentService
    {
        return new ClaimPaymentService(
            new ManualPaymentInvoiceRepository(),
            new ClaimPaymentPayloadBuilder(),
            new PaymentService(
                new TransactionRunner($db),
                new PaymentPayloadValidator(),
                new PaymentRepository(new UuidGenerator(), new PaymentStatusCalculator())
            )
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

    private function audit(string $requestId, string $correlationId): array
    {
        return [
            'request_id' => $requestId,
            'correlation_id' => $correlationId,
            'action' => 'LINK_CLAIM_PAYMENT',
            'source_environment' => 'TEST',
            'source_channel' => 'INTRANET',
            'actor_type' => 'HUMAN',
            'actor_id' => 'gestio-test',
            'actor_role' => 'FACTURACIO',
            'reason_code' => 'CLAIM_PAYMENT_CONFIRMED',
            'occurred_at' => '2026-10-04 02:00:00.000000',
        ];
    }
}
