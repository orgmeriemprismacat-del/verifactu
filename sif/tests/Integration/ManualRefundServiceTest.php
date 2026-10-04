<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\PaymentStatusCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\CreditBalanceRepository;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\PaymentRepository;
use Prisma\Sif\Service\CourseEnrollmentFundAllocationService;
use Prisma\Sif\Service\CreditBalancePayloadBuilder;
use Prisma\Sif\Service\CreditBalanceService;
use Prisma\Sif\Service\ManualPaymentPayloadBuilder;
use Prisma\Sif\Service\ManualPaymentService;
use Prisma\Sif\Service\ManualRefundPayloadBuilder;
use Prisma\Sif\Service\ManualRefundService;
use Prisma\Sif\Service\PaymentPayloadValidator;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ManualRefundServiceTest
{
    public function testRegistersManualRefundAgainstExistingInvoiceByUuid(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload(['emesa_abans_cobrament' => 1])
        );
        $this->chargeService($db)->registerByUuid($db, $invoice['uuid_factura'], [
            'amount' => '120.00',
            'movement_date' => '2026-06-10 11:30:00',
            'reference' => 'TRF-REFUND-BASE',
            'bank' => 'CAIXA',
        ]);

        $service = $this->refundService($db);
        $input = [
            'amount' => '40.00',
            'movement_date' => '2026-06-12 12:00:00',
            'reference' => 'RET-001',
            'bank' => 'CAIXA',
            'notes' => 'Devolucio manual parcial',
        ];

        $first = $service->registerByUuid($db, $invoice['uuid_factura'], $input);
        $second = $service->registerByUuid($db, $invoice['uuid_factura'], $input);

        Assert::same(true, $first['ok']);
        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_payment'], $second['uuid_payment']);
        Assert::same($invoice['uuid_factura'], $first['uuid_factura']);
        Assert::same($invoice['num_visible'], $first['num_visible']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same('PARTIALLY_REFUNDED', (string) $db->query('SELECT ESTAT_COBRAMENT FROM factura')->fetchColumn());

        $refund = $db->query(
            'SELECT IDEMPOTENCY_KEY, TIPUS_MOVIMENT, METODE, SOURCE_CHANNEL, IMPORT, REFERENCIA_BANCARIA
             FROM payment_transaction
             WHERE TIPUS_MOVIMENT = \'REFUND\''
        )->fetch(\PDO::FETCH_ASSOC);

        Assert::same('REFUND|REF:RET-001', $refund['IDEMPOTENCY_KEY']);
        Assert::same('REFUND', $refund['TIPUS_MOVIMENT']);
        Assert::same('TRANSFERENCIA', $refund['METODE']);
        Assert::same('INTRANET', $refund['SOURCE_CHANNEL']);
        Assert::same('40.00', $refund['IMPORT']);
        Assert::same('RET-001', $refund['REFERENCIA_BANCARIA']);
    }

    public function testUsesExplicitRefundIdempotencyKeyAndReusesIt(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload(['emesa_abans_cobrament' => 1])
        );
        $this->chargeService($db)->registerByUuid($db, $invoice['uuid_factura'], [
            'amount' => '120.00',
            'movement_date' => '2026-10-04 02:30:00',
            'reference' => 'UC006-REFUND-EXPLICIT-BASE',
        ]);

        $service = $this->refundService($db);
        $input = [
            'idempotency_key' => 'REFUND|EXTERNAL|UC006|OP:ABC123',
            'amount' => '40.00',
            'movement_date' => '2026-10-04 02:31:00',
            'reference' => 'BANK-REF-ABC123',
        ];

        $first = $service->registerByUuid($db, $invoice['uuid_factura'], $input);
        $second = $service->registerByUuid($db, $invoice['uuid_factura'], $input);

        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_payment'], $second['uuid_payment']);
        Assert::same(
            'REFUND|EXTERNAL|UC006|OP:ABC123',
            (string) $db->query(
                "SELECT IDEMPOTENCY_KEY
                 FROM payment_transaction
                 WHERE TIPUS_MOVIMENT = 'REFUND'"
            )->fetchColumn()
        );
    }

    public function testRegistersRefundExitAgainstEnrollmentFundsIdempotently(): void
    {
        $db = TestDatabase::fresh();
        $invoice = $this->paidEnrollmentWithFunds($db, 'REFUND-FUND-001');

        $service = $this->refundService($db);
        $input = [
            'amount' => '40.00',
            'movement_date' => '2026-10-04 01:30:00',
            'reference' => 'REFUND-FUND-EXIT-001',
            'source_enrollment_id' => 10,
            'correlation_id' => 'UC006|TEST|REFUND|10',
        ];

        $first = $service->registerByUuid($db, $invoice['uuid_factura'], $input);
        $second = $service->registerByUuid($db, $invoice['uuid_factura'], $input);

        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_payment'], $second['uuid_payment']);
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM enrollment_fund_movement')->fetchColumn());

        $exit = $db->query(
            "SELECT MOVEMENT_TYPE, UUID_PAYMENT, ID_INSC_ORIGEN, ID_INSC_DESTI, IMPORT
             FROM enrollment_fund_movement
             WHERE MOVEMENT_TYPE = 'REFUND_EXIT'"
        )->fetch(\PDO::FETCH_ASSOC);

        Assert::same('REFUND_EXIT', $exit['MOVEMENT_TYPE']);
        Assert::same($first['uuid_payment'], $exit['UUID_PAYMENT']);
        Assert::same(10, (int) $exit['ID_INSC_ORIGEN']);
        Assert::same(null, $exit['ID_INSC_DESTI']);
        Assert::same('40.00', $exit['IMPORT']);

        $available = (new EnrollmentFundMovementRepository(new UuidGenerator()))
            ->availableAmountForInscription($db, 10);
        Assert::same('80.00', $available);
    }

    public function testRollsBackRefundWhenCreditAlreadyConsumedTheEnrollmentFunds(): void
    {
        $db = TestDatabase::fresh();
        $invoice = $this->paidEnrollmentWithFunds($db, 'REFUND-FUND-002');

        $this->creditService($db)->createCredit([
            'idempotency_key' => 'CREDIT|UC006|REFUND-CROSS|80',
            'holder_type' => 'STUDENT',
            'holder_id' => 10,
            'holder_name' => 'Client Exemple',
            'amount' => '80.00',
            'source_type' => 'BAIXA',
            'source_enrollment_id' => 10,
            'uuid_factura_origen' => $invoice['uuid_factura'],
        ]);

        Assert::throws(SifException::class, function () use ($db, $invoice): void {
            $this->refundService($db)->registerByUuid(
                $db,
                $invoice['uuid_factura'],
                [
                    'amount' => '50.00',
                    'movement_date' => '2026-10-04 01:45:00',
                    'reference' => 'REFUND-CROSS-FAIL',
                    'source_enrollment_id' => 10,
                ]
            );
        }, 409);

        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM payment_transaction WHERE TIPUS_MOVIMENT = 'CHARGE'"
        )->fetchColumn());
        Assert::same(0, (int) $db->query(
            "SELECT COUNT(*) FROM payment_transaction WHERE TIPUS_MOVIMENT = 'REFUND'"
        )->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM credit_balance')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM enrollment_fund_movement')->fetchColumn());
        Assert::same('PAID', (string) $db->query('SELECT ESTAT_COBRAMENT FROM factura')->fetchColumn());

        $available = (new EnrollmentFundMovementRepository(new UuidGenerator()))
            ->availableAmountForInscription($db, 10);
        Assert::same('40.00', $available);
    }

    public function testRegistersFullRefundByVisibleInvoiceNumber(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'MANUAL|REFUND|FULL',
                'emesa_abans_cobrament' => 1,
            ])
        );
        $this->chargeService($db)->registerByUuid($db, $invoice['uuid_factura'], [
            'amount' => '120.00',
            'movement_date' => '2026-06-10',
            'reference' => 'TRF-FULL-BASE',
        ]);

        $result = $this->refundService($db)->registerByNumVisible($db, $invoice['num_visible'], [
            'amount' => '120.00',
            'movement_date' => '2026-06-12',
            'reference' => 'RET-FULL-001',
        ]);

        Assert::same(true, $result['ok']);
        Assert::same($invoice['uuid_factura'], $result['uuid_factura']);
        Assert::same($invoice['num_visible'], $result['num_visible']);
        Assert::same('REFUNDED', (string) $db->query('SELECT ESTAT_COBRAMENT FROM factura')->fetchColumn());
    }

    public function testRejectsUnknownInvoiceBeforeRegisteringRefund(): void
    {
        $db = TestDatabase::fresh();

        Assert::throws(SifException::class, function () use ($db): void {
            $this->refundService($db)->registerByUuid($db, 'missing-invoice', [
                'amount' => '40.00',
                'movement_date' => '2026-06-12',
            ]);
        }, 422);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
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
                'movement_date' => '2026-10-04 01:00:00',
                'ds_order' => $order,
                'idpag' => 920,
            ],
            'relations' => [[
                'source_type' => 'INSCRIPCIO',
                'source_id' => 10,
                'factura_relacionada' => 920,
                'idpag' => 920,
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

    private function creditService(\PDO $db): CreditBalanceService
    {
        return new CreditBalanceService(
            new TransactionRunner($db),
            new CreditBalanceRepository(new UuidGenerator()),
            new ManualPaymentInvoiceRepository(),
            new CreditBalancePayloadBuilder(),
            new PaymentPayloadValidator(),
            new PaymentRepository(new UuidGenerator(), new PaymentStatusCalculator())
        );
    }

    private function chargeService(\PDO $db): ManualPaymentService
    {
        return new ManualPaymentService(
            new ManualPaymentInvoiceRepository(),
            new ManualPaymentPayloadBuilder(),
            RegisterPaymentTest::paymentServiceFor($db)
        );
    }

    private function refundService(\PDO $db): ManualRefundService
    {
        return new ManualRefundService(
            new ManualPaymentInvoiceRepository(),
            new ManualRefundPayloadBuilder(),
            RegisterPaymentTest::paymentServiceFor($db)
        );
    }
}
