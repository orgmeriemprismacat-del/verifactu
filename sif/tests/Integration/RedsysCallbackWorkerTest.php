<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\IncidentRepository;
use Prisma\Sif\Repository\RedsysCallbackQueueRepository;
use Prisma\Sif\Repository\RedsysNotificationRepository;
use Prisma\Sif\Repository\RedsysPaymentIntentRepository;
use Prisma\Sif\Service\RedsysCallbackWorker;
use Prisma\Sif\Service\RedsysJobProcessor;
use Prisma\Sif\Service\RedsysPaymentIntentService;
use Prisma\Sif\Tests\Support\Assert;
use Prisma\Sif\Tests\Support\TestDatabase;

final class RecordingRedsysJobProcessor implements RedsysJobProcessor
{
    public bool $transactionOpen = true;

    public function process(\PDO $sifDb, array $job): array
    {
        $this->transactionOpen = $sifDb->inTransaction();

        return [
            'ok' => true,
            'uuid_job' => $job['UUID_JOB'],
        ];
    }
}

final class OutcomeRedsysJobProcessor implements RedsysJobProcessor
{
    public function __construct(private array|\Throwable $outcome)
    {
    }

    public function process(\PDO $sifDb, array $job): array
    {
        if ($this->outcome instanceof \Throwable) {
            throw $this->outcome;
        }

        return $this->outcome;
    }
}

final class RedsysCallbackWorkerTest
{
    public function testWorkerClaimsQueuedJobOnce(): void
    {
        $db = TestDatabase::fresh();
        $job = $this->queuedJob($db, 'ORDERWORK1');
        $queue = new RedsysCallbackQueueRepository(new UuidGenerator());
        $now = new \DateTimeImmutable('2030-06-19 10:00:00');

        $claimed = $queue->claimNext($db, 'worker-a', $now);

        Assert::same($job['UUID_JOB'], $claimed['UUID_JOB']);
        Assert::same('PROCESSING', $claimed['STATUS']);
        Assert::same(1, (int) $claimed['ATTEMPTS']);
        Assert::same(null, $queue->claimNext($db, 'worker-b', $now));
    }

    public function testRunOneProcessesClaimedJobAfterClaimCommit(): void
    {
        $db = TestDatabase::fresh();
        $job = $this->queuedJob($db, 'ORDERWORK2');
        $processor = new RecordingRedsysJobProcessor();
        $worker = new RedsysCallbackWorker(
            new RedsysCallbackQueueRepository(new UuidGenerator()),
            $processor,
            new IncidentRepository(),
            5
        );

        $result = $worker->runOne(
            $db,
            'worker-a',
            new \DateTimeImmutable('2030-06-19 10:00:00')
        );

        Assert::same($job['UUID_JOB'], $result['uuid_job']);
        Assert::same(false, $processor->transactionOpen);
        Assert::same(false, $db->inTransaction());
    }

    public function testSuccessfulWorkerPersistsInvoiceAndPaymentResult(): void
    {
        $db = TestDatabase::fresh();
        $this->queuedJob($db, 'ORDERRESULT1');
        $worker = $this->workerWithOutcome([
            'ok' => true,
            'uuid_factura' => '11111111-1111-4111-8111-111111111111',
            'uuid_payment' => '22222222-2222-4222-8222-222222222222',
            'num_visible' => 'A2026/1',
        ]);

        $worker->runOne($db, 'worker-a', new \DateTimeImmutable('2030-06-19 10:00:00'));
        $job = $db->query('SELECT * FROM redsys_callback_queue')->fetch(\PDO::FETCH_ASSOC);

        Assert::same('PROCESSED', $job['STATUS']);
        Assert::same('11111111-1111-4111-8111-111111111111', $job['UUID_FACTURA']);
        Assert::same('22222222-2222-4222-8222-222222222222', $job['UUID_PAYMENT']);
    }

    public function testTechnicalFailureSchedulesRetry(): void
    {
        $db = TestDatabase::fresh();
        $this->queuedJob($db, 'ORDERRETRY1');
        $worker = $this->workerWithOutcome(new \PDOException('temporary connection failure'));

        $worker->runOne($db, 'worker-a', new \DateTimeImmutable('2030-06-19 10:00:00'));
        $job = $db->query('SELECT * FROM redsys_callback_queue')->fetch(\PDO::FETCH_ASSOC);

        Assert::same('RETRY', $job['STATUS']);
        Assert::same('2030-06-19 10:01:00', $job['AVAILABLE_AT']);
    }

    public function testFunctionalConflictBecomesIncidentWithoutRetry(): void
    {
        $db = TestDatabase::fresh();
        $this->queuedJob($db, 'ORDERINCIDENT1');
        $worker = $this->workerWithOutcome(SifException::conflict('amount mismatch'));

        $worker->runOne($db, 'worker-a', new \DateTimeImmutable('2030-06-19 10:00:00'));
        $job = $db->query('SELECT * FROM redsys_callback_queue')->fetch(\PDO::FETCH_ASSOC);

        Assert::same('INCIDENT', $job['STATUS']);
        Assert::same(
            1,
            (int) $db->query("SELECT COUNT(*) FROM errors_verifactu WHERE ESTAT = 'OPEN'")->fetchColumn()
        );
    }

    public function testRecoversStaleProcessingLock(): void
    {
        $db = TestDatabase::fresh();
        $this->queuedJob($db, 'ORDERSTALE1');
        $db->exec(
            "UPDATE redsys_callback_queue
             SET STATUS = 'PROCESSING', ATTEMPTS = 1,
                 LOCKED_AT = '2030-06-19 09:00:00', LOCKED_BY = 'dead-worker'"
        );
        $queue = new RedsysCallbackQueueRepository(new UuidGenerator());

        $recovered = $queue->recoverStaleLocks($db, new \DateTimeImmutable('2030-06-19 10:00:00'));
        $job = $db->query('SELECT * FROM redsys_callback_queue')->fetch(\PDO::FETCH_ASSOC);

        Assert::same(1, $recovered);
        Assert::same('RETRY', $job['STATUS']);
        Assert::same('2030-06-19 10:00:00', $job['AVAILABLE_AT']);
        Assert::same(null, $job['LOCKED_BY']);
    }

    public function testFifthTechnicalFailureBecomesIncident(): void
    {
        $db = TestDatabase::fresh();
        $this->queuedJob($db, 'ORDERMAX1');
        $db->exec("UPDATE redsys_callback_queue SET STATUS = 'RETRY', ATTEMPTS = 4");
        $worker = $this->workerWithOutcome(new \PDOException('still unavailable'));

        $worker->runOne($db, 'worker-a', new \DateTimeImmutable('2030-06-19 10:00:00'));
        $job = $db->query('SELECT * FROM redsys_callback_queue')->fetch(\PDO::FETCH_ASSOC);

        Assert::same(5, (int) $job['ATTEMPTS']);
        Assert::same('INCIDENT', $job['STATUS']);
    }

    private function queuedJob(\PDO $db, string $dsOrder): array
    {
        $intent = (new RedsysPaymentIntentService(
            new RedsysPaymentIntentRepository(),
            new UuidGenerator()
        ))->create($db, [
            'ds_order' => $dsOrder,
            'idpag' => 700,
            'source_type' => 'CURS',
            'source_id' => '700',
            'expected_amount' => '80.00',
            'currency' => 'EUR',
            'terminal' => '1',
            'snapshot' => ['billing' => ['tax_id' => '12345678Z']],
        ]);
        $notification = (new RedsysNotificationRepository())->recordReceived(
            $db,
            $dsOrder,
            700,
            '80.00',
            '0000',
            true,
            ['source' => 'test'],
            'VALIDATED'
        );

        return (new RedsysCallbackQueueRepository(new UuidGenerator()))->enqueue(
            $db,
            (int) $notification['notification_id'],
            $intent['uuid_intent']
        );
    }

    private function workerWithOutcome(array|\Throwable $outcome): RedsysCallbackWorker
    {
        return new RedsysCallbackWorker(
            new RedsysCallbackQueueRepository(new UuidGenerator()),
            new OutcomeRedsysJobProcessor($outcome),
            new IncidentRepository(),
            5
        );
    }
}
