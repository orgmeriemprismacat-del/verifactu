<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\EnrollmentFundMovementRepository;
use Prisma\Sif\Repository\IncidentRepository;
use Prisma\Sif\Repository\LegacyGroupSnapshotRepository;
use Prisma\Sif\Repository\LegacySyncRepository;
use Prisma\Sif\Repository\RedsysCallbackQueueRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Repository\RedsysPaymentIntentRepository;
use Prisma\Sif\Service\GroupEnrollmentFundAllocationService;
use Prisma\Sif\Service\GroupParticipantAdditionPreviewService;
use Prisma\Sif\Service\GroupParticipantRemovalPreviewService;
use Prisma\Sif\Service\LegacyGroupInvoicePayloadBuilder;
use Prisma\Sif\Service\LegacySyncService;
use Prisma\Sif\Service\RedsysCallbackDispatcher;
use Prisma\Sif\Service\RedsysCallbackWorker;
use Prisma\Sif\Service\RedsysGroupInvoiceService;
use Prisma\Sif\Service\RedsysInvoicePayloadBuilder;
use Prisma\Sif\Service\RedsysLegacySyncingProcessor;
use Prisma\Sif\Service\RedsysPaymentIntentService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class RedsysGroupWorkerEndToEndTest
{
    public function testGroupWorkerReplayKeepsInvoicePaymentFundsAndLegacySyncIdempotent(): void
    {
        $db = TestDatabase::fresh();
        $dsOrder = 'ORDERGROUPWORKER1';
        $snapshot = $this->snapshot();

        $intent = (new RedsysPaymentIntentService(
            new RedsysPaymentIntentRepository(),
            new UuidGenerator()
        ))->create($db, [
            'ds_order' => $dsOrder,
            'idpag' => 950,
            'source_type' => 'GRUP',
            'source_id' => '950',
            'expected_amount' => '200.00',
            'currency' => 'EUR',
            'terminal' => '1',
            'snapshot' => $snapshot,
        ]);

        $notifications = new RedsysNotificationRepository();
        $notification = $notifications->recordReceived(
            $db,
            $dsOrder,
            950,
            '200.00',
            '0000',
            true,
            [
                'source' => 'group-worker-e2e',
                'currency_code' => '978',
                'terminal' => '1',
                'signature_version' => 'HMAC_SHA256_V1',
                'payload_hash' => str_repeat('c', 64),
            ],
            'VALIDATED'
        );

        $queue = new RedsysCallbackQueueRepository(new UuidGenerator());
        $job = $queue->enqueue(
            $db,
            (int) $notification['notification_id'],
            (string) $intent['uuid_intent']
        );

        $legacyDb = new RedsysGroupWorkerLegacyPdo();
        $worker = $this->worker($db, $notifications, $queue, $legacyDb);

        $first = $worker->runOne(
            $db,
            'group-worker-a',
            new \DateTimeImmutable('2030-10-01 10:00:00')
        );

        Assert::same(true, $first['ok']);
        Assert::same(true, $first['legacy_sync_executed']);
        Assert::same(2, $first['fund_allocations']['count']);
        Assert::same('200.00', $first['fund_allocations']['amount']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM factura_linia')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM enrollment_fund_movement')->fetchColumn());

        $preview = (new GroupParticipantRemovalPreviewService(
            new EnrollmentFundMovementRepository(new UuidGenerator())
        ))->preview($db, $first['uuid_factura'], 751);
        Assert::same(751, $preview['participant']['id_insc']);
        Assert::same('120.00', $preview['participant']['billed']);
        Assert::same('120.00', $preview['participant']['funds_attributed']);
        Assert::same('0.00', $preview['participant']['unpaid']);
        Assert::same('120.00', $preview['participant']['max_refundable_before_policy']);
        Assert::same(2, $preview['group']['participants_before']);
        Assert::same(1, $preview['group']['participants_after']);
        Assert::same(false, $preview['decision']['automatic_commit_allowed']);
        Assert::same(true, $preview['decision']['repricing_policy_required']);
        Assert::same(1, count($preview['fund_movements']));

        $addition = (new GroupParticipantAdditionPreviewService())->preview(
            $db,
            $first['uuid_factura'],
            [
                'id_insc' => 753,
                'idpag' => 950,
                'concept' => 'Comunicacio assertiva - Carla Participant',
                'base' => '100.00',
                'discount' => '20.00',
                'total' => '80.00',
            ]
        );
        Assert::same(753, $addition['candidate']['id_insc']);
        Assert::same('80.00', $addition['candidate']['total']);
        Assert::same(2, $addition['group']['participants_before']);
        Assert::same(3, $addition['group']['participants_after']);
        Assert::same('280.00', $addition['group']['projected_nominal_total_before_repricing_policy']);
        Assert::same(false, $addition['decision']['automatic_commit_allowed']);
        Assert::same(true, $addition['decision']['repricing_policy_required']);

        Assert::throws(\Prisma\Sif\Exception\SifException::class, function () use ($db, $first): void {
            (new GroupParticipantAdditionPreviewService())->preview(
                $db,
                $first['uuid_factura'],
                [
                    'id_insc' => 751,
                    'idpag' => 950,
                    'concept' => 'Duplicada',
                    'base' => '120.00',
                    'discount' => '0.00',
                    'total' => '120.00',
                ]
            );
        }, 409);

        Assert::throws(\Prisma\Sif\Exception\SifException::class, function () use ($db, $first): void {
            (new GroupParticipantRemovalPreviewService(
                new EnrollmentFundMovementRepository(new UuidGenerator())
            ))->preview($db, $first['uuid_factura'], 999999);
        }, 409);

        $db->prepare(
            "UPDATE redsys_callback_queue
             SET STATUS = 'RETRY', AVAILABLE_AT = ?, PROCESSED_AT = NULL,
                 LOCKED_AT = NULL, LOCKED_BY = NULL
             WHERE ID = ?"
        )->execute(['2030-10-01 10:01:00', (int) $job['ID']]);

        $second = $worker->runOne(
            $db,
            'group-worker-b',
            new \DateTimeImmutable('2030-10-01 10:01:00')
        );

        Assert::same(true, $second['ok']);
        Assert::same($first['uuid_factura'], $second['uuid_factura']);
        Assert::same($first['uuid_payment'], $second['uuid_payment']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM enrollment_fund_movement')->fetchColumn());
        Assert::same(8, count($legacyDb->preparedSql));
    }

    private function worker(
        \PDO $db,
        RedsysNotificationRepository $notifications,
        RedsysCallbackQueueRepository $queue,
        \PDO $legacyDb
    ): RedsysCallbackWorker {
        $handler = new RedsysGroupInvoiceService(
            $notifications,
            new LegacyGroupSnapshotRepository(),
            new LegacyGroupInvoicePayloadBuilder(),
            new RedsysInvoicePayloadBuilder($notifications),
            IssueInvoiceTest::serviceFor($db),
            new GroupEnrollmentFundAllocationService(
                new EnrollmentFundMovementRepository(new UuidGenerator())
            )
        );

        return new RedsysCallbackWorker(
            $queue,
            new RedsysLegacySyncingProcessor(
                new RedsysCallbackDispatcher([$handler]),
                $legacyDb,
                new LegacySyncService(new LegacySyncRepository())
            ),
            new IncidentRepository(),
            5
        );
    }

    private function snapshot(): array
    {
        return [
            'responsible' => [
                'NOM' => 'Responsable',
                'COGNOMS' => 'Grup',
                'DNI' => '44444444G',
                'CORREU' => 'responsable@example.test',
                'ADRECA' => 'Carrer Grup 4',
                'Codi_Postal' => '08001',
                'Poblacio' => 'Barcelona',
            ],
            'payment' => [
                'idpag' => 950,
                'amount' => '200.00',
            ],
            'items' => [
                [
                    'inscription' => $this->inscription(751, 'Anna', '120.00'),
                    'course' => ['NOM_CURS' => 'Comunicacio assertiva'],
                ],
                [
                    'inscription' => $this->inscription(752, 'Biel', '80.00'),
                    'course' => ['NOM_CURS' => 'Comunicacio assertiva'],
                ],
            ],
        ];
    }

    private function inscription(int $id, string $name, string $amount): array
    {
        return [
            'ID' => $id,
            'IDPAG' => 950,
            'ANY' => 2026,
            'MES' => '10',
            'CURS' => 'ABC',
            'TIPUS_INSC' => 'G',
            'NOM' => $name,
            'COGNOMS' => 'Participant',
            'DNI' => $id === 751 ? '11111111H' : '22222222J',
            'CORREU' => strtolower($name) . '@example.test',
            'A_PAGAR' => $amount,
            'TOTAL' => $amount,
        ];
    }
}

final class RedsysGroupWorkerLegacyPdo extends \PDO
{
    public array $preparedSql = [];

    public function __construct()
    {
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        $this->preparedSql[] = $query;
        return new RedsysGroupWorkerLegacyStatement();
    }
}

final class RedsysGroupWorkerLegacyStatement extends \PDOStatement
{
    public function execute(?array $params = null): bool
    {
        return true;
    }
}
