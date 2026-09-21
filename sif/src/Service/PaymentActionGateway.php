<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Database\TransactionRunner;
use Prisma\Sif\Repository\PaymentActionEventWriter;

final class PaymentActionGateway
{
    public function __construct(
        private \PDO $db,
        private TransactionRunner $transactions,
        private PaymentActionEventWriter $events
    ) {
    }

    public function run(array $auditContext, callable $operation): mixed
    {
        $this->appendRequested($auditContext);

        try {
            return $this->transactions->run(function (\PDO $db) use ($auditContext, $operation): mixed {
                $result = $operation($db);

                $this->events->append($db, $this->terminalEvent($auditContext, $result));

                return $result;
            });
        } catch (\Throwable $exception) {
            $this->appendFailed($auditContext, $exception);
            throw $exception;
        }
    }

    private function appendRequested(array $auditContext): void
    {
        $this->events->append($this->db, array_merge($auditContext, [
            'result' => 'REQUESTED',
            'is_terminal' => false,
        ]));
    }

    private function terminalEvent(array $auditContext, mixed $result): array
    {
        $resultData = is_array($result) ? $result : [];

        return array_merge($auditContext, [
            'uuid_payment' => $resultData['uuid_payment'] ?? ($auditContext['uuid_payment'] ?? null),
            'payment_idempotency_key' => $resultData['payment_idempotency_key']
                ?? ($auditContext['payment_idempotency_key'] ?? null),
            'before_hash' => $resultData['payment_audit_before_hash'] ?? ($auditContext['before_hash'] ?? null),
            'after_hash' => $resultData['payment_audit_after_hash'] ?? ($auditContext['after_hash'] ?? null),
            'changeset' => $resultData['payment_audit_changeset'] ?? ($auditContext['changeset'] ?? null),
            'result' => $this->terminalResult($resultData),
            'is_terminal' => true,
        ]);
    }

    private function appendFailed(array $auditContext, \Throwable $exception): void
    {
        try {
            $this->events->append($this->db, array_merge($auditContext, [
                'result' => 'FAILED',
                'is_terminal' => true,
                'error_code' => $this->errorCode($exception),
            ]));
        } catch (\Throwable) {
            // The operation has already failed or been rolled back. Re-throw the
            // original error so the caller does not treat the payment action as done.
        }
    }

    private function terminalResult(array $resultData): string
    {
        if (isset($resultData['payment_audit_result']) && is_string($resultData['payment_audit_result'])) {
            return strtoupper(trim($resultData['payment_audit_result']));
        }

        if (($resultData['idempotency_reused'] ?? false) === true) {
            return 'REUSED';
        }

        return 'SUCCEEDED';
    }

    private function errorCode(\Throwable $exception): string
    {
        $class = strtoupper(str_replace('\\', '_', $exception::class));

        return substr($class, 0, 80);
    }
}
