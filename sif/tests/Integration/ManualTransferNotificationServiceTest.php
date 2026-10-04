<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\NotificationOutboxRepository;
use Prisma\Sif\Service\ManualTransferNotificationService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\Fixtures;
use Prisma\Sif\Tests\Support\TestDatabase;

final class ManualTransferNotificationServiceTest
{
    public function testEnqueuesInternalAndResponsibleMessagesIdempotently(): void
    {
        $db = TestDatabase::fresh();
        $legacy = new ManualTransferNotificationLegacyPdo();
        $intranet = new ManualTransferNotificationIntranetPdo(
            'Entitat Test',
            'Anna',
            'Responsable',
            'anna.responsable@example.test'
        );
        $service = new ManualTransferNotificationService(
            new NotificationOutboxRepository(new UuidGenerator())
        );

        $payment = $this->persistPaymentFixture(
            $db,
            'UC022|NOTIFY|INVOICE|RESPONSIBLE',
            'UC022|NOTIFY|PAYMENT|RESPONSIBLE',
            '120.00'
        );
        $sync = [
            'status' => 'PARTIALLY_PAID',
            'confirmed_amount' => '120.00',
            'projected_amount' => '120.00',
        ];
        $command = [
            'amount' => '20.00',
            'movement_date' => '2026-10-03 18:30:00',
        ];

        $first = $service->enqueue(
            $db,
            $legacy,
            $intranet,
            $payment,
            $sync,
            $command
        );
        $second = $service->enqueue(
            $db,
            $legacy,
            $intranet,
            $payment,
            $sync,
            $command
        );

        Assert::same(2, $first['count']);
        Assert::same('QUEUED', $first['responsible_notification']);
        Assert::same(false, $first['idempotency_reused']);
        Assert::same(true, $second['idempotency_reused']);
        Assert::same(2, (int) $db->query(
            'SELECT COUNT(*) FROM notification_outbox'
        )->fetchColumn());

        $rows = $db->query(
            'SELECT TEMPLATE_CODE, RECIPIENT_HASH, PAYLOAD_JSON
             FROM notification_outbox
             ORDER BY TEMPLATE_CODE'
        )->fetchAll(\PDO::FETCH_ASSOC);

        Assert::same(
            'MANUAL_TRANSFER_CONFIRMED_INTERNAL',
            $rows[0]['TEMPLATE_CODE']
        );
        Assert::same(
            hash('sha256', 'resguard.gestio@prisma.cat'),
            $rows[0]['RECIPIENT_HASH']
        );
        Assert::same(
            'MANUAL_TRANSFER_CONFIRMED_RESPONSIBLE',
            $rows[1]['TEMPLATE_CODE']
        );
        Assert::same(
            hash('sha256', 'anna.responsable@example.test'),
            $rows[1]['RECIPIENT_HASH']
        );

        foreach ($rows as $row) {
            $payload = json_decode((string) $row['PAYLOAD_JSON'], true);
            Assert::same($payment['num_visible'], $payload['num_visible']);
            Assert::same('20.00', $payload['movement_amount']);
            Assert::same('120.00', $payload['confirmed_amount']);
            Assert::same('PARTIALLY_PAID', $payload['payment_status']);
            if (str_contains(
                (string) $row['PAYLOAD_JSON'],
                'anna.responsable@example.test'
            )) {
                Assert::fail('Notification payload must not persist raw recipient email');
            }
        }

        Assert::same(
            $first['notifications'][0]['uuid_notification'],
            $second['notifications'][0]['uuid_notification']
        );
        Assert::same(
            $first['notifications'][1]['uuid_notification'],
            $second['notifications'][1]['uuid_notification']
        );
    }

    public function testMissingLegacyIntranetStillQueuesInternalMessage(): void
    {
        $db = TestDatabase::fresh();
        $service = new ManualTransferNotificationService(
            new NotificationOutboxRepository(new UuidGenerator())
        );

        $payment = $this->persistPaymentFixture(
            $db,
            'UC022|NOTIFY|INVOICE|NO-INTRANET',
            'UC022|NOTIFY|PAYMENT|NO-INTRANET',
            '150.00'
        );

        $result = $service->enqueue(
            $db,
            new ManualTransferNotificationLegacyPdo(),
            null,
            $payment,
            [
                'status' => 'PAID',
                'confirmed_amount' => '150.00',
                'projected_amount' => '150.00',
            ],
            [
                'amount' => '150.00',
                'movement_date' => '2026-10-03 19:00:00',
            ]
        );

        Assert::same(1, $result['count']);
        Assert::same('SKIPPED', $result['responsible_notification']);
        Assert::same(
            'LEGACY_INTRANET_DB_NOT_CONFIGURED',
            $result['responsible_skip_reason']
        );
        Assert::same(1, (int) $db->query(
            'SELECT COUNT(*) FROM notification_outbox'
        )->fetchColumn());
    }
    private function persistPaymentFixture(
        \PDO $db,
        string $invoiceIdempotencyKey,
        string $paymentIdempotencyKey,
        string $amount
    ): array {
        $invoice = IssueInvoiceTest::serviceFor($db)->issueInvoice(
            Fixtures::invoicePayload([
                'idempotency_key' => $invoiceIdempotencyKey,
                'emesa_abans_cobrament' => 1,
                'totals' => [
                    'import_base' => $amount,
                    'taxable_base' => $amount,
                    'total' => $amount,
                ],
                'lines' => [[
                    'unit_price' => $amount,
                    'base' => $amount,
                    'import_base' => $amount,
                    'taxable_base' => $amount,
                    'total' => $amount,
                ]],
            ])
        );

        $payment = RegisterPaymentTest::paymentServiceFor($db)->registerPayment([
            'idempotency_key' => $paymentIdempotencyKey,
            'movement_type' => 'CHARGE',
            'method' => 'TRANSFERENCIA',
            'source_channel' => 'INTRANET',
            'amount' => $amount,
            'movement_date' => '2026-10-03 18:00:00',
            'provider_ref' => 'UC022-NOTIFY-FIXTURE',
            'allocations' => [[
                'uuid_factura' => $invoice['uuid_factura'],
                'amount' => $amount,
                'allocation_type' => 'INVOICE_PAYMENT',
            ]],
        ]);

        return [
            'uuid_factura' => $invoice['uuid_factura'],
            'uuid_payment' => $payment['uuid_payment'],
            'num_visible' => $invoice['num_visible'],
        ];
    }

}

final class ManualTransferNotificationLegacyPdo extends \PDO
{
    public function __construct() {}

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        return new ManualTransferNotificationLegacyStatement();
    }
}

final class ManualTransferNotificationLegacyStatement extends \PDOStatement
{
    public function execute(?array $params = null): bool
    {
        return true;
    }

    public function fetch(
        int $mode = \PDO::FETCH_DEFAULT,
        int $cursorOrientation = \PDO::FETCH_ORI_NEXT,
        int $cursorOffset = 0
    ): mixed {
        return [
            'CIF' => 'B12345678',
            'RAO' => 'Entitat Test',
            'CONCEPTE1' => 'Formació docent',
            'CONCEPTE2' => 'Edició octubre',
        ];
    }
}

final class ManualTransferNotificationIntranetPdo extends \PDO
{
    public function __construct(
        private string $entity,
        private string $name,
        private string $surname,
        private string $email
    ) {
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        return new ManualTransferNotificationIntranetStatement(
            $this->entity,
            $this->name,
            $this->surname,
            $this->email
        );
    }
}

final class ManualTransferNotificationIntranetStatement extends \PDOStatement
{
    public function __construct(
        private string $entity,
        private string $name,
        private string $surname,
        private string $email
    ) {
    }

    public function execute(?array $params = null): bool
    {
        return true;
    }

    public function fetch(
        int $mode = \PDO::FETCH_DEFAULT,
        int $cursorOrientation = \PDO::FETCH_ORI_NEXT,
        int $cursorOffset = 0
    ): mixed {
        return [
            'RAO' => $this->entity,
            'NOM' => $this->name,
            'COGNOMS' => $this->surname,
            'CORREU' => $this->email,
        ];
    }
}
