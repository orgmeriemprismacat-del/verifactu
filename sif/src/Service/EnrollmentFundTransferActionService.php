<?php

namespace Prisma\Sif\Service;

final class EnrollmentFundTransferActionService
{
    public function __construct(
        private PaymentActionGateway $gateway,
        private EnrollmentFundTransferService $transfers
    ) {
    }

    public function transfer(array $auditContext, array $input): array
    {
        $context = array_merge($auditContext, [
            'action' => 'REALLOCATE',
            'payment_idempotency_key' => $this->transferKey($input),
        ]);

        return $this->gateway->run(
            $context,
            fn (\PDO $db): array => $this->transfers->transferInTransaction(
                $db,
                $input
            )
        );
    }

    public function reverseTransfer(array $auditContext, array $input): array
    {
        $context = array_merge($auditContext, [
            'action' => 'UNALLOCATE',
            'payment_idempotency_key' => $this->reversalKey($input),
        ]);

        return $this->gateway->run(
            $context,
            fn (\PDO $db): array => $this->transfers->reverseTransferInTransaction(
                $db,
                $input
            )
        );
    }

    private function transferKey(array $input): ?string
    {
        $value = $input['idempotency_key'] ?? null;
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return $this->auditKey($value);
    }

    private function reversalKey(array $input): ?string
    {
        foreach (['movement_uuid', 'reverses_uuid_movement', 'transfer_uuid'] as $field) {
            if (!array_key_exists($field, $input) || $input[$field] === null) {
                continue;
            }

            $value = strtolower(trim((string) $input[$field]));
            if ($value === '') {
                return null;
            }

            return $this->auditKey('FUND|TRANSFER|REVERSAL|' . $value);
        }

        return null;
    }

    private function auditKey(string $value): string
    {
        if (mb_strlen($value, 'UTF-8') <= 120) {
            return $value;
        }

        return 'FUNDKEY|SHA256:' . hash('sha256', $value);
    }

}
