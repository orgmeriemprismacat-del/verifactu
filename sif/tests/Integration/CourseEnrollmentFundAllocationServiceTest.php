<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;
use Prisma\Sif\Service\CourseEnrollmentFundAllocationService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class CourseEnrollmentFundAllocationServiceTest
{
    public function testAllocatesOneCoursePaymentToItsInscriptionAndReusesOnRetry(): void
    {
        $db = TestDatabase::fresh();
        [$uuidFactura, $uuidPayment] = $this->persistInvoiceAndPayment(
            $db,
            410,
            '95.50'
        );

        $service = $this->service();
        $snapshot = $this->snapshot(410, '95.50');

        $first = $service->allocate(
            $db,
            'COURSEFUND0001',
            $snapshot,
            [
                'uuid_factura' => $uuidFactura,
                'uuid_payment' => $uuidPayment,
            ]
        );
        $second = $service->allocate(
            $db,
            'COURSEFUND0001',
            $snapshot,
            [
                'uuid_factura' => $uuidFactura,
                'uuid_payment' => $uuidPayment,
            ]
        );

        Assert::same(1, $first['count']);
        Assert::same('95.50', $first['amount']);
        Assert::same(false, $first['movements'][0]['idempotency_reused']);
        Assert::same(true, $second['movements'][0]['idempotency_reused']);
        Assert::same(
            $first['movements'][0]['uuid_movement'],
            $second['movements'][0]['uuid_movement']
        );
        Assert::same(1, (int) $db->query(
            'SELECT COUNT(*) FROM enrollment_fund_movement'
        )->fetchColumn());

        $row = $db->query(
            'SELECT IDEMPOTENCY_KEY, MOVEMENT_TYPE, ORDRE, UUID_PAYMENT,
                    UUID_FACTURA, ID_INSC_ORIGEN, ID_INSC_DESTI, IMPORT,
                    CURRENCY, CORRELATION_ID
             FROM enrollment_fund_movement'
        )->fetch(\PDO::FETCH_ASSOC);

        Assert::same('FUND|CURS|ORDER:COURSEFUND0001|INSC:410', $row['IDEMPOTENCY_KEY']);
        Assert::same('EXTERNAL_ALLOCATION', $row['MOVEMENT_TYPE']);
        Assert::same(1, (int) $row['ORDRE']);
        Assert::same($uuidPayment, $row['UUID_PAYMENT']);
        Assert::same($uuidFactura, $row['UUID_FACTURA']);
        Assert::same(null, $row['ID_INSC_ORIGEN']);
        Assert::same(410, (int) $row['ID_INSC_DESTI']);
        Assert::same('95.50', $row['IMPORT']);
        Assert::same('EUR', $row['CURRENCY']);
        Assert::same('REDSYS|COURSEFUND0001', $row['CORRELATION_ID']);
    }

    public function testEachFractionCreatesItsOwnMovementAgainstItsOwnInvoiceLine(): void
    {
        $db = TestDatabase::fresh();
        $service = $this->service();

        [$invoiceOne, $paymentOne] = $this->persistInvoiceAndPayment($db, 410, '50.00');
        [$invoiceTwo, $paymentTwo] = $this->persistInvoiceAndPayment($db, 410, '70.00');

        $service->allocate(
            $db,
            'COURSEPART0001',
            $this->snapshot(410, '50.00', '120.00'),
            ['uuid_factura' => $invoiceOne, 'uuid_payment' => $paymentOne]
        );
        $service->allocate(
            $db,
            'COURSEPART0002',
            $this->snapshot(410, '70.00', '120.00'),
            ['uuid_factura' => $invoiceTwo, 'uuid_payment' => $paymentTwo]
        );

        Assert::same(2, (int) $db->query(
            'SELECT COUNT(*) FROM enrollment_fund_movement'
        )->fetchColumn());
        Assert::same(
            '120.00',
            number_format(
                (float) $db->query(
                    'SELECT SUM(IMPORT) FROM enrollment_fund_movement WHERE ID_INSC_DESTI = 410'
                )->fetchColumn(),
                2,
                '.',
                ''
            )
        );
    }

    public function testRejectsAllocationWhenPaymentAmountDoesNotMatchSnapshot(): void
    {
        $db = TestDatabase::fresh();
        [$uuidFactura, $uuidPayment] = $this->persistInvoiceAndPayment(
            $db,
            410,
            '95.50'
        );

        Assert::throws(SifException::class, function () use ($db, $uuidFactura, $uuidPayment): void {
            $this->service()->allocate(
                $db,
                'COURSEMISMATCH1',
                $this->snapshot(410, '90.00'),
                [
                    'uuid_factura' => $uuidFactura,
                    'uuid_payment' => $uuidPayment,
                ]
            );
        }, 409);

        Assert::same(0, (int) $db->query(
            'SELECT COUNT(*) FROM enrollment_fund_movement'
        )->fetchColumn());
    }

    private function service(): CourseEnrollmentFundAllocationService
    {
        return new CourseEnrollmentFundAllocationService(
            new EnrollmentFundMovementRepository(new UuidGenerator())
        );
    }

    private function snapshot(int $idInsc, string $amount, string $contractTotal = '95.50'): array
    {
        return [
            'inscription' => [
                'ID' => $idInsc,
                'A_PAGAR' => $contractTotal,
            ],
            'payment' => [
                'amount' => $amount,
            ],
        ];
    }

    private function persistInvoiceAndPayment(
        \PDO $db,
        int $idInsc,
        string $amount
    ): array {
        $ordinal = (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn() + 1;
        $order = 'COURSE-FUND-' . str_pad((string) $ordinal, 4, '0', STR_PAD_LEFT);
        $idpag = 400 + $ordinal;

        $payload = Fixtures::invoicePayload([
            'idempotency_key' => 'REDSYS|CURS|IDPAG:' . $idpag . '|ORDER:' . $order,
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
                'concept' => 'Curs',
                'detail' => 'Tram pagat',
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
                'factura_relacionada' => 800 + $ordinal,
                'idpag' => $idpag,
                'ds_order' => $order,
                'visible_alumne' => 1,
            ]],
            'payment' => [
                'idempotency_key' => 'PAYMENT|REDSYS|ORDER:' . $order,
                'movement_type' => 'CHARGE',
                'method' => 'REDSYS',
                'source_channel' => 'REDSYS',
                'amount' => $amount,
                'movement_date' => '2026-10-02 01:00:00',
                'ds_order' => $order,
                'idpag' => $idpag,
            ],
        ]);

        $result = IssueInvoiceTest::serviceFor($db)->issueInvoice($payload);

        return [
            (string) $result['uuid_factura'],
            (string) $result['uuid_payment'],
        ];
    }
}
