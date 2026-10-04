<?php

namespace Prisma\Sif\Service;

final class ManualRefundActionService
{
    public function __construct(
        private PaymentActionGateway $gateway,
        private ManualRefundService $refunds
    ) {
    }

    public function registerByUuid(
        array $auditContext,
        string $uuidFactura,
        array $input
    ): array {
        return $this->run(
            $auditContext,
            $input,
            fn (\PDO $db): array => $this->refunds->registerByUuid(
                $db,
                $uuidFactura,
                $input
            )
        );
    }

    public function registerByNumVisible(
        array $auditContext,
        string $numVisible,
        array $input
    ): array {
        return $this->run(
            $auditContext,
            $input,
            fn (\PDO $db): array => $this->refunds->registerByNumVisible(
                $db,
                $numVisible,
                $input
            )
        );
    }

    private function run(
        array $auditContext,
        array $input,
        callable $operation
    ): array {
        $context = array_merge($auditContext, [
            'action' => 'LINK_REFUND',
            'payment_idempotency_key' => $this->auditKey(
                $input['idempotency_key'] ?? null
            ),
        ]);

        return $this->gateway->run($context, $operation);
    }

    private function auditKey(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (mb_strlen($value, 'UTF-8') <= 120) {
            return $value;
        }

        return 'REFUNDKEY|SHA256:' . hash('sha256', $value);
    }
}
