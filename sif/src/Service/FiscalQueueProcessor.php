<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Contract\AeatTransport;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Repository\FiscalQueueRepository;

final class FiscalQueueProcessor
{
    public function __construct(
        private TransactionRunner $transactions,
        private FiscalQueueRepository $queue,
        private AeatTransport $transport,
        private int $maxAttempts = 3,
        private int $baseRetrySeconds = 60,
        private int $maxRetrySeconds = 3600
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
            return $this->failure($item, new \RuntimeException('Invalid queued fiscal payload.'));
        }

        try {
            $transportResult = $this->transport->send($payload);
            $status = strtoupper((string) ($transportResult['status'] ?? ''));
            if (!in_array($status, ['ACCEPTED', 'ACCEPTED_WITH_ERRORS', 'REJECTED'], true)) {
                throw new \RuntimeException('Invalid AEAT transport status.');
            }
            $response = $transportResult['response'] ?? null;
            if (!is_array($response)) {
                throw new \RuntimeException('Invalid AEAT transport response.');
            }

            $this->transactions->run(function (\PDO $db) use ($item, $status, $response, $transportResult): void {
                $this->queue->complete(
                    $db,
                    $item,
                    $status,
                    $response,
                    isset($transportResult['request_xml']) ? (string) $transportResult['request_xml'] : null
                );
            });

            return [
                'ok' => true,
                'processed' => true,
                'queue_id' => (int) $item['ID'],
                'attempts' => (int) $item['ATTEMPTS'],
                'aeat_status' => $status,
            ];
        } catch (\Throwable $exception) {
            return $this->failure($item, $exception);
        }
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

    private function failure(array $item, \Throwable $exception): array
    {
        $delay = min(
            $this->maxRetrySeconds,
            $this->baseRetrySeconds * (2 ** max(0, (int) $item['ATTEMPTS'] - 1))
        );
        $nextRetryAt = (new \DateTimeImmutable('now'))
            ->modify('+' . $delay . ' seconds')
            ->format('Y-m-d H:i:s');
        $status = $this->transactions->run(
            fn (\PDO $db): string => $this->queue->fail(
                $db,
                $item,
                $exception->getMessage(),
                $this->maxAttempts,
                $nextRetryAt
            )
        );

        return [
            'ok' => false,
            'processed' => true,
            'queue_id' => (int) $item['ID'],
            'attempts' => (int) $item['ATTEMPTS'],
            'queue_status' => $status,
            'next_retry_at' => $status === 'RETRY' ? $nextRetryAt : null,
            'error' => $exception->getMessage(),
        ];
    }
}
