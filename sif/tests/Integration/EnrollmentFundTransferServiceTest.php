<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;
use Prisma\Sif\Service\CourseEnrollmentFundAllocationService;
use Prisma\Sif\Service\EnrollmentFundTransferPayloadBuilder;
use Prisma\Sif\Service\EnrollmentFundTransferService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class EnrollmentFundTransferServiceTest
{
    public function testTransfersFundsBetweenEnrollmentsAndReusesWithNewCorrelation(): void
    {
        $db = TestDatabase::fresh();
        $this->seedExternalFunds($db, 410, '100.00', 1);
        $service = $this->service($db);

        $input = [
            'idempotency_key' => 'FUND|TRANSFER|UC006|A-B|60',
            'source_enrollment_id' => 410,
            'target_enrollment_id' => 420,
            'amount' => '60.00',
            'correlation_id' => 'TRANSFER-A',
        ];

        $first = $service->transfer($input);
        $input['correlation_id'] = 'TRANSFER-B';
        $second = $service->transfer($input);

        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($first['uuid_movement'], $second['uuid_movement']);
        Assert::same(410, $first['id_insc_origin']);
        Assert::same(420, $first['id_insc_destination']);
        Assert::same('60.00', $first['amount']);

        $funds = new EnrollmentFundMovementRepository(new UuidGenerator());
        Assert::same('40.00', $funds->availableAmountForInscription($db, 410));
        Assert::same('60.00', $funds->availableAmountForInscription($db, 420));
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM enrollment_fund_movement
             WHERE MOVEMENT_TYPE = 'INTERNAL_TRANSFER'"
        )->fetchColumn());
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM payment_transaction"
        )->fetchColumn());
    }

    public function testRejectsTransferAboveAvailableAmountWithoutPartialMovement(): void
    {
        $db = TestDatabase::fresh();
        $this->seedExternalFunds($db, 410, '100.00', 2);
        $service = $this->service($db);

        $service->transfer([
            'idempotency_key' => 'FUND|TRANSFER|UC006|A-B|80',
            'source_enrollment_id' => 410,
            'target_enrollment_id' => 420,
            'amount' => '80.00',
        ]);

        Assert::throws(SifException::class, static function () use ($service): void {
            $service->transfer([
                'idempotency_key' => 'FUND|TRANSFER|UC006|A-C|30',
                'source_enrollment_id' => 410,
                'target_enrollment_id' => 430,
                'amount' => '30.00',
            ]);
        }, 409);

        $funds = new EnrollmentFundMovementRepository(new UuidGenerator());
        Assert::same('20.00', $funds->availableAmountForInscription($db, 410));
        Assert::same('80.00', $funds->availableAmountForInscription($db, 420));
        Assert::same('0.00', $funds->availableAmountForInscription($db, 430));
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM enrollment_fund_movement
             WHERE MOVEMENT_TYPE = 'INTERNAL_TRANSFER'"
        )->fetchColumn());
    }

    public function testRejectsSameTransferKeyWithDifferentEconomicPayload(): void
    {
        $db = TestDatabase::fresh();
        $this->seedExternalFunds($db, 410, '100.00', 3);
        $service = $this->service($db);

        $service->transfer([
            'idempotency_key' => 'FUND|TRANSFER|UC006|CONFLICT',
            'source_enrollment_id' => 410,
            'target_enrollment_id' => 420,
            'amount' => '40.00',
        ]);

        Assert::throws(SifException::class, static function () use ($service): void {
            $service->transfer([
                'idempotency_key' => 'FUND|TRANSFER|UC006|CONFLICT',
                'source_enrollment_id' => 410,
                'target_enrollment_id' => 420,
                'amount' => '50.00',
            ]);
        }, 409);

        $funds = new EnrollmentFundMovementRepository(new UuidGenerator());
        Assert::same('60.00', $funds->availableAmountForInscription($db, 410));
        Assert::same('40.00', $funds->availableAmountForInscription($db, 420));
    }

    public function testSupportsChainedTransferWithoutCreatingNewCashMovement(): void
    {
        $db = TestDatabase::fresh();
        $this->seedExternalFunds($db, 410, '100.00', 4);
        $service = $this->service($db);

        $service->transfer([
            'idempotency_key' => 'FUND|TRANSFER|UC006|A-B|80|CHAIN',
            'source_enrollment_id' => 410,
            'target_enrollment_id' => 420,
            'amount' => '80.00',
        ]);
        $service->transfer([
            'idempotency_key' => 'FUND|TRANSFER|UC006|B-C|30|CHAIN',
            'source_enrollment_id' => 420,
            'target_enrollment_id' => 430,
            'amount' => '30.00',
        ]);

        $funds = new EnrollmentFundMovementRepository(new UuidGenerator());
        Assert::same('20.00', $funds->availableAmountForInscription($db, 410));
        Assert::same('50.00', $funds->availableAmountForInscription($db, 420));
        Assert::same('30.00', $funds->availableAmountForInscription($db, 430));
        Assert::same(2, (int) $db->query(
            "SELECT COUNT(*) FROM enrollment_fund_movement
             WHERE MOVEMENT_TYPE = 'INTERNAL_TRANSFER'"
        )->fetchColumn());
        Assert::same(1, (int) $db->query(
            "SELECT COUNT(*) FROM payment_transaction
             WHERE TIPUS_MOVIMENT = 'CHARGE'"
        )->fetchColumn());
    }

    public function testRejectsTransferToSameEnrollment(): void
    {
        $db = TestDatabase::fresh();
        $service = $this->service($db);

        Assert::throws(SifException::class, static function () use ($service): void {
            $service->transfer([
                'idempotency_key' => 'FUND|TRANSFER|UC006|SAME',
                'source_enrollment_id' => 410,
                'target_enrollment_id' => 410,
                'amount' => '10.00',
            ]);
        }, 422);
    }

    private function service(\PDO $db): EnrollmentFundTransferService
    {
        return new EnrollmentFundTransferService(
            new TransactionRunner($db),
            new EnrollmentFundMovementRepository(new UuidGenerator()),
            new EnrollmentFundTransferPayloadBuilder()
        );
    }

    private function seedExternalFunds(
        \PDO $db,
        int $idInsc,
        string $amount,
        int $ordinal
    ): void {
        $order = 'UC006-TRANSFER-' . str_pad((string) $ordinal, 4, '0', STR_PAD_LEFT);
        $idpag = 9000 + $ordinal;

        $payload = Fixtures::invoicePayload([
            'idempotency_key' => 'UC006|TRANSFER|INVOICE|' . $ordinal,
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
                'concept' => 'Curs origen UC-006',
                'detail' => 'Fons per traspassar',
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
                'factura_relacionada' => 9800 + $ordinal,
                'idpag' => $idpag,
                'ds_order' => $order,
                'visible_alumne' => 1,
            ]],
            'payment' => [
                'idempotency_key' => 'PAYMENT|UC006|TRANSFER|' . $ordinal,
                'movement_type' => 'CHARGE',
                'method' => 'REDSYS',
                'source_channel' => 'REDSYS',
                'amount' => $amount,
                'movement_date' => '2026-10-04 03:00:00',
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
                'inscription' => [
                    'ID' => $idInsc,
                    'A_PAGAR' => $amount,
                ],
                'payment' => [
                    'amount' => $amount,
                ],
            ],
            [
                'uuid_factura' => $invoice['uuid_factura'],
                'uuid_payment' => $invoice['uuid_payment'],
            ]
        );
    }
}
