<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\IncidentRepository;
use Prisma\Sif\Repository\RedsysCallbackQueueRepository;

final class RedsysCallbackWorker
{
    public function __construct(
        private RedsysCallbackQueueRepository $queue,
        private RedsysJobProcessor $processor,
        private IncidentRepository $incidents,
        private int $maxAttempts
    ) {
    }

    public function runOne(\PDO $db, string $workerId, \DateTimeImmutable $now): ?array
    {
        $this->queue->recoverStaleLocks($db, $now);
        $job = $this->queue->claimNext($db, $workerId, $now);
        if ($job === null) {
            return null;
        }

        try {
            $result = $this->processor->process($db, $job);
            $this->queue->markProcessed($db, (int) $job['ID'], $result, $now);

            return $result;
        } catch (\Throwable $exception) {
            $attempts = (int) $job['ATTEMPTS'];
            $functional = $exception instanceof SifException
                && in_array($exception->getCode(), [409, 422], true);

            if ($functional || $attempts >= $this->maxAttempts) {
                $incident = $this->moveToIncident($db, $job, $workerId, $exception);

                return [
                    'ok' => false,
                    'status' => 'INCIDENT',
                    'incident_id' => $incident['incident_id'],
                    'uuid_incident' => $incident['uuid_incident'],
                ];
            }

            $delay = $this->retryDelayMinutes($attempts);
            $this->queue->markRetry(
                $db,
                (int) $job['ID'],
                $now->modify("+{$delay} minutes"),
                $exception->getMessage()
            );

            return ['ok' => false, 'status' => 'RETRY'];
        }
    }

    private function moveToIncident(
        \PDO $db,
        array $job,
        string $workerId,
        \Throwable $exception
    ): array {
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) {
            $db->beginTransaction();
        }

        try {
            $this->queue->markIncident($db, (int) $job['ID'], $exception->getMessage());
            $incident = $this->incidents->openDetailed($db, [
                'uuid_factura' => $job['UUID_FACTURA'] ?? null,
                'resource_type' => 'REDSYS_CALLBACK_JOB',
                'resource_id' => (string) ($job['UUID_JOB'] ?? $job['ID']),
                'source_type' => 'REDSYS_WORKER',
                'source_id' => $workerId,
                'type' => 'REDSYS_CALLBACK',
                'message' => $this->incidentDetails($job, $exception),
                'severity' => 'HIGH',
                'correlation_id' => (string) ($job['UUID_JOB'] ?? ('REDSYS_QUEUE:' . $job['ID'])),
                'idempotency_key' => 'REDSYS_CALLBACK|JOB:' . (string) ($job['UUID_JOB'] ?? $job['ID']),
                'reason_code' => 'CALLBACK_PROCESSING_FAILED',
            ]);

            if ($ownsTransaction) {
                $db->commit();
            }

            return $incident;
        } catch (\Throwable $failure) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $failure;
        }
    }

    private function retryDelayMinutes(int $attempts): int
    {
        return match ($attempts) {
            1 => 1,
            2 => 5,
            3 => 15,
            default => 60,
        };
    }

    private function incidentDetails(array $job, \Throwable $exception): string
    {
        $details = json_encode([
            'ds_order' => $job['DS_ORDER'] ?? null,
            'uuid_job' => $job['UUID_JOB'] ?? null,
            'attempts' => (int) ($job['ATTEMPTS'] ?? 0),
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $details === false ? $exception->getMessage() : $details;
    }
}
