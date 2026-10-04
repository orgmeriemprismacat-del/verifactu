<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Contract\GroupParticipantAcademicGatewayInterface;
use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\GroupParticipantChangeExecutionRepository;

final class GroupParticipantAdditionCoordinator
{
    public function __construct(
        private GroupParticipantAdditionPreviewService $previewService,
        private GroupParticipantAdditionDecisionService $decisionService,
        private GroupParticipantChangeFingerprint $fingerprints,
        private GroupParticipantChangeExecutionRepository $executions,
        private GroupParticipantAcademicGatewayInterface $academic
    ) {
    }

    public function preview(\PDO $sifDb, string $uuidFactura, array $candidate): array
    {
        $preview = $this->previewService->preview($sifDb, $uuidFactura, $candidate);
        $preview['fingerprint'] = $this->fingerprints->calculate($preview);

        return $preview;
    }

    public function confirm(
        \PDO $sifDb,
        \PDO $legacyDb,
        string $uuidFactura,
        array $candidate,
        string $expectedFingerprint,
        array $decision,
        array $context
    ): array {
        $actorId = trim((string) ($context['actor_id'] ?? ''));
        $correlationId = trim((string) ($context['correlation_id'] ?? ''));
        $idempotencyKey = trim((string) ($context['idempotency_key'] ?? ''));
        if ($actorId === '' || $correlationId === '' || $idempotencyKey === '') {
            throw SifException::forbidden(
                'Group participant addition confirmation requires actor, correlation and idempotency context'
            );
        }

        $idInsc = (int) ($candidate['id_insc'] ?? 0);
        if ($idInsc <= 0) {
            throw SifException::validation('Invalid group participant candidate enrollment ID');
        }

        $expectedFingerprint = strtolower(trim($expectedFingerprint));
        if (preg_match('/^[a-f0-9]{64}$/D', $expectedFingerprint) !== 1) {
            throw SifException::validation('Invalid group addition expected fingerprint');
        }

        $decisionHash = $this->fingerprints->calculate($decision);
        $execution = $this->executions->findByIdempotencyKey($sifDb, $idempotencyKey, true);
        $plan = null;
        $actualFingerprint = $expectedFingerprint;

        if ($execution !== null) {
            if ((string) $execution['CHANGE_TYPE'] !== 'ADD'
                || (string) $execution['UUID_FACTURA'] !== trim($uuidFactura)
                || (int) $execution['ID_INSC'] !== $idInsc
                || (string) $execution['DECISION_HASH'] !== $decisionHash
                || (string) $execution['EXPECTED_FINGERPRINT'] !== $expectedFingerprint
            ) {
                throw SifException::conflict(
                    'Group participant addition idempotency key belongs to another command'
                );
            }

            $plan = json_decode((string) $execution['PLAN_JSON'], true);
            if (!is_array($plan)) {
                throw new \RuntimeException('Stored group participant addition plan is invalid');
            }

            if ((string) $execution['STATUS'] === 'COMPLETED') {
                return $this->storedExecutionResult($execution, true);
            }
        } else {
            $preview = $this->previewService->preview($sifDb, $uuidFactura, $candidate);
            $actualFingerprint = $this->fingerprints->calculate($preview);
            if (!hash_equals($expectedFingerprint, $actualFingerprint)) {
                throw SifException::conflict(
                    'Group participant addition preview changed before confirmation'
                );
            }

            $plan = $this->decisionService->plan($preview, $decision);
            if (($plan['executable'] ?? false) !== true) {
                return [
                    'ok' => false,
                    'action' => 'confirm',
                    'status' => 'REVIEW_REQUIRED',
                    'fingerprint' => $actualFingerprint,
                    'plan' => $plan,
                ];
            }

            $execution = $this->executions->createOrReuse($sifDb, [
                'idempotency_key' => $idempotencyKey,
                'change_type' => 'ADD',
                'uuid_factura' => $uuidFactura,
                'id_insc' => $idInsc,
                'idpag' => $candidate['idpag'] ?? null,
                'expected_fingerprint' => $actualFingerprint,
                'decision_hash' => $decisionHash,
                'correlation_id' => $correlationId,
                'actor_id' => $actorId,
                'plan' => $plan,
            ]);
        }

        $uuidExecution = (string) $execution['UUID_EXECUTION'];
        $results = [];
        $order = 1;

        foreach ($plan['actions'] as $action) {
            $type = strtoupper((string) ($action['type'] ?? ''));

            if ($type === 'ACADEMIC_ADDITION') {
                $results[$type] = $this->runStep(
                    $sifDb,
                    $uuidExecution,
                    $type,
                    $order++,
                    $action,
                    fn(): array => $this->academic->addParticipant(
                        $legacyDb,
                        $idInsc,
                        array_merge($context, [
                            'uuid_execution' => $uuidExecution,
                            'uuid_factura' => $uuidFactura,
                            'idpag' => $execution['IDPAG'] ?? null,
                        ])
                    )
                );
                continue;
            }

            if (in_array($type, [
                'SUPPLEMENTAL_INVOICE',
                'GROUP_RECTIFICATION',
                'REPRICE_EXISTING_GROUP',
            ], true)) {
                return $this->waitExternal(
                    $sifDb,
                    $uuidExecution,
                    $type,
                    $actualFingerprint,
                    $plan,
                    $results,
                    'Fiscal execution for post-issue group addition requires an approved dedicated executor'
                );
            }
        }

        $result = [
            'ok' => true,
            'action' => 'confirm',
            'status' => 'COMPLETED',
            'uuid_execution' => $uuidExecution,
            'fingerprint' => $actualFingerprint,
            'fingerprint_verified' => true,
            'plan' => $plan,
            'steps' => $results,
        ];
        $this->executions->completeExecution($sifDb, $uuidExecution, $result);

        return $result;
    }

    private function runStep(
        \PDO $sifDb,
        string $uuidExecution,
        string $stepName,
        int $order,
        array $input,
        callable $operation
    ): array {
        $inputHash = $this->fingerprints->calculate($input);
        $step = $this->executions->beginStep(
            $sifDb,
            $uuidExecution,
            $stepName,
            $order,
            $inputHash
        );

        if ((string) $step['STATUS'] === 'COMPLETED') {
            $stored = json_decode((string) ($step['RESULT_JSON'] ?? '{}'), true);
            return is_array($stored) ? $stored : [];
        }

        try {
            $result = $operation();
            $this->executions->completeStep($sifDb, $uuidExecution, $stepName, $result);
            return $result;
        } catch (\Throwable $exception) {
            $this->executions->failStep(
                $sifDb,
                $uuidExecution,
                $stepName,
                $exception::class,
                $exception->getMessage()
            );
            throw $exception;
        }
    }

    private function waitExternal(
        \PDO $sifDb,
        string $uuidExecution,
        string $stepName,
        string $fingerprint,
        array $plan,
        array $results,
        string $reason
    ): array {
        $result = [
            'ok' => false,
            'action' => 'confirm',
            'status' => 'WAITING_EXTERNAL',
            'uuid_execution' => $uuidExecution,
            'fingerprint' => $fingerprint,
            'fingerprint_verified' => true,
            'waiting_step' => $stepName,
            'reason' => $reason,
            'plan' => $plan,
            'steps' => $results,
        ];
        $this->executions->markWaitingExternal($sifDb, $uuidExecution, $stepName, $result);

        return $result;
    }

    private function storedExecutionResult(array $execution, bool $reused): array
    {
        $stored = json_decode((string) ($execution['RESULT_JSON'] ?? '{}'), true);
        if (!is_array($stored)) {
            $stored = [];
        }
        $stored['idempotency_reused'] = $reused;

        return $stored;
    }
}
