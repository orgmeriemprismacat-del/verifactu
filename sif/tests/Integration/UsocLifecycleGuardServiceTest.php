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
