<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Aeat\XmlCodec;
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\{FiscalQueueRepository, IncidentRepository};

final class AeatReviewReconciliationService
{
    public function __construct(
        private TransactionRunner $transactions,
        private FiscalQueueRepository $queue,
        private IncidentRepository $incidents
    ) {
    }

    public function reconcile(int $queueId, string $attemptUuid, string $actorId): array
    {
        $attemptUuid = strtolower(trim($attemptUuid));
        $actorId = trim($actorId);

        if ($queueId <= 0) {
            throw SifException::validation('Invalid AEAT queue id');
        }
        if (preg_match('/^[0-9a-f-]{36}$/D', $attemptUuid) !== 1) {
            throw SifException::validation('Invalid AEAT attempt uuid');
        }
        if ($actorId === '' || mb_strlen($actorId, 'UTF-8') > 120) {
            throw SifException::validation('Invalid reconciliation actor');
        }

        return $this->transactions->run(function (\PDO $db) use ($queueId, $attemptUuid, $actorId): array {
            $item = $this->queue->reviewForUpdate($db, $queueId);

            $attempt = $db->prepare(
                'SELECT UUID_ATTEMPT, FISCAL_QUEUE_ID, ATTEMPT_NO, REQUEST_HASH, RESPONSE_JSON,
                        RESPONSE_CSV, RESPONSE_CODE, STATUS, ERROR_CODE, ERROR_DETAIL,
                        STARTED_AT, FINISHED_AT
                 FROM aeat_submission_attempt
                 WHERE UUID_ATTEMPT = ? AND FISCAL_QUEUE_ID = ?
                 LIMIT 1
                 FOR UPDATE'
            );
            $attempt->execute([$attemptUuid, $queueId]);
            $row = $attempt->fetch(\PDO::FETCH_ASSOC);
            if ($row === false) {
                throw SifException::notFound('AEAT submission attempt not found for review job');
            }

            $remoteStatus = strtoupper((string) $row['STATUS']);
            if (!in_array($remoteStatus, ['ACCEPTED', 'ACCEPTED_WITH_ERRORS', 'REJECTED'], true)) {
                throw SifException::conflict('AEAT review cannot be reconciled without a persisted terminal remote result');
            }

            $response = json_decode((string) ($row['RESPONSE_JSON'] ?? ''), true);
            if (!is_array($response)) {
                throw SifException::conflict('AEAT review attempt has no usable persisted response');
            }

            $payload = json_decode((string) ($item['PAYLOAD_JSON'] ?? ''), true);
            if (!is_array($payload)
                || !isset($payload['fiscal_order'])
                || !isset($payload['aeat'])
                || !is_array($payload['aeat'])) {
                throw SifException::conflict('AEAT review queue payload is incomplete');
            }

            $requestXml = (new XmlCodec())->request($payload['aeat']);

            $this->queue->reconcileReview(
                $db,
                $item,
                $remoteStatus,
                $response,
                $requestXml
            );

            $this->incidents->resolveAeatQueueReview($db, (string) $item['UUID_FACTURA'], $queueId);
            $this->incidents->open(
                $db,
                (string) $item['UUID_FACTURA'],
                'AEAT_RECONCILED',
                'Queue ID ' . $queueId . ': reconciled attempt ' . $attemptUuid . ' by ' . $actorId
            );

            return [
                'ok' => true,
                'queue_id' => $queueId,
                'attempt_uuid' => $attemptUuid,
                'queue_status' => 'SENT',
                'aeat_status' => $remoteStatus,
                'reconciled_without_resend' => true,
            ];
        });
    }
}
