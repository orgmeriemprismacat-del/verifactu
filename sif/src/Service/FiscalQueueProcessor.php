<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Contract\AeatTransport;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Exception\AeatDeliveryUncertainException;
use Prisma\Sif\Repository\{AeatSubmissionAttemptRepository, FiscalQueueRepository, IncidentRepository};

final class FiscalQueueProcessor
{
    public function __construct(
        private TransactionRunner $transactions,
        private FiscalQueueRepository $queue,
        private AeatTransport $transport,
        private int $maxAttempts = 3,
        private int $baseRetrySeconds = 60,
        private int $maxRetrySeconds = 3600,
        private ?AeatSubmissionAttemptRepository $attempts = null
    ) {
        if ($maxAttempts < 1) {
            throw new \InvalidArgumentException('maxAttempts must be at least 1.');
        }
        if ($baseRetrySeconds < 1 || $maxRetrySeconds < $baseRetrySeconds) {
            throw new \InvalidArgumentException('Invalid retry backoff configuration.');
        }
    }

    public function processNext(): array
    {
        $item = $this->transactions->run(
            fn (\PDO $db): ?array => $this->queue->claimNext($db, $this->maxAttempts)
        );
        if ($item === null) {
            return ['ok' => true, 'processed' => false];
        }

        $payload = json_decode((string) $item['PAYLOAD_JSON'], true);
        if (!is_array($payload)) {
            return $this->integrityFailure($item, new \RuntimeException('Invalid queued fiscal payload.'));
        }

        try {
            $this->transactions->run(function (\PDO $db) use ($item): void {
                $this->queue->assertImmutablePayload($db, $item, new PayloadIdempotencyValidator());
            });
        } catch (\Throwable $exception) {
            return $this->integrityFailure($item, $exception);
        }

        $attemptUuid = null;
        if ($this->attempts !== null) {
            try {
                $attemptUuid = $this->transactions->run(
                    fn (\PDO $db): string => $this->attempts->begin($db, $item, $payload)
                );
            } catch (\Throwable $exception) {
                return $this->failure($item, $exception);
            }
        }

        try {
            $transportResult = $this->transport->send($payload);
        } catch (AeatDeliveryUncertainException $exception) {
            return $this->reviewHold($item, $attemptUuid, $exception, 'AEAT_DELIVERY_UNCERTAIN', true);
        } catch (\Throwable $exception) {
            if ($attemptUuid !== null) {
                try {
                    $this->transactions->run(
                        function (\PDO $db) use ($attemptUuid, $exception): void {
                            $this->attempts->fail($db, $attemptUuid, 'FAILED', $exception->getMessage());
                        }
                    );
                } catch (\Throwable) {
                    return $this->reviewHold($item, $attemptUuid, $exception, 'AEAT_ATTEMPT_PERSISTENCE_ERROR', false);
                }
            }
            return $this->failure($item, $exception);
        }

        $status = strtoupper((string) ($transportResult['status'] ?? ''));
        if (!in_array($status, ['ACCEPTED', 'ACCEPTED_WITH_ERRORS', 'REJECTED'], true)) {
            return $this->reviewHold(
                $item,
                $attemptUuid,
                new \RuntimeException('Invalid AEAT transport status.'),
                'AEAT_REMOTE_RESULT_INVALID',
                true
            );
        }
        $response = $transportResult['response'] ?? null;
        if (!is_array($response)) {
            return $this->reviewHold(
                $item,
                $attemptUuid,
                new \RuntimeException('Invalid AEAT transport response.'),
                'AEAT_REMOTE_RESULT_INVALID',
                true
            );
        }

        if ($attemptUuid !== null) {
            try {
                $this->transactions->run(
                    function (\PDO $db) use ($attemptUuid, $status, $response): void {
                        $this->attempts->complete($db, $attemptUuid, $status, $response);
                    }
                );
            } catch (\Throwable $exception) {
                return $this->reviewHold($item, $attemptUuid, $exception, 'AEAT_REMOTE_RESULT_NOT_PERSISTED', true);
            }
        }

        try {
            $this->transactions->run(function (\PDO $db) use ($item, $status, $response, $transportResult): void {
                $this->queue->complete(
                    $db,
                    $item,
                    $status,
                    $response,
                    isset($transportResult['request_xml']) ? (string) $transportResult['request_xml'] : null
                );
            });
        } catch (\Throwable $exception) {
            return $this->reviewHold($item, $attemptUuid, $exception, 'AEAT_REMOTE_RESULT_PENDING_LOCAL_COMMIT', false);
        }

        return [
            'ok' => true,
            'processed' => true,
            'queue_id' => (int) $item['ID'],
            'attempts' => (int) $item['ATTEMPTS'],
            'attempt_uuid' => $attemptUuid,
            'aeat_status' => $status,
            'requires_review' => ($response['requires_review'] ?? false) === true
                || ($response['duplicate'] ?? false) === true || $status !== 'ACCEPTED',
        ];
    }

    public function processBatch(int $limit): array
    {
        if ($limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('Batch limit must be between 1 and 100.');
        }

        $results = [];
        for ($index = 0; $index < $limit; $index++) {
            $result = $this->processNext();
            if (!$result['processed']) {
                break;
            }
            $results[] = $result;
            if (!$result['ok']) {
                break;
            }
        }

        return [
            'ok' => !array_filter($results, static fn (array $result): bool => !$result['ok']),
            'processed' => count($results),
            'results' => $results,
        ];
    }

    public function recoverStaleLocks(int $olderThanSeconds, ?\DateTimeImmutable $now = null): int
    {
        if ($olderThanSeconds < 60) {
            throw new \InvalidArgumentException('Stale lock threshold must be at least 60 seconds.');
        }

        $now ??= new \DateTimeImmutable('now');
        $lockedBefore = $now->modify('-' . $olderThanSeconds . ' seconds')->format('Y-m-d H:i:s');

        return $this->transactions->run(
            fn (\PDO $db): int => $this->queue->recoverStaleLocks($db, $lockedBefore)
        );
    }

    private function integrityFailure(array $item, \Throwable $exception): array
    {
        $message = 'Fiscal queue integrity mismatch: ' . $exception->getMessage();
        $incident = $this->transactions->run(function (\PDO $db) use ($item, $message): array {
            $this->queue->rejectIntegrity($db, $item, $message);

            return (new IncidentRepository())->openDetailed($db, [
                'uuid_factura' => (string) $item['UUID_FACTURA'],
                'resource_type' => 'FISCAL_QUEUE',
                'resource_id' => (string) $item['ID'],
                'source_type' => 'AEAT_WORKER',
                'source_id' => (string) $item['ID'],
                'type' => 'FISCAL_PAYLOAD_CONFLICT',
                'message' => 'Queue ID ' . $item['ID'] . ': ' . $message,
                'severity' => 'CRITICAL',
                'correlation_id' => 'FISCAL_QUEUE:' . $item['ID'],
                'idempotency_key' => 'FISCAL_PAYLOAD_CONFLICT|QUEUE:' . $item['ID'],
                'reason_code' => 'IMMUTABLE_PAYLOAD_MISMATCH',
            ]);
        });

        return [
            'ok' => false,
            'processed' => true,
            'queue_id' => (int) $item['ID'],
            'attempts' => (int) $item['ATTEMPTS'],
            'queue_status' => 'DEAD_LETTER',
            'next_retry_at' => null,
            'incident_id' => $incident['incident_id'],
            'uuid_incident' => $incident['uuid_incident'],
            'error' => $message,
        ];
    }

    private function failure(array $item, \Throwable $exception): array
    {
        $delay = min(
            $this->maxRetrySeconds,
            $this->baseRetrySeconds * (2 ** max(0, (int) $item['ATTEMPTS'] - 1))
        );
        $nextRetryAt = (new \DateTimeImmutable('now'))
            ->modify('+' . $delay . ' seconds')
            ->format('Y-m-d H:i:s');

        $outcome = $this->transactions->run(function (\PDO $db) use (
            $item,
            $exception,
            $nextRetryAt
        ): array {
            $status = $this->queue->fail(
                $db,
                $item,
                $exception->getMessage(),
                $this->maxAttempts,
                $nextRetryAt
            );

            $incident = null;
            if ($status === 'DEAD_LETTER') {
                $incident = (new IncidentRepository())->openDetailed($db, [
                    'uuid_factura' => (string) $item['UUID_FACTURA'],
                    'resource_type' => 'FISCAL_QUEUE',
                    'resource_id' => (string) $item['ID'],
                    'source_type' => 'AEAT_WORKER',
                    'source_id' => (string) $item['ID'],
                    'type' => 'AEAT_DEAD_LETTER',
                    'message' => 'Queue ID ' . $item['ID'] . ': ' . $exception->getMessage(),
                    'severity' => 'HIGH',
                    'correlation_id' => 'FISCAL_QUEUE:' . $item['ID'],
                    'idempotency_key' => 'AEAT_DEAD_LETTER|QUEUE:' . $item['ID'],
                    'reason_code' => 'AEAT_RETRIES_EXHAUSTED',
                ]);
            }

            return ['status' => $status, 'incident' => $incident];
        });

        $status = $outcome['status'];
        $incident = $outcome['incident'];

        return [
            'ok' => false,
            'processed' => true,
            'queue_id' => (int) $item['ID'],
            'attempts' => (int) $item['ATTEMPTS'],
            'queue_status' => $status,
            'next_retry_at' => $status === 'RETRY' ? $nextRetryAt : null,
            'incident_id' => is_array($incident) ? $incident['incident_id'] : null,
            'uuid_incident' => is_array($incident) ? $incident['uuid_incident'] : null,
            'error' => $exception->getMessage(),
        ];
    }

    private function reviewHold(
        array $item,
        ?string $attemptUuid,
        \Throwable $exception,
        string $incidentType,
        bool $markAttemptUncertain
    ): array {
        $message = $exception->getMessage();
        $incident = $this->transactions->run(function (\PDO $db) use (
            $item,
            $attemptUuid,
            $message,
            $incidentType,
            $markAttemptUncertain
        ): array {
            if ($attemptUuid !== null && $markAttemptUncertain && $this->attempts !== null) {
                $this->attempts->fail($db, $attemptUuid, 'UNCERTAIN', $message);
            }
            $this->queue->holdForReview($db, $item, $message);

            return (new IncidentRepository())->openDetailed($db, [
                'uuid_factura' => (string) $item['UUID_FACTURA'],
                'resource_type' => 'FISCAL_QUEUE',
                'resource_id' => (string) $item['ID'],
                'source_type' => 'AEAT_WORKER',
                'source_id' => (string) $item['ID'],
                'type' => $incidentType,
                'message' => 'Queue ID ' . $item['ID'] . ': ' . $message,
                'severity' => 'HIGH',
                'correlation_id' => 'FISCAL_QUEUE:' . $item['ID'],
                'idempotency_key' => $incidentType . '|QUEUE:' . $item['ID'],
                'reason_code' => $incidentType,
            ]);
        });

        return [
            'ok' => false,
            'processed' => true,
            'queue_id' => (int) $item['ID'],
            'attempts' => (int) $item['ATTEMPTS'],
            'attempt_uuid' => $attemptUuid,
            'queue_status' => 'REVIEW',
            'requires_review' => true,
            'next_retry_at' => null,
            'incident_id' => $incident['incident_id'],
            'uuid_incident' => $incident['uuid_incident'],
            'error' => $message,
        ];
    }
}
