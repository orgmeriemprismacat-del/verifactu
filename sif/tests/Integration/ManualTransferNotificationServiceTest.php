<?php

declare(strict_types=1);

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\NotificationOutboxRepository;
use Prisma\Sif\Service\ManualTransferNotificationService;
use Prisma\Sif\Tests\Support\Assert;
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

        $payment = [
            'uuid_factura' => '11111111-1111-4111-8111-111111111111',
            'uuid_payment' => '22222222-2222-4222-8222-222222222222',
            'num_visible' => 'A2026/100',
        ];
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
            Assert::same('A2026/100', $payload['num_visible']);
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

        $result = $service->enqueue(
            $db,
            new ManualTransferNotificationLegacyPdo(),
            null,
            [
                'uuid_factura' => '33333333-3333-4333-8333-333333333333',
                'uuid_payment' => '44444444-4444-4444-8444-444444444444',
                'num_visible' => 'A2026/101',
            ],
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
