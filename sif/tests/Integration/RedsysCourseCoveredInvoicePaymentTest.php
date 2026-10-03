<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;
use Prisma\Sif\Repository\InvoiceBeforePaymentCoverageRepository;
use Prisma\Sif\Repository\LegacyCourseSnapshotRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Service\CourseEnrollmentFundAllocationService;
use Prisma\Sif\Service\InvoiceBeforePaymentPayloadBuilder;
use Prisma\Sif\Service\InvoiceBeforePaymentService;
use Prisma\Sif\Service\LegacyCourseInvoicePayloadBuilder;
use Prisma\Sif\Service\RedsysCourseInvoiceService;
use Prisma\Sif\Service\RedsysCoveredInvoicePaymentService;
use Prisma\Sif\Service\RedsysInvoicePayloadBuilder;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class RedsysCourseCoveredInvoicePaymentTest
{
    public function testFullRedsysPaymentReusesUc004InvoiceAndIsIdempotent(): void
    {
        $db = TestDatabase::fresh();
        $invoice = $this->issueBeforePayment($db, '95.50');
        $notifications = new RedsysNotificationRepository();
        $service = $this->service($db, $notifications);

        $this->notification($notifications, $db, 'UC003COVER001', '95.50');
        $snapshot = $this->snapshot('95.50', '95.50');

        $first = $service->issueFromIntentSnapshot($db, 'UC003COVER001', $snapshot);
        $second = $service->issueFromIntentSnapshot($db, 'UC003COVER001', $snapshot);

        Assert::same(true, $first['existing_invoice_payment']);
        Assert::same(true, $first['invoice_reused']);
        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same($invoice['uuid_factura'], $first['uuid_factura']);
        Assert::same($first['uuid_factura'], $second['uuid_factura']);
        Assert::same($first['uuid_payment'], $second['uuid_payment']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM fiscal_queue')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM enrollment_fund_movement')->fetchColumn());
        Assert::same('PAID', (string) $db->query('SELECT ESTAT_COBRAMENT FROM factura')->fetchColumn());
    }

    public function testPartialThenCompleteRedsysPaymentUsesOneUc004Invoice(): void
    {
        $db = TestDatabase::fresh();
        $invoice = $this->issueBeforePayment($db, '120.00');
        $notifications = new RedsysNotificationRepository();
        $service = $this->service($db, $notifications);

        $this->notification($notifications, $db, 'UC003PART0001', '50.00');
        $partial = $service->issueFromIntentSnapshot(
            $db,
            'UC003PART0001',
            $this->snapshot('50.00', '120.00')
        );

        Assert::same($invoice['uuid_factura'], $partial['uuid_factura']);
        Assert::same('PARTIALLY_PAID', (string) $db->query(
            'SELECT ESTAT_COBRAMENT FROM factura'
        )->fetchColumn());

        $this->notification($notifications, $db, 'UC003PART0002', '70.00');
        $complete = $service->issueFromIntentSnapshot(
            $db,
            'UC003PART0002',
            $this->snapshot('70.00', '120.00')
        );

        Assert::same($invoice['uuid_factura'], $complete['uuid_factura']);
        Assert::same('PAID', (string) $db->query(
            'SELECT ESTAT_COBRAMENT FROM factura'
        )->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM fiscal_queue')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM enrollment_fund_movement')->fetchColumn());
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

    public function testCoveredInvoiceRejectsRedsysOverpaymentBeforeCreatingSecondCharge(): void
    {
        $db = TestDatabase::fresh();
        $this->issueBeforePayment($db, '120.00');
        $notifications = new RedsysNotificationRepository();
        $service = $this->service($db, $notifications);

        $this->notification($notifications, $db, 'UC003OVER0001', '80.00');
        $service->issueFromIntentSnapshot(
            $db,
            'UC003OVER0001',
            $this->snapshot('80.00', '120.00')
        );

        $this->notification($notifications, $db, 'UC003OVER0002', '50.00');
        Assert::throws(SifException::class, function () use ($db, $service): void {
            $service->issueFromIntentSnapshot(
                $db,
                'UC003OVER0002',
                $this->snapshot('50.00', '120.00')
            );
        }, 409);

        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same('PARTIALLY_PAID', (string) $db->query(
            'SELECT ESTAT_COBRAMENT FROM factura'
        )->fetchColumn());
    }

    public function testRedsysInvoiceRaceGuardRefusesNewInvoiceWhenUc004CoverageExists(): void
    {
        $db = TestDatabase::fresh();
        $this->issueBeforePayment($db, '95.50');

        $payload = Fixtures::invoicePayload();
        $payload['idempotency_key'] = 'REDSYS|CURS|IDPAG:400|ORDER:RACEGUARD001';
        $payload['respect_uc004_coverage'] = 1;
        $payload['totals'] = $this->totals('95.50');
        $payload['lines'] = [$this->line('95.50')];
        $payload['relations'] = [[
            'source_type' => 'INSCRIPCIO',
            'source_id' => 410,
            'relation_type' => 'ORIGIN',
            'idpag' => 400,
            'ds_order' => 'RACEGUARD001',
            'visible_alumne' => 1,
        ]];

        Assert::throws(SifException::class, function () use ($db, $payload): void {
            IssueInvoiceTest::serviceFor($db)->issueInvoice($payload);
        }, 409);

        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura_registres')->fetchColumn());
    }

    private function service(
        \PDO $db,
        RedsysNotificationRepository $notifications
    ): RedsysCourseInvoiceService {
        $coverage = new InvoiceBeforePaymentCoverageRepository();

        return new RedsysCourseInvoiceService(
            $notifications,
            new LegacyCourseSnapshotRepository(),
            new LegacyCourseInvoicePayloadBuilder(),
            new RedsysInvoicePayloadBuilder($notifications),
            IssueInvoiceTest::serviceFor($db),
            null,
            null,
            null,
            '',
            'v1',
            new CourseEnrollmentFundAllocationService(
                new EnrollmentFundMovementRepository(new UuidGenerator())
            ),
            new RedsysCoveredInvoicePaymentService(
                $coverage,
                RegisterPaymentTest::paymentServiceFor($db)
            )
        );
    }

    private function issueBeforePayment(\PDO $db, string $total): array
    {
        $input = Fixtures::invoicePayload();
        $input['idempotency_key'] = 'INTRANET|FACTURA_ABANS_COBRAR|INSC:410';
        $input['source_channel'] = 'INTRANET';
        $input['totals'] = $this->totals($total);
        $input['lines'] = [$this->line($total)];
        $input['relations'] = [[
            'source_type' => 'INSCRIPCIO',
            'source_id' => 410,
            'relation_type' => 'ORIGIN',
            'factura_relacionada' => 810,
            'visible_alumne' => 1,
        ]];

        return (new InvoiceBeforePaymentService(
            new InvoiceBeforePaymentPayloadBuilder(),
            IssueInvoiceTest::serviceFor($db)
        ))->issueBeforePayment($input);
    }

    private function notification(
        RedsysNotificationRepository $notifications,
        \PDO $db,
        string $order,
        string $amount
    ): void {
        $notifications->recordReceived(
            $db,
            $order,
            400,
            $amount,
            '0000',
            true,
            ['source' => 'covered-invoice-test'],
            'VALIDATED'
        );
    }

    private function snapshot(string $amount, string $contractTotal): array
    {
        return [
            'inscription' => [
                'ID' => 410,
                'IDPAG' => 400,
                'ANY' => 2026,
                'MES' => '07',
                'CURS' => 'LM',
                'NOM' => 'Joan',
                'COGNOMS' => 'Mostra',
                'DNI' => '87654321Z',
                'CORREU' => 'joan@example.invalid',
                'A_PAGAR' => $contractTotal,
                'FACTURA_RELACIONADA' => 810,
            ],
            'course' => [
                'NOM_CURS' => 'Llenguatge musical',
                'DATAI' => '2026-07-01',
                'DATAF' => '2026-07-31',
                'HORES' => 30,
            ],
            'payment' => [
                'idpag' => 400,
                'amount' => $amount,
            ],
        ];
    }

    private function totals(string $total): array
    {
        return [
            'import_base' => $total,
            'discount' => '0.00',
            'taxable_base' => $total,
            'iva_regim' => 'EXEMPT',
            'iva_pct' => '0.00',
            'iva_import' => '0.00',
            'total' => $total,
        ];
    }

    private function line(string $total): array
    {
        return [
            'concept' => 'Curs Llenguatge musical',
            'detail' => 'Factura UC-004 de la inscripció 410',
            'quantity' => '1.00',
            'unit_price' => $total,
            'base' => $total,
            'import_base' => $total,
            'discount_amount' => '0.00',
            'taxable_base' => $total,
            'iva_regim' => 'EXEMPT',
            'iva_pct' => '0.00',
            'iva_import' => '0.00',
            'total' => $total,
            'source_type' => 'INSCRIPCIO',
            'source_id' => 410,
        ];
    }
}
