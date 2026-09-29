<?php

namespace Prisma\Sif\Repository;

use Prisma\Sif\Exception\SifException;

final class AeatOperationsReadRepository
{
    public function summary(\PDO $db): array
    {
        $queue = (new FiscalQueueMetricsRepository())->snapshot($db);
        $attempts = [];
        foreach ($db->query(
            'SELECT STATUS, COUNT(*) AS TOTAL FROM aeat_submission_attempt GROUP BY STATUS'
        )->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $attempts[(string) $row['STATUS']] = (int) $row['TOTAL'];
        }

        return [
            'queue' => $queue,
            'attempts' => $attempts,
            'open_incidents' => (int) $db->query(
                "SELECT COUNT(*) FROM errors_verifactu
                 WHERE ESTAT = 'OPEN' AND TIPUS_INCIDENCIA LIKE 'AEAT_%'"
            )->fetchColumn(),
        ];
    }

    public function listQueue(\PDO $db, ?string $status, int $limit): array
    {
        $limit = max(1, min(100, $limit));
        $status = $status === null ? null : strtoupper(trim($status));
        $allowed = ['PENDING', 'PROCESSING', 'RETRY', 'REVIEW', 'SENT', 'DEAD_LETTER'];
        if ($status !== null && !in_array($status, $allowed, true)) {
            throw SifException::validation('Invalid AEAT queue status');
        }

        $sql = 'SELECT q.ID, q.UUID_FACTURA, f.NUM_VISIBLE, q.STATUS, q.ATTEMPTS,
                       q.AEAT_CSV, q.AEAT_ERROR_CODE, q.AEAT_ERROR_MESSAGE,
                       q.FLOW_WAIT_SECONDS, q.NEXT_RETRY_AT, q.LOCKED_AT, q.SENT_AT, q.CREATED_AT
                FROM fiscal_queue q
                INNER JOIN factura f ON f.UUID_FACTURA = q.UUID_FACTURA';
        $params = [];
        if ($status !== null) {
            $sql .= ' WHERE q.STATUS = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY q.ID DESC LIMIT ' . $limit;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function detail(\PDO $db, int $queueId): array
    {
        if ($queueId <= 0) {
            throw SifException::validation('Invalid AEAT queue id');
        }

        $queue = $db->prepare(
            'SELECT q.ID, q.UUID_FACTURA, f.NUM_VISIBLE, q.STATUS, q.ATTEMPTS,
                    q.AEAT_CSV, q.AEAT_ERROR_CODE, q.AEAT_ERROR_MESSAGE,
                    q.FLOW_WAIT_SECONDS, q.NEXT_RETRY_AT, q.LOCKED_AT, q.SENT_AT, q.CREATED_AT,
                    JSON_UNQUOTE(JSON_EXTRACT(q.PAYLOAD_JSON, \'$.fiscal_order\')) AS FISCAL_ORDER
             FROM fiscal_queue q
             INNER JOIN factura f ON f.UUID_FACTURA = q.UUID_FACTURA
             WHERE q.ID = ?'
        );
        $queue->execute([$queueId]);
        $item = $queue->fetch(\PDO::FETCH_ASSOC);
        if ($item === false) {
            throw SifException::notFound('AEAT queue item not found');
        }

        $attempts = $db->prepare(
            'SELECT UUID_ATTEMPT, ATTEMPT_NO, ENVIRONMENT, ENDPOINT_CODE, REQUEST_HASH,
                    RESPONSE_CODE, RESPONSE_CSV, STATUS, ERROR_CODE, ERROR_DETAIL,
                    STARTED_AT, FINISHED_AT, CREATED_AT
             FROM aeat_submission_attempt
             WHERE FISCAL_QUEUE_ID = ?
             ORDER BY ATTEMPT_NO, ID'
        );
        $attempts->execute([$queueId]);

        $record = null;
        if ($item['FISCAL_ORDER'] !== null && ctype_digit((string) $item['FISCAL_ORDER'])) {
            $stmt = $db->prepare(
                'SELECT ID, UUID_FACTURA, FISCAL_ORDER, TIPUS_REGISTRE, HASH_FACT, HASH_FACT_ANT,
                        ESTAT_AEAT, DATE_CREATED, DATE_SENT
                 FROM factura_registres
                 WHERE UUID_FACTURA = ? AND FISCAL_ORDER = ?'
            );
            $stmt->execute([$item['UUID_FACTURA'], (int) $item['FISCAL_ORDER']]);
            $record = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        }

        $incidents = $db->prepare(
            "SELECT ID, TIPUS_INCIDENCIA, ESTAT, DETAILS, CREATED_AT, UPDATED_AT
             FROM errors_verifactu
             WHERE UUID_FACTURA = ? AND TIPUS_INCIDENCIA LIKE 'AEAT_%'
             ORDER BY ID DESC LIMIT 50"
        );
        $incidents->execute([$item['UUID_FACTURA']]);

        return [
            'queue' => $item,
            'record' => $record,
            'attempts' => $attempts->fetchAll(\PDO::FETCH_ASSOC),
            'incidents' => $incidents->fetchAll(\PDO::FETCH_ASSOC),
        ];
    }
}
