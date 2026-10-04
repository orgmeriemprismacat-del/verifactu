<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\DebtClaimCaseRepository;
use Prisma\Sif\Repository\DebtSnapshotRepository;
use Prisma\Sif\Repository\NotificationOutboxRepository;
use Prisma\Sif\Repository\OperationalEventRepository;
use Prisma\Sif\Service\DebtClaimCoordinator;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class DebtClaimEnrollmentResolverTest
{
    public function testEnrollmentIdResolvesUniqueSifInvoice(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload(['emesa_abans_cobrament' => 1])
        );

        $preview = $this->service($db)->preview(
            $this->actor(),
            ['id_insc' => '10']
        );

        Assert::same($invoice['uuid_factura'], $preview['snapshot']['uuid_factura']);
        Assert::same('120.00', $preview['snapshot']['outstanding']);
        Assert::same('OUTSTANDING', $preview['status']);

        $notice = $this->service($db)->recordNotice($this->actor(), [
            'id_insc' => '10',
            'action' => 'FINAL_REMINDER',
            'idempotency_key' => 'CLAIM|INSC|10|REMINDER',
            'reason_code' => 'DEBT_DUE',
            'request_id' => 'REQ-INSC-10',
            'correlation_id' => 'CORR-INSC-10',
        ]);

        Assert::same($invoice['uuid_factura'], $notice['uuid_factura']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM debt_claim_case')->fetchColumn());
    }

    public function testEnrollmentIdRejectsMultipleOutstandingInvoices(): void
    {
        $db = TestDatabase::fresh();

        IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC012|INSC|AMBIGUOUS|A',
                'relations' => [[
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => 10,
                    'factura_relacionada' => 501,
                    'idpag' => 501,
                    'ds_order' => '501501',
                    'visible_alumne' => 1,
                ]],
            ])
        );
        IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC012|INSC|AMBIGUOUS|B',
                'relations' => [[
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => 10,
                    'factura_relacionada' => 502,
                    'idpag' => 502,
                    'ds_order' => '502502',
                    'visible_alumne' => 1,
                ]],
            ])
        );

        Assert::throws(
            SifException::class,
            fn () => $this->service($db)->preview($this->actor(), ['id_insc' => '10']),
            409
        );
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM debt_claim_case')->fetchColumn());
    }

    public function testEnrollmentIdSelectsOnlyOutstandingInvoiceWhenHistoricalOneIsPaid(): void
    {
        $db = TestDatabase::fresh();

        $paid = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC012|INSC|HISTORICAL|PAID',
                'relations' => [[
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => 10,
                    'factura_relacionada' => 601,
                    'idpag' => 601,
                    'ds_order' => '601601',
                    'visible_alumne' => 1,
                ]],
            ])
        );
        RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' => 'UC012|INSC|HISTORICAL|PAYMENT',
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => '120.00',
            'movement_date' => '2026-10-03 12:00:00',
            'reference' => 'UC012-HISTORICAL-PAID',
            'allocations' => [[
                'uuid_factura' => $paid['uuid_factura'],
                'amount' => '120.00',
                'allocation_type' => 'CLAIM_PAYMENT',
            ]],
        ]);

        $open = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC012|INSC|CURRENT|OPEN',
                'relations' => [[
                    'source_type' => 'INSCRIPCIO',
                    'source_id' => 10,
                    'factura_relacionada' => 602,
                    'idpag' => 602,
                    'ds_order' => '602602',
                    'visible_alumne' => 1,
                ]],
            ])
        );

        $preview = $this->service($db)->preview($this->actor(), ['id_insc' => 10]);

        Assert::same($open['uuid_factura'], $preview['snapshot']['uuid_factura']);
        Assert::same('OUTSTANDING', $preview['status']);
        Assert::same('120.00', $preview['snapshot']['outstanding']);
    }

    private function service(\PDO $db): DebtClaimCoordinator
    {
        $uuids = new UuidGenerator();

        return new DebtClaimCoordinator(
            $db,
            new TransactionRunner($db),
            new DebtSnapshotRepository(),
            new DebtClaimCaseRepository($uuids),
            new NotificationOutboxRepository($uuids),
            new OperationalEventRepository($uuids),
            ['GESTIO_COBRAMENTS'],
            ['GESTIO_COBRAMENTS']
        );
    }

    private function actor(): array
    {
        return [
            'actor_type' => 'USER',
            'actor_id' => 'admin-cobraments',
            'roles' => ['GESTIO_COBRAMENTS'],
            'request_id' => 'REQ-INSC-RESOLVER',
        ];
    }
}
