<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\UsocFinancingCaseRepository;
use Prisma\Sif\Service\UsocLifecycleGuardService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class UsocLifecycleGuardServiceTest
{
    public function testAllowsLegacyLifecycleWhenNoUsocFinancingCaseExists(): void
    {
        $db = TestDatabase::fresh();
        $service = new UsocLifecycleGuardService(
            new UsocFinancingCaseRepository(new UuidGenerator())
        );

        $change = $service->check($db, 880, 980, 'course_change');
        $cancel = $service->check($db, 880, 980, 'cancellation');

        Assert::same(true, $change['allowed']);
        Assert::same('NO_SIF_USOC_CASE', $change['reason']);
        Assert::same(true, $cancel['allowed']);
        Assert::same('NO_SIF_USOC_CASE', $cancel['reason']);
    }

    public function testBlocksOrphanUsocFiscalEvidenceWithoutFinancingCase(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'REDSYS|USOC_ALUMNE|IDPAG:980|ORDER:ORPHAN980',
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
                    'ds_order' => 'ORPHAN980',
                    'visible_alumne' => 1,
                ]],
            ])
        );

        $service = new UsocLifecycleGuardService(
            new UsocFinancingCaseRepository(new UuidGenerator())
        );

        $result = $service->check($db, 880, 980, 'course_change');

        Assert::same(false, $result['allowed']);
        Assert::same('USOC_FISCAL_EVIDENCE_WITHOUT_CASE_REQUIRES_REVIEW', $result['reason']);
        Assert::same(null, $result['case']);
        Assert::same($invoice['uuid_factura'], $result['orphan_fiscal_evidence']['UUID_FACTURA']);
    }

    public function testLifecycleGuardReturnsSeparatedPayerSnapshot(): void
    {
        $db = TestDatabase::fresh();

        $studentInvoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'REDSYS|USOC_ALUMNE|IDPAG:980|ORDER:SNAPSHOT980',
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
                    'ds_order' => 'SNAPSHOT980',
                    'visible_alumne' => 1,
                ]],
                'payment' => [
                    'idempotency_key' => 'PAYMENT|REDSYS|ORDER:SNAPSHOT980',
                    'movement_type' => 'CHARGE',
                    'method' => 'REDSYS',
                    'source_channel' => 'REDSYS',
                    'amount' => '75.00',
                    'movement_date' => '2026-09-30 10:00:00',
                    'provider_ref' => 'SNAPSHOT980',
                    'ds_order' => 'SNAPSHOT980',
                    'idpag' => 980,
                ],
            ])
        );

        $entityInvoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'INTRANET|USOC_ENTITAT|ID_INSC:880|SNAPSHOT',
                'source_channel' => 'INTRANET',
                'emesa_abans_cobrament' => 1,
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
            ])
        );

        $cases = new UsocFinancingCaseRepository(new UuidGenerator());
        $cases->recordStudentInvoice(
            $db,
            880,
            980,
            (string) $studentInvoice['uuid_factura'],
            '75.00',
            '25.00',
            'SNAPSHOT980'
        );
        $cases->recordEntityInvoice(
            $db,
            880,
            980,
            (string) $studentInvoice['uuid_factura'],
            (string) $entityInvoice['uuid_factura'],
            '75.00',
            '25.00',
            'SNAPSHOT980'
        );

        RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' => 'TRANSFERENCIA|USOC|SNAPSHOT|10',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '10.00',
            'movement_date' => '2026-09-30 11:00:00',
            'reference' => 'USOC-SNAPSHOT-10',
            'allocations' => [[
                'uuid_factura' => (string) $entityInvoice['uuid_factura'],
                'amount' => '10.00',
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ]);

        $result = (new UsocLifecycleGuardService($cases))
            ->check($db, 880, 980, 'cancellation');

        Assert::same(false, $result['allowed']);
        Assert::same('USOC_FINANCING_CASE_REQUIRES_ORCHESTRATION', $result['reason']);

        $student = $result['payer_snapshot']['student'];
        $entity = $result['payer_snapshot']['entity'];

        Assert::same($studentInvoice['uuid_factura'], $student['invoice_uuid']);
        Assert::same('75.00', $student['total']);
        Assert::same('75.00', $student['charged']);
        Assert::same('0.00', $student['refunded']);
        Assert::same('75.00', $student['net_paid']);
        Assert::same('PAID', $student['payment_status']);

        Assert::same($entityInvoice['uuid_factura'], $entity['invoice_uuid']);
        Assert::same('25.00', $entity['total']);
        Assert::same('10.00', $entity['charged']);
        Assert::same('0.00', $entity['refunded']);
        Assert::same('10.00', $entity['net_paid']);
        Assert::same('PARTIAL', $entity['payment_status']);
    }

    public function testBlocksLegacyChangeAndCancellationWhenUsocCaseExists(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'REDSYS|USOC_ALUMNE|IDPAG:980|ORDER:LIFECYCLE980',
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
                    'ds_order' => 'LIFECYCLE980',
                    'visible_alumne' => 1,
                ]],
            ])
        );

        $cases = new UsocFinancingCaseRepository(new UuidGenerator());
        $cases->recordStudentInvoice(
            $db,
            880,
            980,
            (string) $invoice['uuid_factura'],
            '75.00',
            '25.00',
            'LIFECYCLE980'
        );

        $service = new UsocLifecycleGuardService($cases);

        foreach (['course_change', 'cancellation'] as $operation) {
            $result = $service->check($db, 880, 980, $operation);
            Assert::same(false, $result['allowed']);
            Assert::same('USOC_FINANCING_CASE_REQUIRES_ORCHESTRATION', $result['reason']);
            Assert::same('PENDING_ENTITY_INVOICE', $result['case']['STATUS']);
            Assert::same($invoice['uuid_factura'], $result['case']['UUID_STUDENT_INVOICE']);
        }
    }
}
