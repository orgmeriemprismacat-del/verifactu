<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\UsocLifecycleExecutionRepository;

final class UsocCourseChangeLegacyHandoffService
{
    public function __construct(
        private UsocLifecycleExecutionRepository $executions
    ) {
    }

    public function confirm(
        \PDO $sifDb,
        \PDO $legacyDb,
        string $requestId,
        string $actorId
    ): array {
        $requestId = trim($requestId);
        $actorId = trim($actorId);

        if (
            $requestId === ''
            || strlen($requestId) > 120
            || preg_match('/^[A-Za-z0-9._:-]+$/D', $requestId) !== 1
            || $actorId === ''
        ) {
            throw SifException::validation(
                'Invalid USOC course change legacy handoff identity'
            );
        }

        $sifDb->beginTransaction();
        try {
            $execution = $this->executions->findByRequestId(
                $sifDb,
                $requestId,
                true
            );

            if ($execution === null) {
                throw SifException::conflict(
                    'USOC course change preparation checkpoint not found'
                );
            }

            if (
                (string) ($execution['OPERATION'] ?? '') !== 'COURSE_CHANGE'
                || (string) ($execution['ACTOR_ID'] ?? '') !== $actorId
            ) {
                throw SifException::conflict(
                    'USOC course change legacy handoff identity does not match checkpoint'
                );
            }

            if ((string) ($execution['STATE'] ?? '') === 'REVIEW_REQUIRED') {
                throw SifException::conflict(
                    'USOC course change legacy handoff requires review: '
                    . (string) ($execution['REVIEW_REASON'] ?? 'UNKNOWN')
                );
            }

            if ((string) ($execution['STATE'] ?? '') === 'COMPLETED') {
                $result = $this->decode(
                    (string) ($execution['RESULT_JSON'] ?? ''),
                    'completed USOC course change result'
                );

                if (($result['legacy_handoff_completed'] ?? false) !== true) {
                    throw SifException::conflict(
                        'Completed USOC course change has no durable legacy handoff evidence'
                    );
                }

                $sifDb->commit();

                return [
                    'ok' => true,
                    'request_id' => $requestId,
                    'completed' => true,
                    'ready_for_legacy' => false,
                    'phase' => 'COMPLETED',
                    'idempotency_reused' => true,
                ];
            }

            if ((string) ($execution['STATE'] ?? '') !== 'REQUESTED') {
                throw SifException::conflict(
                    'USOC course change legacy handoff cannot be confirmed from current state'
                );
            }

            $bound = $this->decode(
                (string) ($execution['RESULT_JSON'] ?? ''),
                'bound USOC course change destination'
            );
            $phase = strtoupper(trim((string) ($bound['phase'] ?? '')));
            if (!in_array($phase, ['DESTINATION_RESERVED', 'LEGACY_COMPLETED'], true)) {
                throw SifException::conflict(
                    'USOC course change destination has not been durably bound'
                );
            }

            $request = $this->decode(
                (string) ($execution['REQUEST_JSON'] ?? ''),
                'USOC course change request'
            );
            $plan = $this->decode(
                (string) ($execution['PLAN_JSON'] ?? ''),
                'USOC course change plan'
            );

            $targetMeta = $request['target'] ?? null;
            $target = $plan['target'] ?? null;
            if (!is_array($targetMeta) || !is_array($target)) {
                throw SifException::conflict(
                    'Stored USOC course change target is incomplete'
                );
            }

            $sourceIdInsc = (int) ($execution['ID_INSC'] ?? 0);
            $sourceIdpag = (int) ($execution['IDPAG'] ?? 0);
            $destinationIdInsc = (int) ($bound['destination_id_insc'] ?? 0);
            $destinationIdpag = (int) ($bound['destination_idpag'] ?? 0);
            $marker = trim((string) ($bound['reservation_marker'] ?? ''));
            $expectedTotal = $this->money(
                $target['target_student_total'] ?? null
            );

            if (
                $sourceIdInsc <= 0
                || $sourceIdpag <= 0
                || $destinationIdInsc <= 0
                || $destinationIdpag <= 0
                || $destinationIdpag === $sourceIdpag
                || preg_match('/^SIF-USOC-CC:[a-f0-9]{32}$/D', $marker) !== 1
            ) {
                throw SifException::conflict(
                    'Stored USOC course change destination identity is invalid'
                );
            }

            $source = $this->source($legacyDb, $sourceIdInsc);
            $destination = $this->destination($legacyDb, $destinationIdInsc);

            $this->assertSourceIdentity(
                $source,
                $sourceIdInsc,
                $sourceIdpag
            );
            $this->assertDestination(
                $destination,
                $destinationIdInsc,
                $destinationIdpag,
                $marker,
                $targetMeta,
                $expectedTotal
            );

            $sourceStatus = strtoupper(trim((string) ($source['status'] ?? '')));
            if (in_array($sourceStatus, ['0', '1', 'M'], true)) {
                if ($phase === 'LEGACY_COMPLETED') {
                    throw SifException::conflict(
                        'Durable USOC legacy handoff checkpoint contradicts current legacy state'
                    );
                }

                $sifDb->commit();

                return [
                    'ok' => true,
                    'request_id' => $requestId,
                    'completed' => false,
                    'ready_for_legacy' => true,
                    'phase' => 'DESTINATION_RESERVED',
                    'destination_id_insc' => $destinationIdInsc,
                    'destination_idpag' => $destinationIdpag,
                    'idempotency_reused' => false,
                ];
            }

            if ($sourceStatus !== 'C') {
                $this->review(
                    $sifDb,
                    $requestId,
                    'LEGACY_SOURCE_STATUS_' . ($sourceStatus !== '' ? $sourceStatus : 'EMPTY'),
                    $bound
                );
            }

            if (
                $this->money($source['pagament'] ?? null) !== '0.00'
                || trim((string) ($source['data_baixa'] ?? '')) === ''
            ) {
                $this->review(
                    $sifDb,
                    $requestId,
                    'LEGACY_SOURCE_NOT_CLOSED_CLEANLY',
                    $bound
                );
            }

            $confirmed = $bound;
            $confirmed['phase'] = 'LEGACY_COMPLETED';
            $confirmed['source_closed'] = true;
            $confirmed['legacy_handoff_completed'] = true;
            $confirmed['legacy_source_status'] = 'C';
            $confirmed['legacy_source_closed_at'] = (string) $source['data_baixa'];
            $confirmed['effects_applied'] = false;

            if ($phase === 'LEGACY_COMPLETED') {
                if ($confirmed != $bound) {
                    throw SifException::conflict(
                        'Stored USOC legacy handoff checkpoint differs from verified legacy state'
                    );
                }

                $sifDb->commit();

                return [
                    'ok' => true,
                    'request_id' => $requestId,
                    'completed' => true,
                    'ready_for_legacy' => false,
                    'phase' => 'LEGACY_COMPLETED',
                    'destination_id_insc' => $destinationIdInsc,
                    'destination_idpag' => $destinationIdpag,
                    'idempotency_reused' => true,
                ];
            }

            $stored = $this->executions->advanceRequestedResult(
                $sifDb,
                $requestId,
                'DESTINATION_RESERVED',
                $confirmed
            );

            $sifDb->commit();

            return [
                'ok' => true,
                'request_id' => $requestId,
                'uuid_execution' => (string) $stored['UUID_EXECUTION'],
                'completed' => true,
                'ready_for_legacy' => false,
                'phase' => 'LEGACY_COMPLETED',
                'destination_id_insc' => $destinationIdInsc,
                'destination_idpag' => $destinationIdpag,
                'idempotency_reused' => false,
            ];
        } catch (\Throwable $exception) {
            if ($sifDb->inTransaction()) {
                $sifDb->rollBack();
            }
            throw $exception;
        }
    }

    private function source(\PDO $db, int $idInsc): array
    {
        $stmt = $db->prepare(
            "SELECT ID, IDPAG, TIPUS_DESC, VALID_DESC, `INSC CURS`,
                    PAGAMENT, DATA_BAIXA
             FROM inscripcions
             WHERE ID = ?
             LIMIT 2"
        );
        $stmt->execute([$idInsc]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($rows) !== 1) {
            throw SifException::conflict(
                'A single legacy USOC source enrollment is required'
            );
        }

        return $this->normalizeKeys($rows[0]);
    }

    private function destination(\PDO $db, int $idInsc): array
    {
        $stmt = $db->prepare(
            "SELECT ID, IDPAG, `ANY`, MES, CURS, A_PAGAR, PAGAMENT,
                    TIPUS_DESC, VALID_DESC, `INSC CURS`, pag_observacions
             FROM inscripcions
             WHERE ID = ?
             LIMIT 2"
        );
        $stmt->execute([$idInsc]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (count($rows) !== 1) {
            throw SifException::conflict(
                'A single legacy USOC destination enrollment is required'
            );
        }

        return $this->normalizeKeys($rows[0]);
    }

    private function assertSourceIdentity(
        array $source,
        int $idInsc,
        int $idpag
    ): void {
        if (
            (int) ($source['id'] ?? 0) !== $idInsc
            || (int) ($source['idpag'] ?? 0) !== $idpag
            || (int) ($source['tipus_desc'] ?? 0) !== 4
            || (int) ($source['valid_desc'] ?? 0) !== 1
        ) {
            throw SifException::conflict(
                'Legacy USOC source no longer matches prepared course change'
            );
        }
    }

    private function assertDestination(
        array $destination,
        int $idInsc,
        int $idpag,
        string $marker,
        array $target,
        string $expectedTotal
    ): void {
        $checks = [
            'ID_INSC' => (int) ($destination['id'] ?? 0) === $idInsc,
            'IDPAG' => (int) ($destination['idpag'] ?? 0) === $idpag,
            'YEAR' => (string) ($destination['year'] ?? '')
                === (string) ($target['year'] ?? ''),
            'MONTH' => (string) ($destination['month'] ?? '')
                === (string) ($target['month'] ?? ''),
            'COURSE' => (string) ($destination['course'] ?? '')
                === (string) ($target['course'] ?? ''),
            'A_PAGAR' => $this->money($destination['a_pagar'] ?? null)
                === $expectedTotal,
            'PAGAMENT' => $this->money($destination['pagament'] ?? null)
                === '0.00',
            'TIPUS_DESC' => (int) ($destination['tipus_desc'] ?? 0) === 4,
            'VALID_DESC' => (int) ($destination['valid_desc'] ?? 0) === 1,
            'INSC_CURS' => (string) ($destination['status'] ?? '') === '0',
            'RESERVATION_MARKER' => (string) ($destination['marker'] ?? '')
                === $marker,
        ];

        foreach ($checks as $field => $matches) {
            if (!$matches) {
                throw SifException::conflict(
                    'Legacy USOC destination no longer matches bound reservation: '
                    . $field
                );
            }
        }
    }

    private function review(
        \PDO $db,
        string $requestId,
        string $reason,
        array $bound
    ): never {
        $result = $bound;
        $result['phase'] = 'LEGACY_REVIEW_REQUIRED';
        $result['source_closed'] = false;
        $result['legacy_handoff_completed'] = false;
        $this->executions->markReviewRequired(
            $db,
            $requestId,
            $reason,
            $result
        );

        // REVIEW_REQUIRED is itself durable evidence. Commit it before
        // surfacing the conflict so the outer error path cannot roll it back.
        if ($db->inTransaction()) {
            $db->commit();
        }

        throw SifException::conflict(
            'USOC legacy course change requires review: ' . $reason
        );
    }

    private function normalizeKeys(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalized[strtolower((string) $key)] = $value;
        }

        if (array_key_exists('insc curs', $normalized)) {
            $normalized['status'] = $normalized['insc curs'];
        }
        if (array_key_exists('any', $normalized)) {
            $normalized['year'] = $normalized['any'];
        }
        if (array_key_exists('mes', $normalized)) {
            $normalized['month'] = $normalized['mes'];
        }
        if (array_key_exists('curs', $normalized)) {
            $normalized['course'] = $normalized['curs'];
        }
        if (array_key_exists('pag_observacions', $normalized)) {
            $normalized['marker'] = $normalized['pag_observacions'];
        }

        return $normalized;
    }

    private function money(mixed $value): string
    {
        $text = str_replace(',', '.', trim((string) $value));
        if (preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $text) !== 1) {
            throw SifException::conflict(
                'Invalid stored USOC course change monetary value'
            );
        }

        [$whole, $decimals] = array_pad(explode('.', $text, 2), 2, '');
        $cents = ((int) $whole * 100)
            + (int) substr(str_pad($decimals, 2, '0'), 0, 2);

        return intdiv($cents, 100)
            . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function decode(string $json, string $label): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw SifException::conflict('Invalid ' . $label);
        }

        return $decoded;
    }
}
