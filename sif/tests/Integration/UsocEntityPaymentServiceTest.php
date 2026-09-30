<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\UsocFinancingCaseRepository;
use Prisma\Sif\Service\ManualPaymentPayloadBuilder;
use Prisma\Sif\Service\ManualPaymentService;
use Prisma\Sif\Service\UsocCaseReconciler;
use Prisma\Sif\Service\UsocEntityPaymentService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class UsocEntityPaymentServiceTest
{
    public function testPartialAndCompleteEntityPaymentsReconcileUsocCase(): void
    {
        $db = TestDatabase::fresh();
        $invoiceService = IssueInvoiceTest::serviceFor($db);

        $student = $invoiceService->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'REDSYS|USOC_ALUMNE|IDPAG:980|ORDER:ORDERUSOC980',
            'source_channel' => 'REDSYS',
            'totals' => [
                'import_base' => '75.00',
                'taxable_base' => '75.00',
                'total' => '75.00',
            ],
            'lines' => [[
                'unit_price' => '75.00',
                'base' => '75.00',
                'import_base' => '75.00',
                'taxable_base' => '75.00',
                'total' => '75.00',
            ]],
            'relations' => [[
                'source_type' => 'INSCRIPCIO',
                'source_id' => 880,
                'idpag' => 980,
                'ds_order' => 'ORDERUSOC980',
                'visible_alumne' => 1,
            ]],
        ]));
        $db->prepare('UPDATE factura SET ESTAT_COBRAMENT = \'PAID\' WHERE UUID_FACTURA = ?')
            ->execute([$student['uuid_factura']]);

        $entity = $invoiceService->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'INTRANET|USOC_ENTITAT|ID_INSC:880|FACT_ALUMNE:' . $student['uuid_factura'],
            'source_channel' => 'INTRANET',
            'totals' => [
                'import_base' => '25.00',
                'taxable_base' => '25.00',
                'total' => '25.00',
            ],
            'lines' => [[
                'unit_price' => '25.00',
                'base' => '25.00',
                'import_base' => '25.00',
                'taxable_base' => '25.00',
                'total' => '25.00',
            ]],
            'relations' => [[
                'source_type' => 'INSCRIPCIO',
                'source_id' => 880,
                'relation_type' => 'USOC_ENTITY',
                'idpag' => 980,
                'visible_alumne' => 0,
            ]],
        ]));

        $cases = new UsocFinancingCaseRepository(new UuidGenerator());
        $cases->recordStudentInvoice(
            $db,
            880,
            980,
            $student['uuid_factura'],
            '75.00',
            '25.00',
            'ORDERUSOC980'
        );
        $cases->recordEntityInvoice(
            $db,
            880,
            980,
            $student['uuid_factura'],
            $entity['uuid_factura'],
            '75.00',
            '25.00',
            'ORDERUSOC980'
        );

        $service = new UsocEntityPaymentService(
            $cases,
            new ManualPaymentService(
                new ManualPaymentInvoiceRepository(),
                new ManualPaymentPayloadBuilder(),
                RegisterPaymentTest::paymentServiceFor($db)
            ),
            new UsocCaseReconciler($cases)
        );

        $partial = $service->registerByEntityInvoiceUuid($db, $entity['uuid_factura'], [
            'amount' => '10.00',
            'movement_date' => '2026-09-30 09:00:00',
            'reference' => 'USOC-TRF-001',
            'method' => 'TRANSFERENCIA',
        ]);

        Assert::same(false, $partial['reconciliation_pending']);
        Assert::same('ENTITY_PARTIAL', $partial['usoc_case']['STATUS']);
        Assert::same('PARTIAL', $partial['usoc_case']['ENTITY_PAYMENT_STATUS']);
        Assert::same('PARTIAL', $this->invoiceStatus($db, $entity['uuid_factura']));

        $complete = $service->registerByEntityInvoiceUuid($db, $entity['uuid_factura'], [
            'amount' => '15.00',
            'movement_date' => '2026-09-30 10:00:00',
            'reference' => 'USOC-TRF-002',
            'method' => 'TRANSFERENCIA',
        ]);

        Assert::same(false, $complete['reconciliation_pending']);
        Assert::same('FINANCING_RECONCILED', $complete['usoc_case']['STATUS']);
        Assert::same('PAID', $complete['usoc_case']['STUDENT_PAYMENT_STATUS']);
        Assert::same('PAID', $complete['usoc_case']['ENTITY_PAYMENT_STATUS']);
        Assert::same('PAID', $this->invoiceStatus($db, $entity['uuid_factura']));
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
    }

    public function testRejectsInvoiceOutsideUsocCaseBeforePayment(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(Fixtures::invoicePayload());
        $cases = new UsocFinancingCaseRepository(new UuidGenerator());
        $service = new UsocEntityPaymentService(
            $cases,
            new ManualPaymentService(
                new ManualPaymentInvoiceRepository(),
                new ManualPaymentPayloadBuilder(),
                RegisterPaymentTest::paymentServiceFor($db)
            ),
            new UsocCaseReconciler($cases)
        );

        Assert::throws(\Prisma\Sif\Exception\SifException::class, function () use ($db, $invoice, $service): void {
            $service->registerByEntityInvoiceUuid($db, $invoice['uuid_factura'], [
                'amount' => '10.00',
                'movement_date' => '2026-09-30',
                'reference' => 'NOT-USOC',
            ]);
        }, 409);

        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
    }

    private function invoiceStatus(\PDO $db, string $uuidFactura): string
    {
        $stmt = $db->prepare('SELECT ESTAT_COBRAMENT FROM factura WHERE UUID_FACTURA = ?');
        $stmt->execute([$uuidFactura]);

        return (string) $stmt->fetchColumn();
    }
}
