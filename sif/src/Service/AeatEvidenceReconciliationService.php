<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Aeat\{EvidenceStore, EvidenceVerifier, ResponseParser, XmlCodec};
use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Domain\UuidGenerator;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\{
    AeatSubmissionAttemptRepository,
    FiscalQueueRepository,
    IncidentRepository,
    OperationalEventRepository
};

final class AeatEvidenceReconciliationService
{
    public function __construct(
        private TransactionRunner $transactions,
        private FiscalQueueRepository $queue,
        private IncidentRepository $incidents,
        private AeatSubmissionAttemptRepository $attempts,
        private string $evidenceDirectory
    ) {
        $this->evidenceDirectory = trim($this->evidenceDirectory);
        if ($this->evidenceDirectory === '') {
            throw new \InvalidArgumentException('AEAT evidence directory is not configured.');
        }

        // Reuse the same private/outside-repository policy enforced during writes.
        new EvidenceStore($this->evidenceDirectory);
    }

    public function reconcile(int $queueId, string $attemptUuid, string $actorId): array
    {
        $attemptUuid = strtolower(trim($attemptUuid));
        $actorId = trim($actorId);

        if ($queueId <= 0) {
            throw SifException::validation('Invalid AEAT queue id');
        }
        if (preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D',
            $attemptUuid
        ) !== 1) {
            throw SifException::validation('Invalid AEAT attempt uuid');
        }
        if ($actorId === '' || mb_strlen($actorId, 'UTF-8') > 120) {
            throw SifException::validation('Invalid reconciliation actor');
        }

        return $this->transactions->run(function (\PDO $db) use (
            $queueId,
            $attemptUuid,
            $actorId
        ): array {
            $item = $this->queue->reviewForUpdate($db, $queueId);

            $attempt = $db->prepare(
                'SELECT UUID_ATTEMPT, FISCAL_QUEUE_ID, ATTEMPT_NO, REQUEST_HASH, EVIDENCE_ID,
                        RESPONSE_JSON, RESPONSE_CSV, RESPONSE_CODE, STATUS, ERROR_CODE, ERROR_DETAIL,
                        STARTED_AT, FINISHED_AT
                 FROM aeat_submission_attempt
                 WHERE UUID_ATTEMPT = ? AND FISCAL_QUEUE_ID = ?
                 LIMIT 1
                 FOR UPDATE'
            );
            $attempt->execute([$attemptUuid, $queueId]);
            $row = $attempt->fetch(\PDO::FETCH_ASSOC);
            if ($row === false) {
                throw SifException::notFound('AEAT submission attempt not found for evidence review');
            }

            $latestAttemptNo = (int) $db->query(
                'SELECT COALESCE(MAX(ATTEMPT_NO), 0)
                 FROM aeat_submission_attempt
                 WHERE FISCAL_QUEUE_ID = ' . (int) $queueId
            )->fetchColumn();
            if ((int) $row['ATTEMPT_NO'] !== $latestAttemptNo) {
                throw SifException::conflict(
                    'Only the latest AEAT submission attempt can reconcile evidence'
                );
            }

            if (strtoupper((string) $row['STATUS']) !== 'UNCERTAIN') {
                throw SifException::conflict(
                    'Only an UNCERTAIN AEAT attempt can be reconciled from private evidence'
                );
            }

            $evidenceId = trim((string) ($row['EVIDENCE_ID'] ?? ''));
            if (preg_match('/^\d{8}T\d{6}Z-[a-f0-9]{24}$/D', $evidenceId) !== 1) {
                throw SifException::conflict(
                    'UNCERTAIN AEAT attempt has no structured evidence reference'
                );
            }

            $payload = json_decode((string) ($item['PAYLOAD_JSON'] ?? ''), true);
            if (!is_array($payload)
                || !isset($payload['fiscal_order'])
                || !isset($payload['aeat'])
                || !is_array($payload['aeat'])
            ) {
                throw SifException::conflict('AEAT review queue payload is incomplete');
            }

            $requestXml = (new XmlCodec())->request($payload['aeat']);
            $requestHash = hash('sha256', $requestXml);
            if (!hash_equals((string) $row['REQUEST_HASH'], $requestHash)) {
                throw SifException::conflict(
                    'AEAT uncertain attempt request hash does not match immutable fiscal payload'
                );
            }

            try {
                $pair = (new EvidenceVerifier())->readVerifiedPair(
                    $this->evidenceDirectory,
                    $evidenceId
                );
            } catch (\Throwable $exception) {
                throw SifException::conflict(
                    'AEAT private evidence is incomplete, invalid or has changed'
                );
            }

            if ((int) ($pair['response_http_status'] ?? 0) !== 200) {
                throw SifException::conflict(
                    'AEAT evidence HTTP status is not a successful delivery'
                );
            }

            if (!hash_equals(
                hash('sha256', $requestXml),
                (string) ($pair['request_sha256'] ?? '')
            ) || (string) ($pair['request_xml'] ?? '') !== $requestXml) {
                throw SifException::conflict(
                    'AEAT evidence request does not match immutable fiscal payload'
                );
            }

            try {
                $parsed = (new ResponseParser())->parse(
                    (string) ($pair['response_xml'] ?? ''),
                    $payload['aeat']
                );
            } catch (\Throwable $exception) {
                throw SifException::conflict(
                    'AEAT evidence response cannot be validated for this fiscal record'
                );
            }

            $remoteStatus = strtoupper((string) ($parsed['status'] ?? ''));
            $response = $parsed['response'] ?? null;
            if (!in_array(
                $remoteStatus,
                ['ACCEPTED', 'ACCEPTED_WITH_ERRORS', 'REJECTED'],
                true
            ) || !is_array($response)) {
                throw SifException::conflict('AEAT evidence has no terminal remote result');
            }

            $response['evidence_id'] = $evidenceId;
            $response['evidence_response_sha256'] = (string) (
                $pair['response_sha256'] ?? ''
            );

            $this->attempts->completeUncertainFromEvidence(
                $db,
                $attemptUuid,
                $evidenceId,
                $remoteStatus,
                $response
            );

            $this->queue->reconcileReview(
                $db,
                $item,
                $remoteStatus,
                $response,
                $requestXml
            );

            $this->incidents->resolveAeatQueueReview(
                $db,
                (string) $item['UUID_FACTURA'],
                $queueId
            );

            (new OperationalEventRepository(new UuidGenerator()))->append($db, [
                'operation_type' => 'AEAT_RECONCILE',
                'source_type' => 'FISCAL_QUEUE',
                'source_id' => (string) $queueId,
                'uuid_factura' => (string) $item['UUID_FACTURA'],
                'uuid_payment' => null,
                'fiscal_impact' => 'STATE_UPDATE',
                'economic_impact' => 'NONE',
                'status' => 'COMPLETED',
                'reason_code' => 'AEAT_EVIDENCE_RECONCILED',
                'before_snapshot' => [
                    'queue_status' => 'REVIEW',
                    'attempt_uuid' => $attemptUuid,
                    'attempt_status' => 'UNCERTAIN',
                    'evidence_id' => $evidenceId,
                ],
                'after_snapshot' => [
                    'queue_status' => 'SENT',
                    'aeat_status' => $remoteStatus,
                    'evidence_response_sha256' => (string) (
                        $pair['response_sha256'] ?? ''
                    ),
                ],
                'actor_type' => 'USER',
                'actor_id' => $actorId,
                'actor_role' => null,
                'source_channel' => 'INTRANET',
                'correlation_id' => 'AEAT-EVIDENCE-RECONCILE:'
                    . $queueId . ':' . $attemptUuid,
                'occurred_at' => (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'),
            ]);

            return [
                'ok' => true,
                'queue_id' => $queueId,
                'attempt_uuid' => $attemptUuid,
                'evidence_id' => $evidenceId,
                'queue_status' => 'SENT',
                'aeat_status' => $remoteStatus,
                'reconciled_from_verified_evidence' => true,
                'reconciled_without_resend' => true,
            ];
        });
    }
}
