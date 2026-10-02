<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\UsocLifecycleExecutionRepository;

final class UsocCourseChangeExecutionPreparationService
{
    public function __construct(
        private UsocCourseChangePreviewService $preview,
        private UsocLifecycleExecutionRepository $executions
    ) {
    }

    public function prepare(
        \PDO $db,
        int $idInsc,
        int $idpag,
        string $requestId,
        string $actorId,
        array $roles,
        array $targetInput
    ): array {
        $requestId = $this->requestId($requestId);
        $actorId = trim($actorId);

        if ($idInsc <= 0 || $idpag <= 0 || $actorId === '') {
            throw SifException::validation(
                'Invalid USOC course change preparation identity'
            );
        }

        $request = [
            'target' => $this->normalizeTarget($targetInput),
        ];

        $existing = $this->executions->findByRequestId($db, $requestId);
        if ($existing !== null) {
            $storedPlan = $this->decodeObject(
                (string) $existing['PLAN_JSON'],
                'stored course change plan'
            );

            $execution = $this->executions->begin(
                $db,
                $requestId,
                'USOC|COURSE_CHANGE|ID_INSC:' . $idInsc,
                $idInsc,
                $idpag,
                'COURSE_CHANGE',
                $actorId,
                $roles,
                $request,
                $storedPlan
            );

            if ((string) $execution['STATE'] === 'REVIEW_REQUIRED') {
                throw SifException::conflict(
                    'USOC course change preparation requires review: '
                    . (string) ($execution['REVIEW_REASON'] ?? 'UNKNOWN')
                );
            }

            return $this->result($execution, $storedPlan, true);
        }

        $preview = $this->preview->preview(
            $db,
            $idInsc,
            $idpag,
            $request['target']
        );

        if (($preview['can_execute'] ?? true) !== false) {
            throw SifException::conflict(
                'USOC course change preview must remain non-executable during preparation'
            );
        }

        $db->beginTransaction();
        try {
            $execution = $this->executions->begin(
                $db,
                $requestId,
                'USOC|COURSE_CHANGE|ID_INSC:' . $idInsc,
                $idInsc,
                $idpag,
                'COURSE_CHANGE',
                $actorId,
                $roles,
                $request,
                $preview
            );
            $db->commit();
        } catch (\Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $exception;
        }

        return $this->result($execution, $preview, false);
    }

    private function result(
        array $execution,
        array $preview,
        bool $reused
    ): array {
        return [
            'ok' => true,
            'request_id' => (string) $execution['REQUEST_ID'],
            'uuid_execution' => (string) $execution['UUID_EXECUTION'],
            'state' => (string) $execution['STATE'],
            'operation' => strtolower((string) $execution['OPERATION']),
            'preview' => $preview,
            'effects_applied' => false,
            'idempotency_reused' => $reused,
        ];
    }

    private function normalizeTarget(array $input): array
    {
        $fields = [
            'target_standard_course_amount',
            'target_student_course_amount',
            'management_fee',
        ];

        $result = $input;
        foreach ($fields as $field) {
            if (!array_key_exists($field, $result)) {
                throw SifException::validation(
                    'Missing USOC course change target field: ' . $field
                );
            }
            $result[$field] = trim((string) $result[$field]);
        }

        foreach (['year', 'month', 'course', 'kind', 'title'] as $field) {
            if (array_key_exists($field, $result)) {
                $result[$field] = trim((string) $result[$field]);
            }
        }

        if (array_key_exists('price_id', $result)) {
            if (!is_numeric($result['price_id']) || (int) $result['price_id'] <= 0) {
                throw SifException::validation(
                    'Invalid USOC course change target price id'
                );
            }
            $result['price_id'] = (int) $result['price_id'];
        }

        return $result;
    }

    private function requestId(string $value): string
    {
        $value = trim($value);
        if (
            $value === ''
            || strlen($value) > 120
            || preg_match('/^[A-Za-z0-9._:-]+$/D', $value) !== 1
        ) {
            throw SifException::validation(
                'Invalid USOC course change request id'
            );
        }

        return $value;
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
