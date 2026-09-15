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
                $this->queue->markIncident($db, (int) $job['ID'], $exception->getMessage());
                $this->incidents->open(
                    $db,
                    $job['UUID_FACTURA'] ?? null,
                    'REDSYS_CALLBACK',
                    $this->incidentDetails($job, $exception)
                );

                return ['ok' => false, 'status' => 'INCIDENT'];
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
