<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\UsocLifecycleExecutionRepository;

final class UsocCourseChangeDestinationBindingService
{
    public function __construct(
        private UsocLifecycleExecutionRepository $executions
    ) {
    }

    public function bind(
        \PDO $db,
        string $requestId,
        int $sourceIdInsc,
        int $sourceIdpag,
        int $destinationIdInsc,
        int $destinationIdpag,
        string $reservationMarker,
        string $targetStudentTotal
    ): array {
        $requestId = trim($requestId);
        $reservationMarker = trim($reservationMarker);
        $targetStudentTotal = $this->money($targetStudentTotal);

        if (
            $requestId === ''
            || $sourceIdInsc <= 0
            || $sourceIdpag <= 0
            || $destinationIdInsc <= 0
            || $destinationIdpag <= 0
            || $destinationIdpag === $sourceIdpag
            || preg_match('/^SIF-USOC-CC:[a-f0-9]{32}$/D', $reservationMarker) !== 1
        ) {
            throw SifException::validation(
                'Invalid USOC course change destination binding'
            );
        }

        $db->beginTransaction();
        try {
            $execution = $this->executions->findByRequestId(
                $db,
                $requestId,
                true
            );
            if ($execution === null) {
                throw SifException::conflict(
                    'USOC course change preparation checkpoint not found'
                );
            }

            if (
                (string) $execution['OPERATION'] !== 'COURSE_CHANGE'
                || (string) $execution['STATE'] !== 'REQUESTED'
                || (int) $execution['ID_INSC'] !== $sourceIdInsc
                || (int) $execution['IDPAG'] !== $sourceIdpag
            ) {
                throw SifException::conflict(
                    'USOC course change destination does not match checkpoint identity'
                );
            }

            $plan = $this->decodeObject(
                (string) $execution['PLAN_JSON'],
                'stored USOC course change preview'
            );
            $expectedStudentTotal = $this->money(
                $plan['target']['target_student_total'] ?? null
            );

            if ($expectedStudentTotal !== $targetStudentTotal) {
                throw SifException::conflict(
                    'USOC destination amount differs from frozen preview'
                );
            }

            $result = [
                'phase' => 'DESTINATION_RESERVED',
                'source_id_insc' => $sourceIdInsc,
                'destination_id_insc' => $destinationIdInsc,
                'source_idpag' => $sourceIdpag,
                'destination_idpag' => $destinationIdpag,
                'reservation_marker' => $reservationMarker,
                'target_student_total' => $targetStudentTotal,
                'effects_applied' => false,
                'source_closed' => false,
            ];

            $storedJson = trim((string) ($execution['RESULT_JSON'] ?? ''));
            if ($storedJson !== '') {
                $storedResult = $this->decodeObject(
                    $storedJson,
                    'stored USOC course change destination'
                );
                $storedPhase = strtoupper(trim((string) ($storedResult['phase'] ?? '')));
                if (!in_array($storedPhase, ['DESTINATION_RESERVED', 'LEGACY_COMPLETED'], true)) {
                    throw SifException::conflict(
                        'USOC course change destination is already in an incompatible phase'
                    );
                }

                $this->assertSameReservation($storedResult, $result);

                $db->commit();

                return [
                    'ok' => true,
                    'request_id' => $requestId,
                    'uuid_execution' => (string) $execution['UUID_EXECUTION'],
                    'state' => (string) $execution['STATE'],
                    'operation' => 'course_change',
                    'destination' => $storedResult,
                    'idempotency_reused' => true,
                ];
            }

            $stored = $this->executions->recordRequestedResult(
                $db,
                $requestId,
                $result
            );
            $db->commit();

            return [
                'ok' => true,
                'request_id' => $requestId,
                'uuid_execution' => (string) $stored['UUID_EXECUTION'],
                'state' => (string) $stored['STATE'],
                'operation' => 'course_change',
                'destination' => $result,
                'idempotency_reused' => false,
            ];
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }
    }

    private function assertSameReservation(
        array $stored,
        array $expected
    ): void {
        foreach ([
            'source_id_insc',
            'destination_id_insc',
            'source_idpag',
            'destination_idpag',
            'reservation_marker',
            'target_student_total',
        ] as $field) {
            if ((string) ($stored[$field] ?? '') !== (string) ($expected[$field] ?? '')) {
                throw SifException::conflict(
                    'USOC course change destination is already bound to a different reservation'
                );
            }
        }
    }

    private function money(mixed $value): string
    {
        $text = str_replace(',', '.', trim((string) $value));
        if (preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $text) !== 1) {
            throw SifException::validation(
                'Invalid USOC course change destination amount'
            );
        }

        [$whole, $decimals] = array_pad(explode('.', $text, 2), 2, '');
        $cents = ((int) $whole * 100)
            + (int) substr(str_pad($decimals, 2, '0'), 0, 2);

        if ($cents <= 0) {
            throw SifException::validation(
                'USOC course change destination amount must be positive'
            );
        }

        return intdiv($cents, 100)
            . '.'
            . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function decodeObject(string $json, string $label): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw SifException::conflict('Invalid ' . $label);
        }

        return $decoded;
    }
}
