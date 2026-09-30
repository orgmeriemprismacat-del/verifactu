<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Repository\IncidentRepository;
use Prisma\Sif\Repository\LegacyCourseSnapshotRepository;
use Prisma\Sif\Repository\LegacySyncRepository;
use Prisma\Sif\Repository\NotificationOutboxRepository;
use Prisma\Sif\Repository\RedsysCallbackQueueRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Repository\RedsysPaymentIntentRepository;
use Prisma\Sif\Service\CourseLegacyPaymentSyncService;
use Prisma\Sif\Service\CoursePaymentNotificationService;
use Prisma\Sif\Service\LegacyCourseInvoicePayloadBuilder;
use Prisma\Sif\Service\LegacySyncService;
use Prisma\Sif\Service\RedsysCallbackDispatcher;
use Prisma\Sif\Service\RedsysCallbackService;
use Prisma\Sif\Service\RedsysCallbackWorker;
use Prisma\Sif\Service\RedsysCourseInvoiceService;
use Prisma\Sif\Service\RedsysInvoicePayloadBuilder;
use Prisma\Sif\Service\RedsysLegacySyncingProcessor;
use Prisma\Sif\Service\RedsysPaymentIntentService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class RedsysCourseEndToEndSimulatedTest
{
    public function testFullPaymentAndDuplicateCallbackStayIdempotentThroughLegacySync(): void
    {
        $db = TestDatabase::fresh();
        $legacy = new RedsysCourseE2ELegacyPdo('95.50', 'M');
        [$callback, $worker] = $this->circuit($db, $legacy);

        $this->createIntent($db, 'E2EFULL00001', '95.50', '95.50');
        $payload = $this->callbackPayload('E2EFULL00001', '95.50');

        $queued = $callback->receiveCallback($db, $payload, true);
        Assert::same('QUEUED', $queued['queue_status']);
        Assert::same(0, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());

        $first = $worker->runOne($db, 'e2e-worker', new \DateTimeImmutable('2030-06-19 10:00:00'));

        Assert::same(true, $first['ok']);
        Assert::same(true, $first['legacy_sync_executed']);
        Assert::same('PAID', $first['legacy_payment_sync']['status']);
        Assert::same('95.50', $legacy->payment);
        Assert::same('1', $legacy->courseStatus);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same('PENDING', $first['notification_outbox']['status']);
        Assert::same(false, $first['notification_outbox']['idempotency_reused']);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM notification_outbox')->fetchColumn());

        $outbox = $db->query(
            "SELECT IDEMPOTENCY_KEY, TEMPLATE_CODE, RECIPIENT_TYPE, RECIPIENT_HASH, PAYLOAD_JSON
             FROM notification_outbox"
        )->fetch(\PDO::FETCH_ASSOC);
        Assert::same('NOTIFY|COURSE_PAYMENT_CONFIRMED|ORDER:E2EFULL00001', $outbox['IDEMPOTENCY_KEY']);
        Assert::same('COURSE_PAYMENT_CONFIRMED', $outbox['TEMPLATE_CODE']);
        Assert::same('ALUMNE', $outbox['RECIPIENT_TYPE']);
        Assert::same(hash('sha256', 'joan@example.invalid'), $outbox['RECIPIENT_HASH']);
        $notificationPayload = json_decode((string) $outbox['PAYLOAD_JSON'], true);
        Assert::same('PAID', $notificationPayload['payment_status']);
        Assert::same('0.00', $notificationPayload['remaining_after']);
        Assert::same(false, array_key_exists('email', $notificationPayload));
        Assert::same(false, array_key_exists('dni', $notificationPayload));
        Assert::same(false, str_contains((string) $outbox['PAYLOAD_JSON'], 'joan@example.invalid'));
        Assert::same(false, str_contains((string) $outbox['PAYLOAD_JSON'], '87654321Z'));

        $duplicate = $callback->receiveCallback($db, $payload, true);
        Assert::same(true, $duplicate['duplicate']);
        Assert::same(null, $worker->runOne($db, 'e2e-worker', new \DateTimeImmutable('2030-06-19 10:01:00')));
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM notification_outbox')->fetchColumn());
        Assert::same('95.50', $legacy->payment);
    }

    public function testPartialThenCompletePaymentProjectsConfirmedLedgerTotalToLegacy(): void
    {
        $db = TestDatabase::fresh();
        $legacy = new RedsysCourseE2ELegacyPdo('120.00', '0');
        [$callback, $worker] = $this->circuit($db, $legacy);

        $this->createIntent($db, 'E2EPART00001', '50.00', '120.00', true);
        $callback->receiveCallback($db, $this->callbackPayload('E2EPART00001', '50.00'), true);
        $partial = $worker->runOne($db, 'e2e-worker', new \DateTimeImmutable('2030-06-19 11:00:00'));

        Assert::same('PARTIALLY_PAID', $partial['legacy_payment_sync']['status']);
        Assert::same('50.00', $legacy->payment);
        Assert::same('0', $legacy->courseStatus);
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(1, (int) $db->query('SELECT COUNT(*) FROM notification_outbox')->fetchColumn());
        $partialPayload = json_decode(
            (string) $db->query('SELECT PAYLOAD_JSON FROM notification_outbox ORDER BY ID LIMIT 1')->fetchColumn(),
            true
        );
        Assert::same('PARTIALLY_PAID', $partialPayload['payment_status']);
        Assert::same('70.00', $partialPayload['remaining_after']);

        $this->createIntent($db, 'E2EPART00002', '70.00', '120.00', true);
        $callback->receiveCallback($db, $this->callbackPayload('E2EPART00002', '70.00'), true);
        $complete = $worker->runOne($db, 'e2e-worker', new \DateTimeImmutable('2030-06-19 11:10:00'));

        Assert::same('PAID', $complete['legacy_payment_sync']['status']);
        Assert::same('120.00', $legacy->payment);
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM payment_transaction')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM payment_allocation')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM factura')->fetchColumn());
        Assert::same(2, (int) $db->query('SELECT COUNT(*) FROM notification_outbox')->fetchColumn());
        $completePayload = json_decode(
            (string) $db->query('SELECT PAYLOAD_JSON FROM notification_outbox ORDER BY ID DESC LIMIT 1')->fetchColumn(),
            true
        );
        Assert::same('PAID', $completePayload['payment_status']);
        Assert::same('0.00', $completePayload['remaining_after']);
    }

    private function circuit(\PDO $db, RedsysCourseE2ELegacyPdo $legacy): array
    {
        $notifications = new RedsysNotificationRepository();
        $queue = new RedsysCallbackQueueRepository(new UuidGenerator());
        $callback = new RedsysCallbackService(
            new RedsysPaymentIntentRepository(),
            $notifications,
            $queue,
            new IncidentRepository()
        );

        $handler = new RedsysCourseInvoiceService(
            $notifications,
            new LegacyCourseSnapshotRepository(),
            new LegacyCourseInvoicePayloadBuilder(),
            new RedsysInvoicePayloadBuilder($notifications),
            IssueInvoiceTest::serviceFor($db)
        );

        $processor = new RedsysLegacySyncingProcessor(
            new RedsysCallbackDispatcher([$handler]),
            $legacy,
            new LegacySyncService(new LegacySyncRepository()),
            new CourseLegacyPaymentSyncService(),
            new CoursePaymentNotificationService(
                new NotificationOutboxRepository(new UuidGenerator())
            )
        );

        $worker = new RedsysCallbackWorker(
            $queue,
            $processor,
            new IncidentRepository(),
            5
        );

        return [$callback, $worker];
    }

    private function createIntent(
        \PDO $db,
        string $dsOrder,
        string $amount,
        string $contractTotal,
        bool $fractional = false
    ): void {
        (new RedsysPaymentIntentService(
            new RedsysPaymentIntentRepository(),
            new UuidGenerator()
        ))->create($db, [
            'ds_order' => $dsOrder,
            'idpag' => 400,
            'source_type' => 'CURS',
            'source_id' => '410',
            'expected_amount' => $amount,
            'currency' => 'EUR',
            'terminal' => '1',
            'snapshot' => [
                'inscription' => [
                    'ID' => 410,
                    'IDPAG' => 400,
                    'ANY' => 2026,
                    'MES' => '07',
                    'CURS' => 'LM',
                    'NOM' => 'Joan',
                    'COGNOMS' => 'Mostra',
                    'DNI' => '87654321Z',
                    'CORREU' => 'joan@example.invalid',
                    'A_PAGAR' => $contractTotal,
                    'FRACCIO' => $fractional ? '1' : '',
                    'FACTURA_RELACIONADA' => 810,
                ],
                'course' => [
                    'NOM_CURS' => 'Llenguatge musical',
                    'DATAI' => '2026-07-01',
                    'DATAF' => '2026-07-31',
                    'HORES' => 30,
                ],
                'payment' => [
                    'idpag' => 400,
                    'amount' => $amount,
                ],
            ],
        ]);
    }

    private function callbackPayload(string $dsOrder, string $amount): array
    {
        return [
            'ds_order' => $dsOrder,
            'amount' => $amount,
            'response_code' => '0000',
            'currency_code' => '978',
            'currency' => 'EUR',
            'terminal' => '1',
            'signature_version' => 'HMAC_SHA256_V1',
            'payload_hash' => hash('sha256', $dsOrder . '|' . $amount),
        ];
    }
}

final class RedsysCourseE2ELegacyPdo extends \PDO
{
    public string $payment = '0.00';
    public string $courseStatus;
    public ?string $paymentDate = null;
    public string $observations = '';

    public function __construct(public string $contractTotal, string $courseStatus)
    {
        $this->courseStatus = $courseStatus;
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        if (stripos(ltrim($query), 'SELECT') === 0) {
            return new RedsysCourseE2ELegacySelectStatement($this);
        }

        return new RedsysCourseE2ELegacyUpdateStatement($this);
    }
}

final class RedsysCourseE2ELegacySelectStatement extends \PDOStatement
{
    public function __construct(private RedsysCourseE2ELegacyPdo $db) {}

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
            'A_PAGAR' => $this->db->contractTotal,
            'PAGAMENT' => $this->db->payment,
            'FRACCIO' => '',
            'INSC CURS' => $this->db->courseStatus,
        ];
    }
}

final class RedsysCourseE2ELegacyUpdateStatement extends \PDOStatement
{
    private int $rows = 0;

    public function __construct(private RedsysCourseE2ELegacyPdo $db) {}

    public function execute(?array $params = null): bool
    {
        $this->db->payment = (string) ($params[0] ?? $this->db->payment);
        $paid = (int) ($params[1] ?? 0) === 1;
        if ($paid && $this->db->paymentDate === null) {
            $this->db->paymentDate = (string) ($params[2] ?? '');
        }
        if ((int) ($params[3] ?? 0) === 1 && $this->db->courseStatus === 'M') {
            $this->db->courseStatus = '1';
        }
        $this->rows = 1;

        return true;
    }

    public function rowCount(): int
    {
        return $this->rows;
    }
}
