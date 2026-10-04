<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\PaymentStatusCalculator;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\ManualPaymentInvoiceRepository;
use Prisma\Sif\Repository\NotificationOutboxRepository;
use Prisma\Sif\Repository\PaymentRepository;
use Prisma\Sif\Service\ExistingInvoiceLegacyProjectionService;
use Prisma\Sif\Service\ExistingInvoicePaymentCommandService;
use Prisma\Sif\Service\ExistingInvoicePaymentNotificationService;
use Prisma\Sif\Service\ManualPaymentPayloadBuilder;
use Prisma\Sif\Service\ManualPaymentService;
use Prisma\Sif\Service\PaymentPayloadValidator;
use Prisma\Sif\Service\PaymentService;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ExistingInvoicePaymentNotificationServiceTest
{
    public function testCommandEnqueuesSingleNotificationInSameTransaction(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC002|NOTIFY|INVOICE',
                'emesa_abans_cobrament' => 1,
            ])
        );

        $command = $this->command($db);
        $payload = [
            'selector' => ['uuid_factura' => $invoice['uuid_factura']],
            'payment' => [
                'idempotency_key' => 'INTRANET|UC002|NOTIFY|PAYMENT',
                'amount' => '120.00',
                'movement_date' => '2026-10-04 04:40:00',
                'bank' => 'CAIXA',
                'method' => 'TRANSFERENCIA',
                'reference' => 'BANK-UC002-001',
            ],
        ];

        $db->beginTransaction();
        $result = $command->register($db, $payload);
        $db->commit();

        Assert::same('PENDING', $result['notification_outbox']['status']);
        Assert::same(false, $result['notification_outbox']['idempotency_reused']);
        Assert::same(
            1,
            (int) $db->query('SELECT COUNT(*) FROM notification_outbox')->fetchColumn()
        );

        $row = $db->query(
            'SELECT TEMPLATE_CODE, UUID_FACTURA, UUID_PAYMENT, STATUS
             FROM notification_outbox LIMIT 1'
        )->fetch(\PDO::FETCH_ASSOC);

        Assert::same('EXISTING_INVOICE_PAYMENT_CONFIRMED', $row['TEMPLATE_CODE']);
        Assert::same($invoice['uuid_factura'], $row['UUID_FACTURA']);
        Assert::same($result['uuid_payment'], $row['UUID_PAYMENT']);
        Assert::same('PENDING', $row['STATUS']);
    }

    public function testRetryReusesNotificationOutboxRow(): void
    {
        $db = TestDatabase::fresh();
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => 'UC002|NOTIFY|INVOICE-RETRY',
                'emesa_abans_cobrament' => 1,
            ])
        );

        $command = $this->command($db);
        $payload = [
            'selector' => ['uuid_factura' => $invoice['uuid_factura']],
            'payment' => [
                'idempotency_key' => 'INTRANET|UC002|NOTIFY|PAYMENT-RETRY',
                'amount' => '120.00',
                'movement_date' => '2026-10-04 04:41:00',
                'bank' => 'BBVA',
                'method' => 'TRANSFERENCIA',
                'reference' => 'BANK-UC002-002',
            ],
        ];

        $db->beginTransaction();
        $first = $command->register($db, $payload);
        $db->commit();

        $db->beginTransaction();
        $second = $command->register($db, $payload);
        $db->commit();

        Assert::same(false, $first['notification_outbox']['idempotency_reused']);
        Assert::same(true, $second['notification_outbox']['idempotency_reused']);
        Assert::same(
            $first['notification_outbox']['uuid_notification'],
            $second['notification_outbox']['uuid_notification']
        );
        Assert::same(
            1,
            (int) $db->query('SELECT COUNT(*) FROM notification_outbox')->fetchColumn()
        );
    }

    private function command(\PDO $db): ExistingInvoicePaymentCommandService
    {
        $payments = new PaymentService(
            new TransactionRunner($db),
            new PaymentPayloadValidator(),
            new PaymentRepository(new UuidGenerator(), new PaymentStatusCalculator())
        );

        return new ExistingInvoicePaymentCommandService(
            new ManualPaymentService(
                new ManualPaymentInvoiceRepository(),
                new ManualPaymentPayloadBuilder(),
                $payments
            ),
            new ExistingInvoiceLegacyProjectionService(),
            null,
            new ExistingInvoicePaymentNotificationService(
                new NotificationOutboxRepository(new UuidGenerator())
            )
        );
    }
}
