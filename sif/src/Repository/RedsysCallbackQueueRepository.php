<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;

final class RedsysCallbackQueueRepository
{
    public function __construct(private UuidGenerator $uuidGenerator)
    {
    }

    public function enqueue(\PDO $db, int $notificationId, string $uuidIntent): array
    {
        $uuidJob = $this->uuidGenerator->generate();

        try {
            $db->prepare(
                'INSERT INTO redsys_callback_queue (
                    UUID_JOB, NOTIFICATION_ID, UUID_INTENT, STATUS
                ) VALUES (?, ?, ?, ?)'
            )->execute([$uuidJob, $notificationId, $uuidIntent, 'QUEUED']);
        } catch (\PDOException $exception) {
            if ((string) $exception->getCode() !== '23000') {
                throw $exception;
            }

            $existing = $this->findByNotificationId($db, $notificationId);
            if ($existing === null) {
                throw $exception;
            }

            return $existing;
        }

        return $this->findByNotificationId($db, $notificationId)
            ?? throw new \RuntimeException('Could not reload queued Redsys callback');
    }

    public function findByNotificationId(\PDO $db, int $notificationId): ?array
    {
        $stmt = $db->prepare('SELECT * FROM redsys_callback_queue WHERE NOTIFICATION_ID = ?');
        $stmt->execute([$notificationId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function claimNext(\PDO $db, string $workerId, \DateTimeImmutable $now): ?array
    {
        $timestamp = $now->format('Y-m-d H:i:s');
        $db->beginTransaction();

        try {
            $stmt = $db->prepare(
                "SELECT q.*, n.DS_ORDER, i.SOURCE_TYPE, i.SOURCE_ID, i.SNAPSHOT_JSON
                 FROM redsys_callback_queue q
                 JOIN redsys_notifications n ON n.ID = q.NOTIFICATION_ID
                 JOIN redsys_payment_intent i ON i.UUID_INTENT = q.UUID_INTENT
                 WHERE q.STATUS IN ('QUEUED', 'RETRY') AND q.AVAILABLE_AT <= ?
                 ORDER BY q.AVAILABLE_AT, q.ID
                 LIMIT 1
                 FOR UPDATE"
            );
            $stmt->execute([$timestamp]);
            $job = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$job) {
                $db->commit();

                return null;
            }

            $db->prepare(
                "UPDATE redsys_callback_queue
                 SET STATUS = 'PROCESSING', ATTEMPTS = ATTEMPTS + 1,
                     LOCKED_AT = ?, LOCKED_BY = ?
                 WHERE ID = ?"
            )->execute([$timestamp, $workerId, $job['ID']]);
            $db->commit();

            $job['STATUS'] = 'PROCESSING';
            $job['ATTEMPTS'] = (int) $job['ATTEMPTS'] + 1;
            $job['LOCKED_AT'] = $timestamp;
            $job['LOCKED_BY'] = $workerId;

            return $job;
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }
    }

    public function markProcessed(\PDO $db, int $id, array $result, \DateTimeImmutable $now): void
    {
        $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw SifException::validation('Invalid Redsys callback result');
        }

        $stmt = $db->prepare(
            "UPDATE redsys_callback_queue
             SET STATUS = 'PROCESSED', RESULT_JSON = ?, UUID_FACTURA = ?, UUID_PAYMENT = ?,
                 PROCESSED_AT = ?, LOCKED_AT = NULL, LOCKED_BY = NULL, LAST_ERROR = NULL
             WHERE ID = ? AND STATUS = 'PROCESSING'"
        );
        $stmt->execute([
            $json,
            $result['uuid_factura'] ?? null,
            $result['uuid_payment'] ?? null,
            $now->format('Y-m-d H:i:s'),
            $id,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw SifException::conflict('Redsys callback job is not owned by this worker');
        }
    }

    public function markRetry(
        \PDO $db,
        int $id,
        \DateTimeImmutable $availableAt,
        string $error
    ): void {
        $stmt = $db->prepare(
            "UPDATE redsys_callback_queue
             SET STATUS = 'RETRY', AVAILABLE_AT = ?, LAST_ERROR = ?,
                 LOCKED_AT = NULL, LOCKED_BY = NULL
             WHERE ID = ? AND STATUS = 'PROCESSING'"
        );
        $stmt->execute([$availableAt->format('Y-m-d H:i:s'), $error, $id]);

        if ($stmt->rowCount() !== 1) {
            throw SifException::conflict('Redsys callback job is not owned by this worker');
        }
    }

    public function markIncident(\PDO $db, int $id, string $error): void
    {
        $stmt = $db->prepare(
            "UPDATE redsys_callback_queue
             SET STATUS = 'INCIDENT', LAST_ERROR = ?, LOCKED_AT = NULL, LOCKED_BY = NULL
             WHERE ID = ? AND STATUS = 'PROCESSING'"
        );
        $stmt->execute([$error, $id]);

        if ($stmt->rowCount() !== 1) {
            throw SifException::conflict('Redsys callback job is not owned by this worker');
        }
    }

    public function recoverStaleLocks(\PDO $db, \DateTimeImmutable $now): int
    {
        $stmt = $db->prepare(
            "UPDATE redsys_callback_queue
             SET STATUS = 'RETRY', AVAILABLE_AT = ?, LOCKED_AT = NULL, LOCKED_BY = NULL,
                 LAST_ERROR = 'Recovered stale processing lock'
             WHERE STATUS = 'PROCESSING' AND LOCKED_AT < ?"
        );
        $stmt->execute([
            $now->format('Y-m-d H:i:s'),
            $now->modify('-15 minutes')->format('Y-m-d H:i:s'),
        ]);

        return $stmt->rowCount();
    }
}
