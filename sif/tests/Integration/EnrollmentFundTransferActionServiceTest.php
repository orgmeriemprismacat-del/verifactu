<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;
use Prisma\Sif\Repository\PaymentActionEventRepository;
use Prisma\Sif\Service\CourseEnrollmentFundAllocationService;
use Prisma\Sif\Service\EnrollmentFundTransferActionService;
use Prisma\Sif\Service\EnrollmentFundTransferPayloadBuilder;
use Prisma\Sif\Service\EnrollmentFundTransferService;
use Prisma\Sif\Service\PaymentActionGateway;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class EnrollmentFundTransferActionServiceTest
{
    public function testAuditedTransferWritesRequestedSucceededAndReused(): void
    {
        $db = TestDatabase::fresh();
        $this->seedExternalFunds($db, 510, '100.00', 1);
        $service = $this->service($db);

        $input = [
            'idempotency_key' => 'FUND|TRANSFER|AUDIT|A-B|60',
            'source_enrollment_id' => 510,
            'target_enrollment_id' => 520,
            'amount' => '60.00',
        ];

        $first = $service->transfer($this->audit('req-transfer-1', 'corr-transfer-1'), $input);
        $second = $service->transfer($this->audit('req-transfer-2', 'corr-transfer-2'), $input);

        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);

        $rows = $db->query(
            "SELECT ACTION, RESULT, IS_TERMINAL, PAYMENT_IDEMPOTENCY_KEY
             FROM payment_action_event
             ORDER BY ID"
        )->fetchAll(\PDO::FETCH_ASSOC);

        Assert::same(4, count($rows));
        Assert::same('REALLOCATE', $rows[0]['ACTION']);
        Assert::same('REQUESTED', $rows[0]['RESULT']);
        Assert::same(0, (int) $rows[0]['IS_TERMINAL']);
        Assert::same('SUCCEEDED', $rows[1]['RESULT']);
        Assert::same(1, (int) $rows[1]['IS_TERMINAL']);
        Assert::same('REQUESTED', $rows[2]['RESULT']);
        Assert::same('REUSED', $rows[3]['RESULT']);
        Assert::same('FUND|TRANSFER|AUDIT|A-B|60', $rows[0]['PAYMENT_IDEMPOTENCY_KEY']);
    }

    public function testFailedAuditedTransferRollsBackAndWritesFailed(): void
    {
        $db = TestDatabase::fresh();
        $this->seedExternalFunds($db, 510, '100.00', 2);
        $service = $this->service($db);

        Assert::throws(SifException::class, function () use ($service): void {
            $service->transfer(
                $this->audit('req-transfer-fail', 'corr-transfer-fail'),
                [
                    'idempotency_key' => 'FUND|TRANSFER|AUDIT|FAIL|120',
                    'source_enrollment_id' => 510,
                    'target_enrollment_id' => 520,
                    'amount' => '120.00',
                ]
            );
        }, 409);

        $rows = $db->query(
            "SELECT ACTION, RESULT, IS_TERMINAL
             FROM payment_action_event
             ORDER BY ID"
        )->fetchAll(\PDO::FETCH_ASSOC);

        Assert::same(2, count($rows));
        Assert::same('REQUESTED', $rows[0]['RESULT']);
        Assert::same('FAILED', $rows[1]['RESULT']);
        Assert::same(1, (int) $rows[1]['IS_TERMINAL']);
        Assert::same(0, (int) $db->query(
            "SELECT COUNT(*) FROM enrollment_fund_movement
             WHERE MOVEMENT_TYPE = 'INTERNAL_TRANSFER'"
        )->fetchColumn());
    }

    public function testLongLedgerKeyIsHashedForPaymentAuditField(): void
    {
        $db = TestDatabase::fresh();
        $this->seedExternalFunds($db, 510, '100.00', 4);
        $service = $this->service($db);

        $key = 'FUND|TRANSFER|UC006|' . str_repeat('X', 130);

        $service->transfer(
            $this->audit('req-transfer-long-key', 'corr-transfer-long-key'),
            [
                'idempotency_key' => $key,
                'source_enrollment_id' => 510,
                'target_enrollment_id' => 520,
                'amount' => '40.00',
            ]
        );

        $auditKey = (string) $db->query(
            "SELECT PAYMENT_IDEMPOTENCY_KEY
             FROM payment_action_event
             WHERE ACTION = 'REALLOCATE'
             ORDER BY ID
             LIMIT 1"
        )->fetchColumn();

        Assert::same(
            'FUNDKEY|SHA256:' . hash('sha256', $key),
            $auditKey
        );
    }

    public function testAuditedReversalUsesUnallocateAction(): void
    {
        $db = TestDatabase::fresh();
        $this->seedExternalFunds($db, 510, '100.00', 3);
        $service = $this->service($db);

        $transfer = $service->transfer(
            $this->audit('req-transfer-base', 'corr-transfer-base'),
            [
                'idempotency_key' => 'FUND|TRANSFER|AUDIT|REVERSAL|60',
                'source_enrollment_id' => 510,
                'target_enrollment_id' => 520,
                'amount' => '60.00',
            ]
        );

        $reversal = $service->reverseTransfer(
            $this->audit('req-reversal-1', 'corr-reversal-1'),
            ['movement_uuid' => $transfer['uuid_movement']]
        );

        Assert::same(false, $reversal['idempotency_reused']);

        $rows = $db->query(
            "SELECT ACTION, RESULT, IS_TERMINAL
             FROM payment_action_event
             WHERE ACTION = 'UNALLOCATE'
             ORDER BY ID"
        )->fetchAll(\PDO::FETCH_ASSOC);

        Assert::same(2, count($rows));
        Assert::same('REQUESTED', $rows[0]['RESULT']);
        Assert::same('SUCCEEDED', $rows[1]['RESULT']);

        $funds = new EnrollmentFundMovementRepository(new UuidGenerator());
        Assert::same('100.00', $funds->availableAmountForInscription($db, 510));
        Assert::same('0.00', $funds->availableAmountForInscription($db, 520));
    }

    private function service(\PDO $db): EnrollmentFundTransferActionService
    {
        $builder = new EnrollmentFundTransferPayloadBuilder();
        $transfers = new EnrollmentFundTransferService(
            new TransactionRunner($db),
            new EnrollmentFundMovementRepository(new UuidGenerator()),
            $builder
        );
        $gateway = new PaymentActionGateway(
            $db,
            new TransactionRunner($db),
            new PaymentActionEventRepository(new UuidGenerator())
        );

        return new EnrollmentFundTransferActionService($gateway, $transfers);
    }

    private function audit(string $requestId, string $correlationId): array
    {
        return [
            'request_id' => $requestId,
            'correlation_id' => $correlationId,
            'source_environment' => 'TEST',
            'source_channel' => 'CLI',
            'actor_type' => 'HUMAN',
            'actor_id' => 'uc006-test',
            'actor_role' => 'GESTIO',
            'reason_code' => 'COURSE_CHANGE',
            'occurred_at' => '2026-10-04 04:00:00.000000',
        ];
    }

    private function seedExternalFunds(
        \PDO $db,
        int $idInsc,
        string $amount,
        int $ordinal
    ): void {
        $order = 'UC006-AUDIT-' . str_pad((string) $ordinal, 4, '0', STR_PAD_LEFT);
        $idpag = 9900 + $ordinal;

        $payload = Fixtures::invoicePayload([
            'idempotency_key' => 'UC006|AUDIT|INVOICE|' . $ordinal,
            'totals' => [
                'import_base' => $amount,
                'discount' => '0.00',
                'taxable_base' => $amount,
                'iva_regim' => 'EXEMPT',
                'iva_pct' => '0.00',
                'iva_import' => '0.00',
                'total' => $amount,
            ],
            'lines' => [[
                'concept' => 'Curs origen UC-006 audit',
                'detail' => 'Fons auditats',
                'quantity' => '1.00',
                'unit_price' => $amount,
                'base' => $amount,
                'import_base' => $amount,
                'discount_amount' => '0.00',
                'taxable_base' => $amount,
                'iva_regim' => 'EXEMPT',
                'iva_pct' => '0.00',
                'iva_import' => '0.00',
                'total' => $amount,
                'source_type' => 'INSCRIPCIO',
                'source_id' => $idInsc,
            ]],
            'relations' => [[
                'source_type' => 'INSCRIPCIO',
                'source_id' => $idInsc,
                'factura_relacionada' => 9950 + $ordinal,
                'idpag' => $idpag,
                'ds_order' => $order,
                'visible_alumne' => 1,
            ]],
            'payment' => [
                'idempotency_key' => 'PAYMENT|UC006|AUDIT|' . $ordinal,
                'movement_type' => 'CHARGE',
                'method' => 'REDSYS',
                'source_channel' => 'REDSYS',
                'amount' => $amount,
                'movement_date' => '2026-10-04 03:30:00',
                'ds_order' => $order,
                'idpag' => $idpag,
            ],
        ]);

        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice($payload);

        (new CourseEnrollmentFundAllocationService(
            new EnrollmentFundMovementRepository(new UuidGenerator())
        ))->allocate(
            $db,
            $order,
            [
                'inscription' => ['ID' => $idInsc, 'A_PAGAR' => $amount],
                'payment' => ['amount' => $amount],
            ],
            [
                'uuid_factura' => $invoice['uuid_factura'],
                'uuid_payment' => $invoice['uuid_payment'],
            ]
        );
    }
}
