<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;
use Prisma\Sif\Service\ExistingInvoiceEnrollmentFundAllocationService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ExistingInvoiceEnrollmentFundAllocationServiceTest
{
    public function testSplitsPartialPaymentAcrossInvoiceInscriptionLines(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            $this->twoLineInvoicePayload()
        );

        $payment = RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' => 'INTRANET|UC002|FUND|PAYMENT-1',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '90.00',
            'movement_date' => '2026-10-04 04:30:00',
            'allocations' => [[
                'uuid_factura' => $invoice['uuid_factura'],
                'amount' => '90.00',
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ]);

        $db->beginTransaction();
        $result = $this->service()->allocate(
            $db,
            $payment['uuid_payment'],
            $invoice['uuid_factura'],
            'INTRANET|UC002|FUND|PAYMENT-1'
        );
        $db->commit();

        Assert::same(2, $result['count']);
        Assert::same('90.00', $result['amount']);

        $rows = $db->query(
            'SELECT ID_INSC_DESTI, IMPORT
             FROM enrollment_fund_movement
             ORDER BY ORDRE'
        )->fetchAll(\PDO::FETCH_ASSOC);

        Assert::same(2, count($rows));
        Assert::same(10, (int) $rows[0]['ID_INSC_DESTI']);
        Assert::same('60.00', $rows[0]['IMPORT']);
        Assert::same(11, (int) $rows[1]['ID_INSC_DESTI']);
        Assert::same('30.00', $rows[1]['IMPORT']);
    }

    public function testRetryReusesSameEnrollmentFundMovements(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            $this->twoLineInvoicePayload([
                'idempotency_key' => 'UC002|FUND|INVOICE-RETRY',
            ])
        );

        $payment = RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' => 'INTRANET|UC002|FUND|PAYMENT-RETRY',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '90.00',
            'movement_date' => '2026-10-04 04:31:00',
            'allocations' => [[
                'uuid_factura' => $invoice['uuid_factura'],
                'amount' => '90.00',
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ]);

        $service = $this->service();

        $db->beginTransaction();
        $first = $service->allocate(
            $db,
            $payment['uuid_payment'],
            $invoice['uuid_factura'],
            'INTRANET|UC002|FUND|PAYMENT-RETRY'
        );
        $db->commit();

        $db->beginTransaction();
        $second = $service->allocate(
            $db,
            $payment['uuid_payment'],
            $invoice['uuid_factura'],
            'INTRANET|UC002|FUND|PAYMENT-RETRY'
        );
        $db->commit();

        Assert::same(2, $first['count']);
        Assert::same(2, $second['count']);
        Assert::same(
            2,
            (int) $db->query(
                'SELECT COUNT(*) FROM enrollment_fund_movement'
            )->fetchColumn()
        );
        Assert::same(
            2,
            count(array_filter(
                $second['movements'],
                static fn (array $movement): bool =>
                    ($movement['idempotency_reused'] ?? false) === true
            ))
        );
    }

    private function service(): ExistingInvoiceEnrollmentFundAllocationService
    {
        return new ExistingInvoiceEnrollmentFundAllocationService(
            new EnrollmentFundMovementRepository(new UuidGenerator())
        );
    }

    private function twoLineInvoicePayload(array $overrides = []): array
    {
        return array_replace_recursive(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC002|FUND|INVOICE',
                'emesa_abans_cobrament' => 1,
                'totals' => [
                    'import_base' => '120.00',
                    'taxable_base' => '120.00',
                    'total' => '120.00',
                ],
                'lines' => [
                    [
                        'concept' => 'Curs A',
                        'detail' => 'Inscripció A',
                        'quantity' => '1.00',
                        'unit_price' => '60.00',
                        'base' => '60.00',
                        'import_base' => '60.00',
                        'discount_amount' => '0.00',
                        'taxable_base' => '60.00',
                        'iva_regim' => 'EXEMPT',
                        'iva_pct' => '0.00',
                        'iva_import' => '0.00',
                        'total' => '60.00',
                        'source_type' => 'INSCRIPCIO',
                        'source_id' => 10,
                    ],
                    [
                        'concept' => 'Curs B',
                        'detail' => 'Inscripció B',
                        'quantity' => '1.00',
                        'unit_price' => '60.00',
                        'base' => '60.00',
                        'import_base' => '60.00',
                        'discount_amount' => '0.00',
                        'taxable_base' => '60.00',
                        'iva_regim' => 'EXEMPT',
                        'iva_pct' => '0.00',
                        'iva_import' => '0.00',
                        'total' => '60.00',
                        'source_type' => 'INSCRIPCIO',
                        'source_id' => 11,
                    ],
                ],
                'relations' => [
                    [
                        'source_type' => 'INSCRIPCIO',
                        'source_id' => 10,
                        'factura_relacionada' => 900,
                        'idpag' => 901,
                        'visible_alumne' => 1,
                    ],
                    [
                        'source_type' => 'INSCRIPCIO',
                        'source_id' => 11,
                        'factura_relacionada' => 900,
                        'idpag' => 901,
                        'visible_alumne' => 1,
                    ],
                ],
            ]),
            $overrides
        );
    }
}
