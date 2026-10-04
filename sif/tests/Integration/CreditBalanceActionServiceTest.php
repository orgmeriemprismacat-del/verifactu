<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\PaymentStatusCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\CreditBalanceRepository;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\PaymentActionEventRepository;
use Prisma\Sif\Repository\PaymentRepository;
use Prisma\Sif\Service\CourseEnrollmentFundAllocationService;
use Prisma\Sif\Service\CreditBalanceActionService;
use Prisma\Sif\Service\CreditBalancePayloadBuilder;
use Prisma\Sif\Service\CreditBalanceService;
use Prisma\Sif\Service\PaymentActionGateway;
use Prisma\Sif\Service\PaymentPayloadValidator;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class CreditBalanceActionServiceTest
{
    public function testAuditedCreditCreationWritesRequestedSucceededAndReused(): void
    {
        $db = TestDatabase::fresh();
        $origin = $this->paidEnrollmentWithFunds($db, 'CREDIT-AUDIT-ORIGIN');
        $service = $this->actionService($db);

        $input = [
            'idempotency_key' => 'CREDIT|AUDIT|UC006|80',
            'holder_type' => 'STUDENT',
            'holder_id' => 10,
            'holder_name' => 'Client Exemple',
            'amount' => '80.00',
            'source_type' => 'CANVI_CURS',
            'source_enrollment_id' => 10,
            'uuid_factura_origen' => $origin['uuid_factura'],
        ];

        $first = $service->createCredit(
            $this->audit('req-credit-1', 'corr-credit-1'),
            $input
        );
        $second = $service->createCredit(
            $this->audit('req-credit-2', 'corr-credit-2'),
            $input
        );

        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_credit'], $second['uuid_credit']);

        $events = $db->query(
            "SELECT ACTION, RESULT, IS_TERMINAL, PAYMENT_IDEMPOTENCY_KEY,
                    CHANGESET_JSON
             FROM payment_action_event
             WHERE ACTION = 'CREATE'
             ORDER BY ID"
        )->fetchAll(\PDO::FETCH_ASSOC);

        Assert::same(4, count($events));
        Assert::same('REQUESTED', $events[0]['RESULT']);
        Assert::same('SUCCEEDED', $events[1]['RESULT']);
        Assert::same('REQUESTED', $events[2]['RESULT']);
        Assert::same('REUSED', $events[3]['RESULT']);
        Assert::same('CREDIT|AUDIT|UC006|80', $events[1]['PAYMENT_IDEMPOTENCY_KEY']);
        Assert::stringContainsString('CREDIT_BALANCE_CREATE', (string) $events[1]['CHANGESET_JSON']);

        Assert::same(1, (int) $db->query(
            'SELECT COUNT(*) FROM credit_balance'
        )->fetchColumn());
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM enrollment_fund_movement
             WHERE MOVEMENT_TYPE = 'CREDIT_CREATE'"
        )->fetchColumn());

        $funds = new EnrollmentFundMovementRepository(new UuidGenerator());
        Assert::same('40.00', $funds->availableAmountForInscription($db, 10));
    }

    public function testAuditedCompensationWritesRequestedSucceededAndReused(): void
    {
        $db = TestDatabase::fresh();
        $origin = $this->paidEnrollmentWithFunds($db, 'COMP-AUDIT-ORIGIN');
        $target = $this->pendingInvoiceForEnrollment($db, 20, 'COMP-AUDIT-TARGET');
        $service = $this->actionService($db);

        $credit = $service->createCredit(
            $this->audit('req-credit-base', 'corr-credit-base'),
            [
                'idempotency_key' => 'CREDIT|AUDIT|COMP|80',
                'holder_type' => 'STUDENT',
                'holder_id' => 10,
                'holder_name' => 'Client Exemple',
                'amount' => '80.00',
                'source_type' => 'CANVI_CURS',
                'source_enrollment_id' => 10,
                'uuid_factura_origen' => $origin['uuid_factura'],
            ]
        );

        $input = [
            'idempotency_key' => 'COMP|AUDIT|UC006|ORDER:A',
            'amount' => '20.00',
            'movement_date' => '2026-10-04 04:45:00',
            'target_enrollment_id' => 20,
        ];

        $first = $service->applyCreditByUuid(
            $this->audit('req-comp-1', 'corr-comp-1'),
            $credit['uuid_credit'],
            $target['uuid_factura'],
            $input
        );
        $second = $service->applyCreditByUuid(
            $this->audit('req-comp-2', 'corr-comp-2'),
            $credit['uuid_credit'],
            $target['uuid_factura'],
            $input
        );

        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_payment'], $second['uuid_payment']);

        $events = $db->query(
            "SELECT ACTION, RESULT, IS_TERMINAL, UUID_PAYMENT,
                    PAYMENT_IDEMPOTENCY_KEY, CHANGESET_JSON
             FROM payment_action_event
             WHERE ACTION = 'LINK_COMPENSATION'
             ORDER BY ID"
        )->fetchAll(\PDO::FETCH_ASSOC);

        Assert::same(4, count($events));
        Assert::same('REQUESTED', $events[0]['RESULT']);
        Assert::same('SUCCEEDED', $events[1]['RESULT']);
        Assert::same('REQUESTED', $events[2]['RESULT']);
        Assert::same('REUSED', $events[3]['RESULT']);
        Assert::same($first['uuid_payment'], $events[1]['UUID_PAYMENT']);
        Assert::same('COMP|AUDIT|UC006|ORDER:A', $events[1]['PAYMENT_IDEMPOTENCY_KEY']);
        Assert::stringContainsString('CREDIT_COMPENSATION', (string) $events[1]['CHANGESET_JSON']);

        Assert::same('60.00', (string) $db->query(
            'SELECT IMPORT_DISPONIBLE FROM credit_balance'
        )->fetchColumn());
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM payment_transaction
             WHERE TIPUS_MOVIMENT = 'COMPENSATION'"
        )->fetchColumn());
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM enrollment_fund_movement
             WHERE MOVEMENT_TYPE = 'COMPENSATION_ALLOCATION'"
        )->fetchColumn());
    }

    public function testFailedAuditedCompensationRollsBackAndWritesFailed(): void
    {
        $db = TestDatabase::fresh();
        $origin = $this->paidEnrollmentWithFunds($db, 'COMP-AUDIT-FAIL-ORIGIN');
        $target = $this->pendingInvoiceForEnrollment($db, 20, 'COMP-AUDIT-FAIL-TARGET');
        $service = $this->actionService($db);

        $credit = $service->createCredit(
            $this->audit('req-credit-fail-base', 'corr-credit-fail-base'),
            [
                'idempotency_key' => 'CREDIT|AUDIT|COMP|FAIL|80',
                'holder_type' => 'STUDENT',
                'holder_id' => 10,
                'holder_name' => 'Client Exemple',
                'amount' => '80.00',
                'source_type' => 'CANVI_CURS',
                'source_enrollment_id' => 10,
                'uuid_factura_origen' => $origin['uuid_factura'],
            ]
        );

        Assert::throws(SifException::class, function () use ($service, $credit, $target): void {
            $service->applyCreditByUuid(
                $this->audit('req-comp-fail', 'corr-comp-fail'),
                $credit['uuid_credit'],
                $target['uuid_factura'],
                [
                    'idempotency_key' => 'COMP|AUDIT|UC006|FAIL',
                    'amount' => '20.00',
                    'movement_date' => '2026-10-04 04:46:00',
                    'target_enrollment_id' => 999,
                ]
            );
        }, 409);

        $events = $db->query(
            "SELECT ACTION, RESULT, IS_TERMINAL
             FROM payment_action_event
             WHERE ACTION = 'LINK_COMPENSATION'
             ORDER BY ID"
        )->fetchAll(\PDO::FETCH_ASSOC);

        Assert::same(2, count($events));
        Assert::same('REQUESTED', $events[0]['RESULT']);
        Assert::same('FAILED', $events[1]['RESULT']);
        Assert::same(0, (int) $db->query(
            "SELECT COUNT(*) FROM payment_transaction
             WHERE TIPUS_MOVIMENT = 'COMPENSATION'"
        )->fetchColumn());
        Assert::same(0, (int) $db->query(
            "SELECT COUNT(*) FROM enrollment_fund_movement
             WHERE MOVEMENT_TYPE = 'COMPENSATION_ALLOCATION'"
        )->fetchColumn());
        Assert::same('80.00', (string) $db->query(
            'SELECT IMPORT_DISPONIBLE FROM credit_balance'
        )->fetchColumn());
    }

    private function actionService(\PDO $db): CreditBalanceActionService
    {
        $credits = new CreditBalanceService(
            new TransactionRunner($db),
            new CreditBalanceRepository(new UuidGenerator()),
            new ManualPaymentInvoiceRepository(),
            new CreditBalancePayloadBuilder(),
            new PaymentPayloadValidator(),
            new PaymentRepository(new UuidGenerator(), new PaymentStatusCalculator())
        );
        $gateway = new PaymentActionGateway(
            $db,
            new TransactionRunner($db),
            new PaymentActionEventRepository(new UuidGenerator())
        );

        return new CreditBalanceActionService($gateway, $credits);
    }

    private function audit(string $requestId, string $correlationId): array
    {
        return [
            'request_id' => $requestId,
            'correlation_id' => $correlationId,
            'source_environment' => 'TEST',
            'source_channel' => 'CLI',
            'actor_type' => 'HUMAN',
            'actor_id' => 'uc006-credit-test',
            'actor_role' => 'GESTIO',
            'reason_code' => 'UC006_DECISION',
            'occurred_at' => '2026-10-04 04:40:00.000000',
        ];
    }

    private function paidEnrollmentWithFunds(\PDO $db, string $order): array
    {
        $payload = Fixtures::invoicePayload([
            'idempotency_key' => 'INVOICE|' . $order,
            'payment' => [
                'idempotency_key' => 'PAYMENT|' . $order,
                'movement_type' => 'CHARGE',
                'method' => 'REDSYS',
                'source_channel' => 'REDSYS',
                'amount' => '120.00',
                'movement_date' => '2026-10-04 04:35:00',
                'ds_order' => $order,
                'idpag' => 9960,
            ],
            'relations' => [[
                'source_type' => 'INSCRIPCIO',
                'source_id' => 10,
                'factura_relacionada' => 9960,
                'idpag' => 9960,
                'ds_order' => $order,
                'visible_alumne' => 1,
            ]],
        ]);

        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice($payload);
        (new CourseEnrollmentFundAllocationService(
            new EnrollmentFundMovementRepository(new UuidGenerator())
        ))->allocate(
            $db,
            $order,
            [
                'inscription' => ['ID' => 10, 'A_PAGAR' => '120.00'],
                'payment' => ['amount' => '120.00'],
            ],
            $invoice
        );

        return $invoice;
    }

    private function pendingInvoiceForEnrollment(
        \PDO $db,
        int $idInsc,
        string $key
    ): array {
        return IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'INVOICE|' . $key,
                'emesa_abans_cobrament' => 1,
                'lines' => [[
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => $idInsc,
                ]],
                'relations' => [[
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => $idInsc,
                    'factura_relacionada' => 9970 + $idInsc,
                    'idpag' => 9970 + $idInsc,
                    'ds_order' => $key,
                    'visible_alumne' => 1,
                ]],
            ])
        );
    }
}
