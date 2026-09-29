<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\UsocFinancingCaseRepository;
use Prisma\Sif\Service\UsocCaseReconciler;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class UsocCaseReconcilerTest
{
    public function testReconcilesEntityPendingPartialAndPaidStates(): void
    {
        $db = TestDatabase::fresh();
        $invoices = IssueInvoiceTest::serviceFor($db);
        $student = $invoices->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'REDSYS|USOC_ALUMNE|IDPAG:980|ORDER:ORDERUSOC980',
            'source_channel' => 'REDSYS',
            'relations' => [[
                'source_type' => 'INSCRIPCIO',
                'source_id' => 880,
                'idpag' => 980,
                'ds_order' => 'ORDERUSOC980',
                'visible_alumne' => 1,
            ]],
        ]));
        $entity = $invoices->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'INTRANET|USOC_ENTITAT|ID_INSC:880|FACT_ALUMNE:' . $student['uuid_factura'],
            'source_channel' => 'INTRANET',
            'relations' => [[
                'source_type' => 'INSCRIPCIO',
                'source_id' => 880,
                'relation_type' => 'USOC_ENTITY',
                'idpag' => 980,
                'visible_alumne' => 0,
            ]],
        ]));

        $db->prepare('UPDATE factura SET ESTAT_COBRAMENT = \'PAID\' WHERE UUID_FACTURA = ?')
            ->execute([$student['uuid_factura']]);

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

        $reconciler = new UsocCaseReconciler($cases);

        $pending = $reconciler->reconcile($db, 880, 980);
        Assert::same('ENTITY_INVOICED', $pending['STATUS']);
        Assert::same('PENDING', $pending['ENTITY_PAYMENT_STATUS']);

        $db->prepare('UPDATE factura SET ESTAT_COBRAMENT = \'PARTIAL\' WHERE UUID_FACTURA = ?')
            ->execute([$entity['uuid_factura']]);
        $partial = $reconciler->reconcile($db, 880, 980);
        Assert::same('ENTITY_PARTIAL', $partial['STATUS']);
        Assert::same('PARTIAL', $partial['ENTITY_PAYMENT_STATUS']);

        $db->prepare('UPDATE factura SET ESTAT_COBRAMENT = \'PAID\' WHERE UUID_FACTURA = ?')
            ->execute([$entity['uuid_factura']]);
        $paid = $reconciler->reconcile($db, 880, 980);
        Assert::same('FINANCING_RECONCILED', $paid['STATUS']);
        Assert::same('PAID', $paid['STUDENT_PAYMENT_STATUS']);
        Assert::same('PAID', $paid['ENTITY_PAYMENT_STATUS']);
    }

    public function testRequiresReviewWhenStudentInvoiceIsNoLongerPaid(): void
    {
        $db = TestDatabase::fresh();
        $invoices = IssueInvoiceTest::serviceFor($db);
        $student = $invoices->issueInvoice(Fixtures::invoicePayload([
            'idempotency_key' => 'REDSYS|USOC_ALUMNE|IDPAG:981|ORDER:ORDERUSOC981',
            'source_channel' => 'REDSYS',
            'relations' => [[
                'source_type' => 'INSCRIPCIO',
                'source_id' => 881,
                'idpag' => 981,
                'ds_order' => 'ORDERUSOC981',
                'visible_alumne' => 1,
            ]],
        ]));

        $cases = new UsocFinancingCaseRepository(new UuidGenerator());
        $cases->recordStudentInvoice(
            $db,
            881,
            981,
            $student['uuid_factura'],
            '75.00',
            '25.00',
            'ORDERUSOC981'
        );

        $result = (new UsocCaseReconciler($cases))->reconcile($db, 881, 981);

        Assert::same('REVIEW_REQUIRED', $result['STATUS']);
        Assert::same('PENDING', $result['STUDENT_PAYMENT_STATUS']);
        Assert::same('PENDING', $result['ENTITY_PAYMENT_STATUS']);
    }
}
